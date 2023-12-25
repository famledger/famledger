<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\Invoice;
use App\Repository\CustomerRepository;
use App\Repository\InvoiceRepository;
use App\Repository\SeriesRepository;
use App\Service\Helper\ResponseHelper;
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
        $filename = $invoiceFileManager->getPdfFilename($invoice);

        return ResponseHelper::createPdfResponse($filePath, $filename);
    }
}
