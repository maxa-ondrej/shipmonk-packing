<?php

namespace App\Error;

use Exception;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

class HttpException extends Exception
{

    public function __construct(
        private int $httpCode,
        string $message,
    )
    {
        parent::__construct($message, $httpCode);
    }

    public function toResponse(): ResponseInterface
    {
        return new Response($this->httpCode, body: $this->message);
    }

}
