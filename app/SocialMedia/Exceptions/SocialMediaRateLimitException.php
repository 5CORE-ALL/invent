<?php

namespace App\SocialMedia\Exceptions;

class SocialMediaRateLimitException extends SocialMediaApiException
{
    public function __construct(string $message = 'The platform rate limit was reached. The sync will retry.', int $code = 429)
    {
        parent::__construct($message, $code);
    }
}
