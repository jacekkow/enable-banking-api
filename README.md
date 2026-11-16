# Enable Banking API client for PHP

PHP client for the Account Information Service (AIS) part of the
[Enable Banking API](https://enablebanking.com/docs/api/reference/).

* Works with any [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and
  [PSR-17](https://www.php-fig.org/psr/psr-17/) factories. Guzzle is used by default.
* JWTs are signed with [firebase/php-jwt](https://github.com/firebase/php-jwt) and reused until shortly before they expire.
* Responses are mapped to typed, read-only models. Each model also keeps the raw payload in `$raw`.

## Installation

```bash
composer require jacekkow/enable-banking-api
```

Requires PHP 8.1+ with the `openssl` and `json` extensions.

## Usage

```php
use EnableBanking\Auth\JwtTokenProvider;
use EnableBanking\EnableBankingClient;
use EnableBanking\Enum\PsuType;
use EnableBanking\Model\Aspsp;
use EnableBanking\Request\Access;
use EnableBanking\Request\PsuHeaders;
use EnableBanking\Request\StartAuthorizationRequest;

$client = new EnableBankingClient(
    JwtTokenProvider::fromFile('your-application-id', '/path/to/private.pem'),
);
// or: EnableBankingClient::create($applicationId, $pemString);

// 1. Pick a bank
$aspsps = $client->getAspsps(country: 'FI', psuType: PsuType::Personal);

// 2. Start authorization and redirect the user
$auth = $client->startAuthorization(new StartAuthorizationRequest(
    access: new Access(validUntil: new DateTimeImmutable('+90 days')),
    aspsp: new Aspsp('Nordea', 'FI'),
    state: $csrfState,
    redirectUrl: 'https://example.com/enable-banking/callback',
    psuType: PsuType::Personal,
));
header('Location: ' . $auth->url);

// 3. On the callback, check $_GET['state'] and exchange the code for a session
$session = $client->authorizeSession($_GET['code']);

// 4. Read account data
foreach ($session->accounts as $account) {
    $balances = $client->getAccountBalances($account->uid);

    // Follows continuation keys automatically
    foreach ($client->getAllAccountTransactions($account->uid, dateFrom: new DateTimeImmutable('-30 days')) as $tx) {
        echo $tx->bookingDate?->format('Y-m-d'), ' ', $tx->transactionAmount->amount, PHP_EOL;
    }
}

// Later: session status and closing
$client->getSession($session->sessionId)->isAuthorized();
$client->deleteSession($session->sessionId);
```

### Available methods

| Method | Endpoint |
| --- | --- |
| `getApplication()` | `GET /application` |
| `getAspsps(?country, ?psuType, ?service = AIS)` | `GET /aspsps` |
| `startAuthorization(StartAuthorizationRequest)` | `POST /auth` |
| `authorizeSession(code)` | `POST /sessions` |
| `getSession(sessionId)` | `GET /sessions/{id}` |
| `deleteSession(sessionId, ?PsuHeaders)` | `DELETE /sessions/{id}` |
| `getAccountDetails(accountId, ?PsuHeaders)` | `GET /accounts/{id}/details` |
| `getAccountBalances(accountId, ?PsuHeaders)` | `GET /accounts/{id}/balances` |
| `getAccountTransactions(accountId, ...)` | `GET /accounts/{id}/transactions` (one page) |
| `getAllAccountTransactions(accountId, ...)` | same, as a generator that follows all pages |
| `getTransaction(accountId, transactionId, ?PsuHeaders)` | `GET /accounts/{id}/transactions/{tx}` |
| `request(method, path, query, body, ?PsuHeaders)` | any other endpoint, returns the decoded array |

### PSU headers

Some banks require PSU context headers (see `AspspData::$requiredPsuHeaders`) when the user is present:

```php
$client->getAccountBalances($accountId, PsuHeaders::fromGlobals());
```

### Custom HTTP client

```php
$client = new EnableBankingClient(
    $tokenProvider,
    $anyPsr18Client,      // Psr\Http\Client\ClientInterface
    $requestFactory,      // Psr\Http\Message\RequestFactoryInterface
    $streamFactory,       // Psr\Http\Message\StreamFactoryInterface
);
```

### Errors

Every exception implements `EnableBanking\Exception\EnableBankingException`:

* `ApiException`: the API returned a non-2xx status. It has `$statusCode`, `$error` (e.g. `EXPIRED_SESSION`), `$detail` and `$response`.
* `NetworkException`: the request could not be sent.
* `UnexpectedResponseException`: the response body could not be parsed.
* `InvalidArgumentException`: the configuration is invalid, for example an unreadable key or a TTL over 24 hours.

## Example

`examples/ais.php` runs the full interactive flow, like the
[official PHP sample](https://github.com/enablebanking/enablebanking-api-samples/blob/master/php_example/enablebanking.php):

```bash
cp examples/config.json.dist examples/config.json   # fill in application ID and key path
php examples/ais.php
```

## Development

```bash
composer test
composer analyse
```
