<?php

namespace App\Controller\Admin;

use App\Constant\AccountType;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;

#[Route('/admin/expense')]
class ExpenseController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route('/{year}', name: 'admin_expense', defaults: ['year' => null])]
    public function history(
        Request               $request,
        TransactionRepository $transactionRepository,
        AccountRepository     $accountRepository,
        ?string               $year = null
    ): Response {
        $year = $year ?? $request->query->get('year');

        return $this->render('admin/Expense/history.html.twig', [
            'year'           => $year,
            'expensesByYear' => $transactionRepository->getExpenseHistory($year ? (int)$year : null),
            'accounts'       => $accountRepository->getOptions(AccountType::CREDIT_CARD),
        ]);
    }
}
