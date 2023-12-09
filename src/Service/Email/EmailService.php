<?php

namespace App\Service\Email;

use Doctrine\ORM\EntityManagerInterface;
use Exception;

use App\Entity\EmailLog;
use App\Entity\Invoice;
use App\Repository\EmailLogRepository;
use App\Service\EFClient;

class EmailService
{
    public function __construct(
        private readonly EFClient               $efClient,
        private readonly EmailLogRepository     $emailLogRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws Exception
     */
    public function sendEmails(Invoice $invoice, array $emails, ?string $message = null): void
    {
        $emailLog = (new EmailLog())
            ->setInvoice($invoice)
            ->setMessage($message);
        $this->em->persist($emailLog);
        $this->em->flush();

        $apiResponse = $this->efClient->sendEmail($invoice, $emails, $message);
        if (isset($apiResponse['errores'])) {
            $errors = $apiResponse['errores'];
            throw new Exception($errors[0]['descripcionError']);
        }

        $emailLog
            ->setStatus($apiResponse['estatus'])
            ->setDateSent(\DateTime::createFromFormat('Y-m-d H:i:s', $apiResponse['fechaMensaje']))
            ->setDocumentStatus($apiResponse['estatusDocumento']);
        $this->em->flush();
    }

    public function getInvoiceEmailLog(Invoice $invoice): ?EmailLog
    {
        return $this->emailLogRepository->findOneBy(['invoice' => $invoice]);
    }
}