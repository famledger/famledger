<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Throwable;

use App\Service\Helper\ResponseHelper;
use App\Service\InboxFileManager;
use App\Service\InboxHandler;

class InboxController extends AbstractController
{
    #[Route('/admin/inboxes', name: 'admin_inbox', methods: ['GET'])]
    public function index(InboxFileManager $inboxFileManager): Response
    {
        return $this->render('admin/Inbox/index.html.twig', [
            'files' => $inboxFileManager->getFiles(),
        ]);
    }

    #[Route('/admin/inboxes/process', name: 'admin_inbox_process', methods: ['GET'])]
    public function process(
        Request          $request,
        InboxHandler     $inboxHandler,
        InboxFileManager $inboxFileManager
    ): Response {
        $report = [];
        try {
            $report = $inboxHandler->processFiles();
        } catch (Throwable $e) {
            $request->getSession()->getFlashBag()->add('error', $e->getMessage());
        }

        return $this->render('admin/Inbox/report.html.twig', [
            'files'  => $inboxFileManager->getFiles(),
            'report' => $report,
        ]);
    }

    #[Route('/admin/inbox/{filename}', name: 'admin_inbox_download')]
    public function download(string $filename, InboxFileManager $inboxFileManager): Response
    {
        $filePath = sprintf('%s/%s',
            $inboxFileManager->getInboxFolderPath(),
            $filename
        );

        return ResponseHelper::createPdfResponse($filePath, $filename);
    }

    #[Route('/admin/inbox/{filename}/delete', name: 'admin_inbox_delete')]
    public function delete(string $filename, Request $request, InboxFileManager $inboxFileManager): Response
    {
        $filePath = sprintf('%s/%s',
            $inboxFileManager->getInboxFolderPath(),
            $filename
        );
        if (!file_exists($filePath)) {
            $request->getSession()->getFlashBag()->add('error', 'The file does not exist');
        }
        $inboxFileManager->deleteFile($filename);

        return $this->redirect($this->generateUrl('admin', ['routeName' => 'admin_inbox']));
    }
}