<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Invoice;
use App\Repository\CustomerRepository;
use App\Repository\InvoiceRepository;
use App\Repository\SeriesRepository;
use App\Service\InvoiceFileManager;

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
            'invoicesByYear' => $invoiceRepo->getHistory($activeSeries, $year ? (int)$year : null),
            'customers'      => $customerRepository->getOptions()
        ]);
    }

    #[Route('/download/{invoice}', name: 'admin_invoice_download')]
    public function download(Invoice $invoice, InvoiceFileManager $invoiceFileManager): Response
    {
        $filePath = $invoiceFileManager->getPdfPath($invoice);
        $response = new StreamedResponse(function () use ($filePath) {
            $fileStream   = fopen($filePath, 'rb');
            $outputStream = fopen('php://output', 'wb');
            stream_copy_to_stream($fileStream, $outputStream);
            fclose($fileStream); // Don't forget to close the resource handle!
        });

        // Set headers for showing the file in browser
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set(
            'Content-Disposition',
            'inline; filename="' . $invoiceFileManager->getPdfFilename($invoice) . '"'
        );

        return $response;
    }
}
