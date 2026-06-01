<?php

namespace App\Services\Crm\Exceptions;

use RuntimeException;
use Throwable;

class ShopifyApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 0,
        ?Throwable $previous = null,
        private readonly ?string $responseBody = null
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }
}
