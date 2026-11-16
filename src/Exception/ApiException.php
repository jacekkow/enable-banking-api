<?php

declare(strict_types=1);

namespace EnableBanking\Exception;

use Psr\Http\Message\ResponseInterface;

/**
 * The API responded with a non-2xx status code.
 *
 * @see https://enablebanking.com/docs/api/reference/#errorresponse
 */
final class ApiException extends \RuntimeException implements EnableBankingException
{
    /**
     * @param int         $statusCode HTTP status code
     * @param string|null $error      Text error code, e.g. "ACCESS_DENIED" or "EXPIRED_SESSION"
     * @param mixed       $detail     Detailed explanation of the error, as returned by the API
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly ?string $error,
        public readonly mixed $detail,
        public readonly ResponseInterface $response,
    ) {
        parent::__construct($message, $statusCode);
    }

    public static function fromResponse(ResponseInterface $response): self
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (is_array($data)) {
            $message = is_string($data['message'] ?? null) ? $data['message'] : $response->getReasonPhrase();
            $error = is_string($data['error'] ?? null) ? $data['error'] : null;
            $detail = $data['detail'] ?? null;
        } else {
            $message = $body !== '' ? $body : $response->getReasonPhrase();
            $error = null;
            $detail = null;
        }

        return new self(
            sprintf('Enable Banking API error %d%s: %s', $status, $error !== null ? ' ' . $error : '', $message),
            $status,
            $error,
            $detail,
            $response,
        );
    }
}
