<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Entity\EDoc;
use App\Entity\FileOwnerInterface;
use App\Service\EDocService;

class EDocController extends DashboardController
{
    #[Route('/admin/eDocs/{eDoc}', name: 'admin_eDoc_download', methods: ['GET'])]
    public function download(EDoc $eDoc, EDocService $eDocService): Response
    {
        $filePath = $eDocService->getEDocFilepath($eDoc);

        if (!file_exists($filePath)) {
            return new Response('File not found', Response::HTTP_NOT_FOUND);
        }

        return new BinaryFileResponse($filePath);
    }

    #[Route('/admin/eDocs/{eDoc}/copyToOutbox', name: 'admin_eDoc_outbox', methods: ['POST'])]
    public function copyToOutbox(EDoc $eDoc, EDocService $eDocService, string $outboxFolder): Response
    {
        $filePath = $eDocService->getEDocFilepath($eDoc);

        if (!file_exists($filePath)) {
            return new Response('File not found', Response::HTTP_NOT_FOUND);
        }

        copy($filePath, $outboxFolder . '/' . $eDoc->getFilename());

        return new Response($filePath);
    }

    #[Route('/admin/eDocs/{eDoc}', name: 'admin_eDoc_delete', methods: ['DELETE'])]
    public function delete(
        EDoc        $eDoc,
        Request     $request,
        EDocService $eDocService
    ): Response {

        $owner = $eDocService->getOwner($eDoc);
        $type  = $eDoc->getType();
        $token = $request->headers->get('X-CSRF-TOKEN');
        if ($this->isCsrfTokenValid('del-eDoc-' . $eDoc->getId(), $token)) {
            try {
                $eDocService->deleteEDoc($eDoc);
            } catch (Exception) {
            }
        }

        return new JsonResponse([
            'success'     => true,
            'updatedHTML' => $this->renderView('admin/EDoc/eDocsCardBody.html.twig', [
                'eDocs' => $eDocService->getEDocs($owner, $type),
                'type'  => $type,
                'owner' => $owner
            ])
        ]);
    }

    #[Route('/admin/eDocs/{ownerName}/{ownerId}/{type}', name: 'admin_eDoc_upload', methods: ['POST'])]
    public function upload(
        int                    $ownerId,
        string                 $ownerName,
        string                 $type,
        Request                $request,
        EntityManagerInterface $em,
        EDocService            $eDocService
    ): Response {
        try {
            $file = $request->files->get('file');

            if (!$file instanceof UploadedFile) {
                throw new Exception('File was not uploaded properly.');
            }

            if ($file->getError() !== UPLOAD_ERR_OK) {
                throw new FileException($this->getUploadErrorMessage($file->getError()));
            }
            $class       = "App\\Entity\\$ownerName";
            $ownerEntity = $em->getRepository($class)->find($ownerId);

            if (!$ownerEntity instanceof FileOwnerInterface) {
                return new JsonResponse('Invalid owner entity', Response::HTTP_BAD_REQUEST);
            }

            $eDocService->createAndPersistEDoc($ownerEntity, $file, $type);

            return $this->render('admin/EDoc/eDocsCardBody.html.twig', [
                'eDocs' => $eDocService->getEDocs($ownerEntity, $type),
                'type'  => $type,
                'owner' => $ownerEntity
            ]);
        } catch (Exception $e) {
            return new JsonResponse($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    private function getUploadErrorMessage($errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE   => 'The file is too large (server limit).',
            UPLOAD_ERR_FORM_SIZE  => 'The file is too large (form limit).',
            UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION  => 'File upload stopped by extension.',
            default               => 'Unknown upload error.',
        };
    }
}
