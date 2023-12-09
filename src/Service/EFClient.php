<?php

namespace App\Service;

use App\Entity\Invoice;
use DateTime;
use DateTimeZone;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use App\Exception\EfClientException;

class EFClient
{
    public function __construct(
        private readonly HttpClientInterface $efClient,
        private readonly TenantContext       $tenantContext,
        private readonly LiveModeContext     $liveModeContext,
        private readonly LoggerInterface     $logger,
        private readonly string              $efApiVersion,
        private readonly string              $efCfdiVersion
    ) {
    }

    /**
     * @throws EfClientException
     */
    public function testConnection(): array
    {
        return $this->executeRequest('probarConexion');
    }

    /**
     * @throws EfClientException
     * @throws Exception
     */
    public function listInvoices(string $code, ?DateTime $since = null): array
    {
        $since ??= new DateTime('2017-01-01');

        return $this->executeRequest('listarComprobantes', [
            'Comprobantes' => [
                'fechaInicial' => $since->format('Y-m-d H:i:s'),
                'fechaFinal'   => (new DateTime('now',
                    new DateTimeZone('America/Mexico_City')))->format('Y-m-d H:i:s'),
                'serie'        => $code
            ]
        ]);
    }

    public function sendEmail(Invoice $invoice, array $emails, ?string $message = null): array
    {
        return $this->executeRequest('enviarCorreo', [
            'CFDi' => [
                'serie'      => $invoice->getSeries(),
                'folio'      => $invoice->getNumber(),
                'EnviarCFDI' => array_filter([
                    'Correos'       => $emails,
                    'mensajeCorreo' => $message
                ])
            ]
        ]);
    }

    /**
     * @throws EfClientException
     */
    public function getInvoice(Invoice $invoice, ?bool $incluirPeticion = false): array
    {
        return $this->executeRequest('informacionCfdi', [
            'incluirPeticion' => (int)$incluirPeticion,
            'CFDi'            => array_filter([
                'folio' => $invoice->getNumber(),
                'serie' => $invoice->getSeries()
            ])
        ]);
    }

    /**
     * @throws EfClientException
     */
    public function cancelInvoice(Invoice $invoice, Invoice $substituteInvoice, string $reason): array
    {
        return $this->executeRequest('cancelarCfdi', [
            'CFDi' => [
                'serie'                  => $invoice->getSeries(),
                'folio'                  => $invoice->getNumber(),
                'justificacion'          => $reason,
                'motivo'                 => '01',
                'ComprobanteSustitucion' => [
                    'serie' => $substituteInvoice->getSeries(),
                    'folio' => $substituteInvoice->getNumber(),
                ]
            ]
        ]);
    }

    /**
     * @throws EfClientException
     */
    private function executeRequest(string $action, array $requestData = []): array
    {
        $liveMode = $this->liveModeContext->getLiveMode();
        $rfc      = $this->tenantContext->getTenant()->getRfc();
        $request  = [
            'Solicitud' => array_merge(
                [
                    'rfc'    => $rfc,
                    'modo'   => $liveMode ? 'produccion' : 'debug',
                    'accion' => $action,
                ],
                $requestData
            )
        ];

        return $this->sendRequest($action, 'POST', $request);
    }

    /**
     * @throws EfClientException
     */
    public function createInvoice(array $cfdi): array
    {
        return $this->sendRequest('generarCfdi', 'POST', $cfdi);
    }

    /**
     * @throws EfClientException
     */
    private function sendRequest(string $path, string $method, ?array $body = null): ?array
    {
        try {
            $tenant = $this->tenantContext->getTenant();

            $this->logger->debug('EF-Request: ' . json_encode($body));

            $credentials = base64_encode($tenant->getRfc() . ':' . $tenant->getToken());
            $options     = [
                'headers' => [
                    'X-API-KEY'     => $tenant->getApiKey(),
                    'Authorization' => 'Basic ' . $credentials,
                    'Content-Type'  => 'application/json',
                    'verify_peer'   => false
                ]
            ];
            $response    = $this->efClient
                ->withOptions($options)
                ->request($method, $this->buildUri($path), array_filter([
                        'json' => $body
                    ])
                );

            $json       = $response->getContent(false);
            $statusCode = $response->getStatusCode();
            $this->logger->debug('EF-Request: ' . $json);

            if ($statusCode >= 400) {
                throw new EfClientException('Error: ' . $json, $statusCode);
            }

            // validate the response and return its content or throw an exception
            return $this->extractResponse(json_decode($json, true));
        } catch (ExceptionInterface $e) {
            throw new EfClientException($e->getMessage(), $e->getCode());
        }
    }

    private function buildUri(string $path): string
    {
        return sprintf('/v6/%s', $path);
    }

    /**
     * @throws EfClientException
     */
    private function extractObjects(?array $response, string $key): array
    {
        $this->assertValidResponse($response);

        $results = $response['AckEnlaceFiscal'][$key] ?? null;
        if (null === $results) {
            throw new EfClientException('No results found for key ' . $key);
        }

        return $results;
    }

    private function extractSingleObject(?array $response): ?array
    {
        return $response['AckEnlaceFiscal'] ?? null;
    }

    /**
     * @throws EfClientException
     */
    private function assertValidResponse(?array $response): void
    {
        if (!isset($response['AckEnlaceFiscal'])) {
            throw new EfClientException('Unsuccessful request.');
        }
    }

    /**
     * @throws EfClientException
     */
    private function extractResponse(array $response): array
    {
        if (!isset($response['AckEnlaceFiscal'])) {
            throw new EfClientException('Unsuccessful request.');
        }

        return $response['AckEnlaceFiscal'];
    }
}