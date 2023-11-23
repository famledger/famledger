<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
            } catch (Exception $e) {
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
            $file        = $request->files->get('file');
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
}
