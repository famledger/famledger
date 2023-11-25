<?php

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

use App\Entity\Document;
use App\Repository\DocumentRepository;
use App\Service\ChecksumHelper;
use App\Service\DocumentDetector\DocumentLoader;
use App\Service\DocumentService;

#[Route('/admin/document')]
class DocumentController extends AbstractController
{
    /**
     * @throws Exception
     */
    #[Route('/detect', name: 'admin_document_detect', methods: ['GET', 'POST'])]
    public function detect(
        Request            $request,
        DocumentLoader     $documentLoader,
        DocumentRepository $documentRepository,
        AdminUrlGenerator  $adminUrlGenerator
    ): Response {
        $documentSpecs = null;

        if ($request->isMethod('POST')) {
            $uploadedFile = $request->files->get('file');
            if ($uploadedFile instanceof UploadedFile) {
                $document = null;
                try {
                    $documentSpecs = $documentLoader->load(
                        $uploadedFile->getRealPath(),
                        $uploadedFile->getClientOriginalExtension(),
                        $uploadedFile->getClientOriginalName()
                    );

                    $checksum = ChecksumHelper::get(file_get_contents($uploadedFile->getRealPath()));
                    $document = $documentRepository->findByChecksum($checksum);

                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }

                return $this->render('admin/Document/protocol.html.twig', [
                    'error'         => $error ?? null,
                    'filename'      => $uploadedFile->getClientOriginalName(),
                    'protocol'      => $documentLoader->getDetectionProtocol()->getProtocol(),
                    'documentSpecs' => $documentSpecs?->serialize(),
                    'document'      => $document
                ]);
            }
        }

        return $this->render('admin/Document/detect.html.twig', [
            'protocol'      => null,
            'postUrl'       => $adminUrlGenerator
                ->setController(DocumentController::class)
                ->setAction('detect')
                ->generateUrl(),
            'documentSpecs' => $documentSpecs,
        ]);
    }

    /**
     * @throws Exception
     */
    #[Route('/download/{document}', name: 'admin_document_download')]
    public function download(Document $document, DocumentService $documentService): Response
    {
        $filePath = $documentService->getAccountingFilepath($document);
        $mime     = mime_content_type($filePath); // Get the MIME type of the file

        $response = new StreamedResponse(function () use ($filePath) {
            $fileStream   = fopen($filePath, 'rb');
            $outputStream = fopen('php://output', 'wb');
            stream_copy_to_stream($fileStream, $outputStream);
            fclose($fileStream);
        });

        $response->headers->set('Content-Type', $mime);
        $response->headers->set('Content-Disposition', 'inline; filename="' . $document->getFilename() . '"');

        return $response;
    }

    /**
     * @throws Exception
     */
    #[Route('/delete/{document}', name: 'admin_document_delete')]
    public function delete(Document $document, DocumentService $documentService): Response
    {
        $documentService->removeDocument($document);
    }
}
