<?php

namespace App\Controller\Admin;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Invoice;
use App\Exception\EfClientException;
use App\Repository\CustomerRepository;
use App\Repository\InvoiceRepository;
use App\Repository\SeriesRepository;
use App\Service\Helper\ResponseHelper;
use App\Service\InvoiceFileManager;
use App\Service\Invoice\InvoiceSynchronizer;
use App\Service\TenantContext;

#[Route('/admin/invoice')]
class InvoiceController extends AbstractController
{
    #[Route('/history/{year}', name: 'admin_invoice_history', defaults: ['year' => null])]
    public function history(
        Request            $request,
        InvoiceRepository  $invoiceRepo,
        CustomerRepository $customerRepository,
        SeriesRepository   $seriesRepository,
        ?string            $year = null
    ): Response {
        $activeSeries = $seriesRepository->getActiveSeries();
        $year         = $year ?? $request->query->get('year');

        return $this->render('admin/Invoice/history.html.twig', [
            'year'           => $year,
            'invoicesByYear' => $invoiceRepo->getHistory($activeSeries, $year ? (int)$year : null),
            'customers'      => $customerRepository->getOptions()
        ]);
    }

    #[Route('/{invoice}/paymentDate', name: 'admin_invoice_paymentDate')]
    public function paymentDate(
        Invoice                $invoice,
        Request                $request,
        EntityManagerInterface $em
    ): Response|JsonResponse {
        $paymentDate = $request->request->get('paymentDate');
        $paymentDate = !empty($paymentDate) ? new DateTime($paymentDate) : null;
        $invoice->setPaymentDate($paymentDate);
        $em->flush();

        $html = $this->renderView('admin/Invoice/_invoicePaymentDate.html.twig', [
            'invoice'     => $invoice,
            'transaction' => $invoice->getDocument()?->getTransaction(),
        ]);

        return new JsonResponse(['html' => $html, 'id' => $invoice->getId()]);
    }

    #[Route('/fetch', name: 'admin_invoice_fetch')]
    public function fetch(
        EntityManagerInterface $em,
        InvoiceSynchronizer    $invoiceSynchronizer,
        Request                $request,
        TenantContext          $tenantContext,
    ): Response {
        try {
            $report  = $invoiceSynchronizer->fetchActiveSeries($tenantContext->getTenant());
            $message = '';
            foreach ($report as $key => $countProcessed) {
                $message .= sprintf('%s: %d<br/>', $key, $countProcessed);
            }
            $em->flush();

            $request->getSession()->getFlashBag()->add('success', $message);
        } catch (EfClientException $e) {
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }

        return $this->redirect("/admin?routeName=admin_invoice_history");
    }

    #[Route('/download/{invoice}', name: 'admin_invoice_download')]
    public function download(Invoice $invoice, InvoiceFileManager $invoiceFileManager): Response
    {
        $filePath = $invoiceFileManager->getPdfPath($invoice);
        $filename = $invoiceFileManager->getPdfFilename($invoice);

        return ResponseHelper::createPdfResponse($filePath, $filename);
    }

    #[Route('/{invoice}/copyToOutbox', name: 'admin_invoice_outbox', methods: ['POST'])]
    public function copyToOutbox(
        Invoice            $invoice,
        InvoiceFileManager $invoiceFileManager,
        string             $outboxFolder
    ): Response {
        $filePath = $invoiceFileManager->getPdfPath($invoice);

        if (!file_exists($filePath)) {
            return new Response('File not found', Response::HTTP_NOT_FOUND);
        }
        $filePath = $invoiceFileManager->getPdfPath($invoice);
        $filename = $invoiceFileManager->getPdfFilename($invoice);

        copy($filePath, $outboxFolder . '/' . $filename);

        return new Response($filePath);
    }
}
