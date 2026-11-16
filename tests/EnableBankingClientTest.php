<?php

declare(strict_types=1);

namespace EnableBanking\Tests;

use DateTimeImmutable;
use EnableBanking\Auth\TokenProviderInterface;
use EnableBanking\EnableBankingClient;
use EnableBanking\Enum\PsuType;
use EnableBanking\Enum\SessionStatus;
use EnableBanking\Enum\TransactionStatus;
use EnableBanking\Exception\ApiException;
use EnableBanking\Exception\NetworkException;
use EnableBanking\Model\Aspsp;
use EnableBanking\Request\Access;
use EnableBanking\Request\PsuHeaders;
use EnableBanking\Request\StartAuthorizationRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class EnableBankingClientTest extends TestCase
{
    private MockHandler $mock;
    /** @var list<array{request: RequestInterface}> */
    private array $history = [];
    private EnableBankingClient $client;

    protected function setUp(): void
    {
        $this->mock = new MockHandler();
        $this->history = [];
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        $tokens = new class () implements TokenProviderInterface {
            public function getToken(): string
            {
                return 'test-token';
            }
        };
        $this->client = new EnableBankingClient($tokens, new Client(['handler' => $stack]));
    }

    private function queueJson(array $data, int $status = 200): void
    {
        $this->mock->append(new Response($status, ['Content-Type' => 'application/json'], json_encode($data)));
    }

    private function lastRequest(): RequestInterface
    {
        return $this->history[array_key_last($this->history)]['request'];
    }

    public function testGetApplication(): void
    {
        $this->queueJson([
            'name' => 'My app',
            'kid' => 'abc',
            'environment' => 'SANDBOX',
            'redirect_urls' => ['https://example.com/cb'],
            'active' => true,
            'countries' => ['FI'],
            'services' => ['AIS'],
        ]);

        $app = $this->client->getApplication();

        self::assertSame('My app', $app->name);
        self::assertSame(['https://example.com/cb'], $app->redirectUrls);
        self::assertTrue($app->active);

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('https://api.enablebanking.com/application', (string) $request->getUri());
        self::assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
    }

    public function testGetAspsps(): void
    {
        $this->queueJson(['aspsps' => [[
            'name' => 'Nordea',
            'country' => 'FI',
            'logo' => 'https://enablebanking.com/brands/FI/Nordea/',
            'bic' => 'NDEAFIHH',
            'psu_types' => ['business', 'personal'],
            'auth_methods' => [['name' => 'MTA', 'approach' => 'REDIRECT']],
            'maximum_consent_validity' => 15552000,
            'beta' => false,
            'required_psu_headers' => ['Psu-Ip-Address'],
        ]]]);

        $aspsps = $this->client->getAspsps('FI', PsuType::Personal);

        self::assertCount(1, $aspsps);
        self::assertSame('Nordea', $aspsps[0]->name);
        self::assertSame([PsuType::Business, PsuType::Personal], $aspsps[0]->psuTypes);
        self::assertSame(15552000, $aspsps[0]->maximumConsentValidity);
        self::assertSame(['Psu-Ip-Address'], $aspsps[0]->requiredPsuHeaders);
        self::assertSame(['name' => 'Nordea', 'country' => 'FI'], $aspsps[0]->toAspsp()->toArray());
        self::assertSame(
            'https://api.enablebanking.com/aspsps?country=FI&psu_type=personal&service=AIS',
            (string) $this->lastRequest()->getUri(),
        );
    }

    public function testStartAuthorization(): void
    {
        $this->queueJson([
            'url' => 'https://auth.enablebanking.com/ais/start?sessionid=73100c65',
            'authorization_id' => '73100c65',
            'psu_id_hash' => 'hash',
        ]);

        $result = $this->client->startAuthorization(new StartAuthorizationRequest(
            new Access(new DateTimeImmutable('2030-01-01T12:00:00+00:00')),
            new Aspsp('Nordea', 'FI'),
            'state-1',
            'https://example.com/cb',
            PsuType::Personal,
        ));

        self::assertSame('73100c65', $result->authorizationId);
        self::assertStringStartsWith('https://auth.enablebanking.com/', $result->url);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame([
            'access' => ['valid_until' => '2030-01-01T12:00:00+00:00'],
            'aspsp' => ['name' => 'Nordea', 'country' => 'FI'],
            'state' => 'state-1',
            'redirect_url' => 'https://example.com/cb',
            'psu_type' => 'personal',
        ], json_decode((string) $request->getBody(), true));
    }

    public function testAuthorizeSession(): void
    {
        $this->queueJson([
            'session_id' => 'sess-1',
            'accounts' => [[
                'uid' => 'acc-1',
                'account_id' => ['iban' => 'FI0455231152453547'],
                'all_account_ids' => [['identification' => '123456', 'scheme_name' => 'BBAN']],
                'currency' => 'EUR',
                'credit_limit' => ['currency' => 'EUR', 'amount' => '100.00'],
            ]],
            'aspsp' => ['name' => 'Nordea', 'country' => 'FI'],
            'psu_type' => 'personal',
            'access' => ['valid_until' => '2030-01-01T12:00:00.000000+00:00'],
        ]);

        $session = $this->client->authorizeSession('the-code');

        self::assertSame('sess-1', $session->sessionId);
        self::assertSame('acc-1', $session->accounts[0]->uid);
        self::assertSame('FI0455231152453547', $session->accounts[0]->accountId?->iban);
        self::assertSame('BBAN', $session->accounts[0]->allAccountIds[0]->schemeName);
        self::assertSame('100.00', $session->accounts[0]->creditLimit?->amount);
        self::assertSame(PsuType::Personal, $session->psuType);
        self::assertSame('2030-01-01', $session->validUntil?->format('Y-m-d'));
        self::assertSame(['code' => 'the-code'], json_decode((string) $this->lastRequest()->getBody(), true));
    }

    public function testGetAndDeleteSession(): void
    {
        $this->queueJson([
            'status' => 'AUTHORIZED',
            'accounts' => ['acc-1'],
            'aspsp' => ['name' => 'Nordea', 'country' => 'FI'],
            'psu_type' => 'business',
            'access' => ['valid_until' => '2030-01-01T12:00:00.000000+00:00'],
            'created' => '2020-12-01T12:00:00.000000+00:00',
        ]);
        $this->queueJson(['message' => 'OK']);

        $session = $this->client->getSession('sess-1');
        self::assertTrue($session->isAuthorized());
        self::assertSame(SessionStatus::AUTHORIZED, $session->getStatus());
        self::assertSame(['acc-1'], $session->accounts);
        self::assertNull($session->authorized);

        $this->client->deleteSession('sess-1', new PsuHeaders(ipAddress: '10.0.0.1'));
        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('/sessions/sess-1', $request->getUri()->getPath());
        self::assertSame('10.0.0.1', $request->getHeaderLine('Psu-Ip-Address'));
    }

    public function testGetAccountBalances(): void
    {
        $this->queueJson(['balances' => [[
            'name' => 'Booked balance',
            'balance_amount' => ['currency' => 'EUR', 'amount' => '1.23'],
            'balance_type' => 'CLBD',
            'reference_date' => '2024-08-24',
        ]]]);

        $balances = $this->client->getAccountBalances('acc-1');

        self::assertCount(1, $balances);
        self::assertSame('1.23', $balances[0]->balanceAmount->amount);
        self::assertSame('CLBD', $balances[0]->balanceType);
        self::assertSame('2024-08-24', $balances[0]->referenceDate?->format('Y-m-d'));
        self::assertSame('/accounts/acc-1/balances', $this->lastRequest()->getUri()->getPath());
    }

    public function testGetAllAccountTransactionsFollowsContinuationKey(): void
    {
        $tx = static fn (string $id, string $cdi): array => [
            'transaction_id' => $id,
            'transaction_amount' => ['currency' => 'EUR', 'amount' => '10.00'],
            'credit_debit_indicator' => $cdi,
            'status' => 'BOOK',
            'booking_date' => '2024-01-03',
            'creditor' => ['name' => 'Shop'],
            'remittance_information' => ['Invoice 1'],
        ];
        $this->queueJson(['transactions' => [$tx('t1', 'DBIT')], 'continuation_key' => 'next/page+1']);
        $this->queueJson(['transactions' => [], 'continuation_key' => 'page-3']);
        $this->queueJson(['transactions' => [$tx('t2', 'CRDT')], 'continuation_key' => null]);

        $transactions = iterator_to_array($this->client->getAllAccountTransactions(
            'acc-1',
            new DateTimeImmutable('2024-01-01'),
            transactionStatus: TransactionStatus::BOOK,
        ), false);

        self::assertSame(['t1', 't2'], array_map(static fn ($t) => $t->transactionId, $transactions));
        self::assertTrue($transactions[0]->isDebit());
        self::assertTrue($transactions[1]->isCredit());
        self::assertSame('Shop', $transactions[0]->creditor?->name);
        self::assertSame(['Invoice 1'], $transactions[0]->remittanceInformation);
        self::assertSame(TransactionStatus::BOOK, $transactions[0]->getStatus());

        self::assertCount(3, $this->history);
        self::assertSame(
            'date_from=2024-01-01&transaction_status=BOOK',
            $this->history[0]['request']->getUri()->getQuery(),
        );
        self::assertSame(
            'date_from=2024-01-01&continuation_key=next%2Fpage%2B1&transaction_status=BOOK',
            $this->history[1]['request']->getUri()->getQuery(),
        );
    }

    public function testApiErrorIsMappedToException(): void
    {
        $this->queueJson([
            'message' => 'Required PSU header is not provided',
            'code' => 422,
            'error' => 'ACCESS_DENIED',
            'detail' => 'PSU header psuIpAddress is not provided',
        ], 422);

        try {
            $this->client->getAccountDetails('acc-1');
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(422, $e->statusCode);
            self::assertSame(422, $e->getCode());
            self::assertSame('ACCESS_DENIED', $e->error);
            self::assertSame('PSU header psuIpAddress is not provided', $e->detail);
            self::assertStringContainsString('Required PSU header is not provided', $e->getMessage());
        }
    }

    public function testNetworkErrorIsWrapped(): void
    {
        $this->mock->append(new ConnectException('Connection refused', new Request('GET', '/')));

        $this->expectException(NetworkException::class);
        $this->client->getApplication();
    }

    public function testPsuHeadersFromGlobals(): void
    {
        $headers = PsuHeaders::fromGlobals([
            'REMOTE_ADDR' => '192.0.2.1',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
            'HTTP_ACCEPT_LANGUAGE' => 'pl',
        ]);

        self::assertSame([
            'Psu-Ip-Address' => '192.0.2.1',
            'Psu-User-Agent' => 'Mozilla/5.0',
            'Psu-Accept-Language' => 'pl',
        ], $headers->toHeaders());
    }
}
