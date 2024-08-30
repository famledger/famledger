<?php

namespace App\Controller\Admin;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Constant\AccountType;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;

#[Route('/admin/yearlyReport')]
class YearlyReportController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route('/{year}', name: 'admin_yearlyReport', defaults: ['year' => null])]
    public function history(
        Request               $request,
        TransactionRepository $transactionRepository,
        AccountRepository     $accountRepository,
        ?string               $year = null
    ): Response {
        $year = $year ?? $request->query->get('year');

        return $this->render('admin/YearlyReport/index.html.twig', [
            'year'           => $year,
            'expensesByYear' => $transactionRepository->getYearlyReportData($year ? (int)$year : null),
            'accounts'       => $accountRepository->getOptions(AccountType::BANK_ACCOUNT),
        ]);
    }
}
