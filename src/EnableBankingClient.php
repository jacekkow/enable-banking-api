<?php

declare(strict_types=1);

namespace EnableBanking;

use DateTimeInterface;
use EnableBanking\Auth\JwtTokenProvider;
use EnableBanking\Auth\TokenProviderInterface;
use EnableBanking\Enum\PsuType;
use EnableBanking\Enum\Service;
use EnableBanking\Enum\TransactionsFetchStrategy;
use EnableBanking\Enum\TransactionStatus;
use EnableBanking\Exception\ApiException;
use EnableBanking\Exception\NetworkException;
use EnableBanking\Exception\UnexpectedResponseException;
use EnableBanking\Model\Account;
use EnableBanking\Model\Application;
use EnableBanking\Model\AspspData;
use EnableBanking\Model\AuthorizationStart;
use EnableBanking\Model\Balance;
use EnableBanking\Model\Data;
use EnableBanking\Model\Session;
use EnableBanking\Model\SessionData;
use EnableBanking\Model\Transaction;
use EnableBanking\Model\TransactionsPage;
use EnableBanking\Request\PsuHeaders;
use EnableBanking\Request\StartAuthorizationRequest;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Enable Banking API client for Account Information Services (AIS).
 *
 * Any PSR-18 HTTP client and PSR-17 factories can be injected; Guzzle is used by default.
 *
 * @see https://enablebanking.com/docs/api/reference/
 */
final class EnableBankingClient
{
    public const DEFAULT_BASE_URI = 'https://api.enablebanking.com';

    private readonly ClientInterface $httpClient;
    private readonly RequestFactoryInterface $requestFactory;
    private readonly StreamFactoryInterface $streamFactory;
    private readonly string $baseUri;

    public function __construct(
        private readonly TokenProviderInterface $tokenProvider,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        string $baseUri = self::DEFAULT_BASE_URI,
    ) {
        $this->httpClient = $httpClient ?? new GuzzleClient(['timeout' => 60, 'http_errors' => false]);
        $this->requestFactory = $requestFactory ?? new HttpFactory();
        $this->streamFactory = $streamFactory ?? new HttpFactory();
        $this->baseUri = rtrim($baseUri, '/');
    }

    /**
     * Convenience factory using the application ID and a PEM-encoded RSA private key.
     */
    public static function create(
        string $applicationId,
        #[\SensitiveParameter] string $privateKey,
        ?ClientInterface $httpClient = null,
    ): self {
        return new self(new JwtTokenProvider($applicationId, $privateKey), $httpClient);
    }

    /**
     * GET /application – details of the application associated with the JWT key ID.
     */
    public function getApplication(): Application
    {
        return Application::fromArray($this->request('GET', '/application'));
    }

    /**
     * GET /aspsps – list of available ASPSPs (banks).
     *
     * @return list<AspspData>
     */
    public function getAspsps(?string $country = null, ?PsuType $psuType = null, ?Service $service = Service::AIS): array
    {
        $data = $this->request('GET', '/aspsps', [
            'country' => $country,
            'psu_type' => $psuType?->value,
            'service' => $service?->value,
        ]);

        return array_map(AspspData::fromArray(...), Data::arrayList($data, 'aspsps'));
    }

    /**
     * POST /auth – starts user authorization; redirect the PSU to the returned URL.
     */
    public function startAuthorization(StartAuthorizationRequest $request): AuthorizationStart
    {
        return AuthorizationStart::fromArray($this->request('POST', '/auth', body: $request->toArray()));
    }

    /**
     * POST /sessions – creates a user session using the "code" query parameter received on the redirect URL.
     */
    public function authorizeSession(string $code): Session
    {
        return Session::fromArray($this->request('POST', '/sessions', body: ['code' => $code]));
    }

    /**
     * GET /sessions/{session_id}
     */
    public function getSession(string $sessionId): SessionData
    {
        return SessionData::fromArray($this->request('GET', '/sessions/' . rawurlencode($sessionId)));
    }

    /**
     * DELETE /sessions/{session_id} – closes the session (and the bank consent, if possible).
     */
    public function deleteSession(string $sessionId, ?PsuHeaders $psuHeaders = null): void
    {
        $this->request('DELETE', '/sessions/' . rawurlencode($sessionId), psuHeaders: $psuHeaders);
    }

    /**
     * GET /accounts/{account_id}/details
     */
    public function getAccountDetails(string $accountId, ?PsuHeaders $psuHeaders = null): Account
    {
        return Account::fromArray($this->request(
            'GET',
            '/accounts/' . rawurlencode($accountId) . '/details',
            psuHeaders: $psuHeaders,
        ));
    }

    /**
     * GET /accounts/{account_id}/balances
     *
     * @return list<Balance>
     */
    public function getAccountBalances(string $accountId, ?PsuHeaders $psuHeaders = null): array
    {
        $data = $this->request('GET', '/accounts/' . rawurlencode($accountId) . '/balances', psuHeaders: $psuHeaders);

        return array_map(Balance::fromArray(...), Data::arrayList($data, 'balances'));
    }

    /**
     * GET /accounts/{account_id}/transactions – fetches a single page of transactions.
     *
     * Note that a page may be empty even though more pages follow; use {@see self::getAllAccountTransactions()}
     * to iterate over every page.
     */
    public function getAccountTransactions(
        string $accountId,
        ?DateTimeInterface $dateFrom = null,
        ?DateTimeInterface $dateTo = null,
        ?string $continuationKey = null,
        ?TransactionStatus $transactionStatus = null,
        ?TransactionsFetchStrategy $strategy = null,
        ?PsuHeaders $psuHeaders = null,
    ): TransactionsPage {
        $data = $this->request('GET', '/accounts/' . rawurlencode($accountId) . '/transactions', [
            'date_from' => $dateFrom?->format('Y-m-d'),
            'date_to' => $dateTo?->format('Y-m-d'),
            'continuation_key' => $continuationKey,
            'transaction_status' => $transactionStatus?->value,
            'strategy' => $strategy?->value,
        ], psuHeaders: $psuHeaders);

        return TransactionsPage::fromArray($data);
    }

    /**
     * Fetches transactions lazily, following continuation keys until all pages are retrieved.
     *
     * @return \Generator<int, Transaction>
     */
    public function getAllAccountTransactions(
        string $accountId,
        ?DateTimeInterface $dateFrom = null,
        ?DateTimeInterface $dateTo = null,
        ?TransactionStatus $transactionStatus = null,
        ?TransactionsFetchStrategy $strategy = null,
        ?PsuHeaders $psuHeaders = null,
    ): \Generator {
        $continuationKey = null;
        do {
            $page = $this->getAccountTransactions(
                $accountId,
                $dateFrom,
                $dateTo,
                $continuationKey,
                $transactionStatus,
                $strategy,
                $psuHeaders,
            );
            foreach ($page->transactions as $transaction) {
                yield $transaction;
            }
            $continuationKey = $page->continuationKey;
        } while ($continuationKey !== null);
    }

    /**
     * GET /accounts/{account_id}/transactions/{transaction_id}
     */
    public function getTransaction(string $accountId, string $transactionId, ?PsuHeaders $psuHeaders = null): Transaction
    {
        return Transaction::fromArray($this->request(
            'GET',
            '/accounts/' . rawurlencode($accountId) . '/transactions/' . rawurlencode($transactionId),
            psuHeaders: $psuHeaders,
        ));
    }

    /**
     * Sends an authenticated request and returns the decoded JSON body.
     *
     * Can be used to call endpoints not (yet) wrapped by this client.
     *
     * @param array<string, string|int|null> $query  Null values are skipped
     * @param array<string, mixed>|null      $body   Sent as JSON
     *
     * @return array<array-key, mixed>
     *
     * @throws ApiException                 on non-2xx responses
     * @throws NetworkException             when the request could not be sent
     * @throws UnexpectedResponseException  when the response body is not a JSON object
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        ?PsuHeaders $psuHeaders = null,
    ): array {
        $uri = $this->baseUri . '/' . ltrim($path, '/');
        $query = array_filter($query, static fn (mixed $v): bool => $v !== null);
        if ($query !== []) {
            $uri .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $request = $this->requestFactory->createRequest($method, $uri)
            ->withHeader('Authorization', 'Bearer ' . $this->tokenProvider->getToken())
            ->withHeader('Accept', 'application/json');

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)));
        }

        foreach ($psuHeaders?->toHeaders() ?? [] as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException(sprintf('%s %s failed: %s', $method, $path, $e->getMessage()), 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            throw ApiException::fromResponse($response);
        }

        $contents = (string) $response->getBody();
        if ($contents === '') {
            return [];
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new UnexpectedResponseException('Unable to decode API response: ' . $e->getMessage(), $response, $e);
        }
        if (!is_array($data)) {
            throw new UnexpectedResponseException('API response is not a JSON object.', $response);
        }

        return $data;
    }
}
