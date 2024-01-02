<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

use App\Entity\FinancialMonth;
use App\Service\Accounting\AccountingDocumentService;
use App\Service\Accounting\AccountingFolderComparator;
use App\Service\Accounting\AccountingFolderManager;
use App\Service\Helper\ResponseHelper;

class AccountingFilesController extends AbstractController
{
    #[Route('/admin/accountingFiles/{financialMonth}/comparison/{subFolder}',
        name: 'admin_accountingFiles_comparison',
        options: ['defaults' => ['subFolder' => null]],
        methods: ['GET']
    )]
    public function comparison(
        FinancialMonth          $financialMonth,
        AccountingFolderManager $accountingFolderManager,
        ?string                 $subFolder = null,
    ): Response {
        try {
            $accountingFolder   = $accountingFolderManager->getAccountingFolderPath($financialMonth, false);
            $accountantFolder   = $accountingFolderManager->getAccountantFolderPath($financialMonth, false);
            $folderSynchronizer = AccountingFolderComparator::create(
                $accountingFolder . ($subFolder ? "/$subFolder" : ''),
                $accountantFolder . ($subFolder ? "/$subFolder" : ''),
            );

            return $this->render('admin/Accounting/cardFileSync.html.twig', [
                'financialMonth'    => $financialMonth,
                'subFolder'         => $subFolder,
                'accountingFolder'  => $accountingFolderManager->getAccountingFolderPath($financialMonth, false),
                'accountantFolder'  => $accountingFolderManager->getAccountantFolderPath($financialMonth, false),
                'byContentChecksum' => $folderSynchronizer->getByContentChecksum(),
                'byNameChecksum'    => $folderSynchronizer->getByNameChecksum(),
            ]);
        } catch (Exception $e) {
            return new Response($e->getMessage());
        }
    }


    #[Route('/admin/accountingFiles/{financialMonth}/{location}/{filename}/{isAttachment}',
        name: 'admin_accountingFiles_download',
        defaults: ['isAttachment' => false],
        methods: ['GET']
    )]
    public function download(
        FinancialMonth          $financialMonth,
        string                  $location,
        string                  $filename,
        bool                    $isAttachment,
        AccountingFolderManager $accountingFolderManager
    ): Response {

        try {
            $folder   = ($location === 'source')
                ? $accountingFolderManager->getAccountingFolderPath($financialMonth, $isAttachment)
                : $accountingFolderManager->getAccountantFolderPath($financialMonth, $isAttachment);
            $filePath = sprintf('%s/%s',
                $folder,
                $filename
            );

            return ResponseHelper::createPdfResponse($filePath, $filename);
        } catch (Exception $e) {
        }
    }

    #[Route('/admin/accountingFiles/{financialMonth}', name: 'admin_accountingFiles_update', methods: ['POST'])]
    public function update(
        Request                   $request,
        FinancialMonth            $financialMonth,
        AccountingDocumentService $accountingDocumentService,
        AccountingFolderManager   $accountingFolderManager,
        EntityManagerInterface    $em,
    ): Response {
        try {
            $requestData    = json_decode($request->getContent(), true);
            $location       = $requestData['location'] ?? null;
            $filename       = $requestData['filename'] ?? null;
            $fileIdentifier = $requestData['checksum'] ?? null;
            $operation      = $requestData['operation'] ?? null;
            $isAttachment   = (isset($requestData['subfolder']) and $requestData['subfolder'] === 'Anexos');
            if (!$this->isCsrfTokenValid('upd-accounting-file-' . $financialMonth->getId(), $requestData['_token'])) {
                throw new Exception('Invalid CSRF token');
            }

            $successMessage = 'No operation has been performed';
            switch($location) {
                case 'source':
                    // files in the source folder may not be manipulated directly by the AccountingFolderManager
                    // they must be updated via the AccountingDocumentService
                    switch($operation) {
                        case 'delete':
                            // determine the document from the checksum
                            $document = $accountingDocumentService->getDocumentByChecksum(
                                $financialMonth,
                                $fileIdentifier
                            );
                            $accountingDocumentService->deleteDocument($document);
                            $successMessage = 'The document has been deleted: ' . $document->getFilename();
                            break;

                        case 'sync':
                            if ('*' === $filename) {
                                $accountingFolderComparator = AccountingFolderComparator::create(
                                    $accountingFolderManager->getAccountingFolderPath($financialMonth, $isAttachment),
                                    $accountingFolderManager->getAccountantFolderPath($financialMonth, $isAttachment)
                                );

                                foreach ($accountingFolderComparator->getByContentChecksum() as $checksum => $data) {
                                    if (count($data['source']) === 1 and count($data['target']) === 0) {
                                        // the file exists only in the source folder
                                        $accountingFolderManager->syncAccountingFile(
                                            $financialMonth,
                                            $data['source'][0]->getName(),
                                            $isAttachment
                                        );
                                    }
                                }

                            } else {
                                $accountingFolderManager->syncAccountingFile($financialMonth, $filename, $isAttachment);
                            }

                            break;

                        case 'rename':
                            $newFilename = $requestData['newFilename'] ?? null;
                            if ($newFilename === $filename) {
                                throw new Exception('The new filename must be different from the old filename');
                            }

                            $document = $accountingDocumentService->getDocumentByChecksum(
                                $financialMonth,
                                $fileIdentifier
                            );
                            if (null === $document) {
                                throw new Exception('The document could not be found: ' . $fileIdentifier);
                            }
                            $accountingDocumentService->renameDocument($document, $newFilename);
                            $successMessage = 'The document has been renamed to: ' . $newFilename;

                            $em->flush();
                            break;
                        default:
                            throw new Exception('Operation not supported');
                    }
                    break;

                case 'target':
                    switch($operation) {
                        // files in the target folder can be manipulated directly by the AccountingFolderManager
                        case 'delete':
                            $accountingFolderManager->deleteAccountantFile($financialMonth, $filename, $isAttachment);
                            $successMessage = 'The document has been deleted: ' . $fileIdentifier;
                            break;
                        default:
                            throw new Exception('Operation not supported');
                    }
                    break;
            }

            $request->getSession()->getFlashBag()->add('success', $successMessage);
        } catch (Throwable $e) {
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }

        return new JsonResponse('ok');
    }
}