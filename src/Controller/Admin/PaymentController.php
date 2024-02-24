<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Repository\CustomerRepository;
use App\Repository\PaymentTransactionRepository;

#[Route('/admin/payment')]
class PaymentController extends AbstractController
{
    #[Route('/history/{year}', name: 'admin_payment_history', defaults: ['year' => null])]
    public function history(
        Request                      $request,
        PaymentTransactionRepository $paymentRepo,
        CustomerRepository           $customerRepository,
        ?string                      $year = null
    ): Response {
        $year = $year ?? $request->query->get('year');

        return $this->render('admin/Payment/history.html.twig', [
            'year'           => $year,
            'paymentsByYear' => $paymentRepo->getHistory($year ? (int)$year : null),
            'customers'      => $customerRepository->getOptions(),
        ]);
    }
}
