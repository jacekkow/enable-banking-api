<?php

declare(strict_types=1);

namespace EnableBanking\Exception;

use Psr\Http\Message\ResponseInterface;

/**
 * The API returned a successful status, but the body could not be interpreted.
 */
final class UnexpectedResponseException extends \RuntimeException implements EnableBankingException
{
    public function __construct(
        string $message,
        public readonly ?ResponseInterface $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
