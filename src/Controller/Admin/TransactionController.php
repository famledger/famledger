<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Repository\CustomerRepository;
use App\Repository\TransactionRepository;
use App\Repository\SeriesRepository;

#[Route('/admin/transaction')]
class TransactionController extends AbstractController
{
    #[Route('/history/{year}', name: 'admin_payment_history', defaults: ['year' => null])]
    public function history(
        Request               $request,
        TransactionRepository $transactionRepo,
        CustomerRepository    $customerRepository,
        SeriesRepository      $seriesRepository,
        ?string               $year = null
    ): Response {
        $activeSeries = $seriesRepository->getActiveSeries();
        $year         = $year ?? $request->query->get('year');

        return $this->render('admin/Transaction/history.html.twig', [
            'transactionsByYear' => $transactionRepo->getHistory($activeSeries, $year ? (int)$year : null),
            'customers'          => $customerRepository->getOptions()
        ]);
    }
}
