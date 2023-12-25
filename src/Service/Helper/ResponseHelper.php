<?php

namespace App\Service\Helper;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ResponseHelper
{
    static public function createPdfResponse(string $filePath, string $filename): Response
    {
        if (!file_exists($filePath)) {
            throw new NotFoundHttpException('The file does not exist');
        }
        $response = new StreamedResponse(function () use ($filePath) {
            $fileStream   = fopen($filePath, 'rb');
            $outputStream = fopen('php://output', 'wb');
            stream_copy_to_stream($fileStream, $outputStream);
            fclose($fileStream); // Don't forget to close the resource handle!
        });
        // Set headers for showing the file in browser
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');

        return $response;
    }

}