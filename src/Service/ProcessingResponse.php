<?php

namespace App\Service;

class ProcessingResponse
{
    const TYPE_SUCCESS = 'success';
    const TYPE_ERROR   = 'error';

    private string $type;
    private mixed  $response;

    public function __construct(string $type, mixed $response)
    {
        $this->type     = $type;
        $this->response = $response;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getResponse(): mixed
    {
        return $this->response;
    }
}