<?php

namespace App\Controller\Admin;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Repository\StatementRepository;

class StatementFixController extends AbstractController
{
    #[Route('/admin/statement/inconsistencies', name: 'admin_statement_inconsistencies', methods: ['GET'])]
    public function statementDocuments(Connection $connection, StatementRepository $statementRepository): Response
    {
        $qb = $statementRepository->createQueryBuilder('s');
        $qb->where($qb->expr()->in('s.id', $this->getStatementIds($connection)));

        return $this->render('admin/Statement/inconsistencies.html.twig', [
            'statements' => $qb->getQuery()->getResult(),
        ]);
    }

    private function getStatementIds(Connection $connection): array
    {
        $query = <<<EOT
SELECT distinct t.statement_id
    FROM transaction t
INNER JOIN (
    SELECT
        d.transaction_id,
        SUM(d.amount) AS total_document_amount
    FROM document d
    WHERE d.type IN ('income', 'expense', 'tax')
    GROUP BY d.transaction_id
) doc_sum ON t.id = doc_sum.transaction_id
WHERE abs(t.amount - doc_sum.total_document_amount) > 100
EOT;

        return $connection->executeQuery($query)->fetchFirstColumn();
    }
}