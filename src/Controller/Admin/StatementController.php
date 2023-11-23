<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

use App\Constant\DocumentType;
use App\Entity\Document;
use App\Entity\Invoice;
use App\Entity\Statement;
use App\Entity\Transaction;
use App\Repository\AttachmentRepository;
use App\Repository\DocumentRepository;
use App\Service\DocumentService;
use App\Service\StatementService;

class StatementController extends AbstractController
{
    #[Route('/admin/statement/{statement}/documents', name: 'admin_statement_documents', methods: ['GET'])]
    public function statementDocuments(Statement $statement, DocumentRepository $documentRepository): Response
    {
        return $this->render('admin/Statement/_cardDocuments.html.twig', [
            'statement' => $statement,
            'documents' => $documentRepository->findUnLinked($statement),
        ]);
    }

    #[Route('/admin/statement/{statement}/attachments', name: 'admin_statement_attachments', methods: ['GET'])]
    public function statementAttachments(Statement $statement, AttachmentRepository $attachmentRepository): Response
    {
        return $this->render('admin/Statement/_cardAttachments.html.twig', [
            'statement'   => $statement,
            'attachments' => $attachmentRepository->findPendingAttachments($statement),
        ]);
    }

    #[Route('/admin/statement/{statement}/addAnnotationDocument', name: 'admin_statement_addAnnotationDocument', methods: ['POST'])]
    public function addAnnotationDocument(
        Request                $request,
        Statement              $statement,
        DocumentService        $documentService,
        AdminUrlGenerator      $adminUrlGenerator,
        EntityManagerInterface $em
    ): Response {
        try {
            $filename  = $request->request->get('filename');
            $amount    = $request->request->get('amount');
            $csrfToken = $request->request->get('_csrf_token');
            if (!$this->isCsrfTokenValid('add-annotation-document-' . $statement->getId(), $csrfToken)) {
                throw new Exception('Invalid CSRF token');
            }

            $filename = str_ends_with($filename, '.txt') ? $filename : ($filename . '.txt');
            $document = $documentService->createAnnotationDocument($statement, $filename, $amount);

            $em->persist($document);
            $em->flush();
        } catch (Exception $e) {
            return $this->json([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]);
        }

        return $this->redirectToDetailsPage($adminUrlGenerator, $statement);
    }

    #[Route('/admin/statement/{statement}/link', name: 'admin_statement_link', methods: ['POST'])]
    public function link(
        Request                $request,
        Statement              $statement,
        StatementService       $statementService,
        EntityManagerInterface $em
    ): Response {

        try {
            $transactionId = $request->request->get('transactionId');
            if (null === $transaction = $em->getRepository(Transaction::class)->find($transactionId)) {
                return new JsonResponse(['error' => 'Transaction not found'], 404);
            }
            if ($transaction->getStatement() !== $statement) {
                throw new Exception('Transaction does not belong to this statement');
            }

            $type = $request->request->get('type');
            switch($type) {
                case 'attachment':
                    $attachment = $em->getRepository(Document::class)->find($request->request->get('attachmentId'));
                    $statementService->linkDocument($transaction, $attachment);
                    break;
                case 'document':
                    $document = $em->getRepository(Document::class)->find($request->request->get('documentId'));
                    $statementService->linkDocument($transaction, $document);
                    break;
                case 'invoice':
                    $invoice = $em->getRepository(Invoice::class)->find($request->request->get('invoiceId'));
                    $statementService->linkInvoice($transaction, $invoice);
                    break;
                default:
                    throw new Exception('Invalid type');
            }

            $em->flush();

            return $this->render('admin/Statement/detail_transaction.html.twig', [
                'transaction' => $transaction,
                'statement'   => $statement,
            ]);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/admin/statement/{statement}/unlink', name: 'admin_statement_unlink', methods: ['POST'])]
    public function unlink(
        Statement              $statement,
        Request                $request,
        EntityManagerInterface $em,
        StatementService       $statementService
    ): Response {

        try {
            if (null === $document = $em->getRepository(Document::class)->find($request->request->get('documentId'))) {
                throw new Exception('Document not found');
            }
            $transaction = $document->getTransaction();
            if ($transaction->getStatement()->getId() !== $statement->getId()) {
                throw new Exception('Transaction does not belong to this statement');
            }
            $statementService->unLinkDocument($transaction, $document);
            $em->flush();

            return $this->render('admin/Statement/detail_transaction.html.twig', [
                'transaction' => $transaction,
                'statement'   => $statement,
            ]);
        } catch (Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/admin/transaction/{transaction}/comment', name: 'admin_transaction_comment', methods: ['POST'])]
    public function comment(
        Transaction            $transaction,
        Request                $request,
        EntityManagerInterface $em
    ): Response {

        try {
            $transaction->setComment($request->request->get('comment'));
            $em->flush();

            return new JsonResponse(['status' => 'success']);
        } catch (Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/admin/statement/{statement}/consolidate', name: 'admin_statement_consolidate', methods: ['GET'])]
    public function consolidate(
        Statement              $statement,
        Request                $request,
        EntityManagerInterface $em,
        AdminUrlGenerator      $adminUrlGenerator
    ): Response {

        try {
            foreach ($statement->getTransactions() as $transaction) {
                if ($transaction->getType() === DocumentType::ACCOUNT_STATEMENT) {
                    continue;
                }
                $transaction->updateConsolidationStatus();
                if ($transaction->getStatus() !== Transaction::STATUS_CONSOLIDATED) {
                    throw new Exception(sprintf('Transaction %d is not consolidated', $transaction->getSequenceNo()));
                }
            }
            $statement->setStatus(Statement::STATUS_CONSOLIDATED);
            $em->flush();
            $request->getSession()->getFlashBag()->add('success', 'The statement has been consolidated');
        } catch (Exception $e) {
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }

        return $this->redirectToDetailsPage($adminUrlGenerator, $statement);
    }

    #[Route('/admin/statement/{statement}/unConsolidate', name: 'admin_statement_unConsolidate', methods: ['GET'])]
    public function unConsolidate(
        AdminUrlGenerator      $adminUrlGenerator,
        EntityManagerInterface $em,
        Request                $request,
        Statement              $statement,
    ): Response {

        $statement->setStatus(Statement::STATUS_PENDING);
        $em->flush();
        $request->getSession()->getFlashBag()->add('success', 'The statement has been un-consolidated');

        return $this->redirectToDetailsPage($adminUrlGenerator, $statement);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->showEntityActionsInlined();
    }

    private function redirectToDetailsPage(AdminUrlGenerator $adminUrlGenerator, Statement $statement): Response
    {
        return $this->redirect($adminUrlGenerator
            ->setController(StatementCrudController::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($statement->getId())
            ->generateUrl()
        );
    }
}