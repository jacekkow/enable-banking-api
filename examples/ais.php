<?php

/**
 * Interactive Account Information flow, equivalent to the official PHP sample.
 *
 * Usage: copy config.json.dist to config.json, fill it in and run `php examples/ais.php`.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use EnableBanking\Auth\JwtTokenProvider;
use EnableBanking\EnableBankingClient;
use EnableBanking\Enum\PsuType;
use EnableBanking\Exception\EnableBankingException;
use EnableBanking\Request\Access;
use EnableBanking\Request\StartAuthorizationRequest;

$config = json_decode((string) file_get_contents(__DIR__ . '/config.json'), true, 512, JSON_THROW_ON_ERROR);

$client = new EnableBankingClient(
    JwtTokenProvider::fromFile($config['applicationId'], $config['keyPath']),
);

try {
    $app = $client->getApplication();
    echo "Application: {$app->name} ({$app->environment})\n";

    $country = strtoupper(trim((string) readline('Country code (empty for all): ')));
    $aspsps = $client->getAspsps($country !== '' ? $country : null);
    if ($aspsps === []) {
        throw new RuntimeException('No ASPSPs available' . ($country !== '' ? " in {$country}" : '') . '.');
    }

    echo "Available ASPSPs:\n";
    foreach ($aspsps as $i => $candidate) {
        printf("  %3d. %s (%s)%s\n", $i + 1, $candidate->name, $candidate->country, $candidate->beta ? ' [beta]' : '');
    }
    do {
        $input = readline(sprintf('Select ASPSP [1-%d]: ', count($aspsps)));
        if ($input === false) {
            throw new RuntimeException('No ASPSP selected.');
        }
        $choice = (int) $input;
    } while (!isset($aspsps[$choice - 1]));
    $aspsp = $aspsps[$choice - 1];

    // Ask for the PSU type only when the ASPSP supports more than one
    $psuTypes = $aspsp->psuTypes !== [] ? $aspsp->psuTypes : PsuType::cases();
    if (count($psuTypes) === 1) {
        $psuType = $psuTypes[0];
    } else {
        $labels = array_map(static fn (PsuType $t): string => $t->value, $psuTypes);
        do {
            $input = readline(sprintf('PSU type [%s]: ', implode('/', $labels)));
            if ($input === false) {
                throw new RuntimeException('No PSU type selected.');
            }
            $psuType = PsuType::tryFrom(strtolower(trim($input)));
        } while ($psuType === null || !in_array($psuType, $psuTypes, true));
    }
    echo "Using PSU type: {$psuType->value}\n";

    // Consent validity must not exceed the maximum supported by the ASPSP
    $validity = min(10 * 86400, $aspsp->maximumConsentValidity ?? PHP_INT_MAX);

    $auth = $client->startAuthorization(new StartAuthorizationRequest(
        access: new Access(new DateTimeImmutable("+{$validity} seconds")),
        aspsp: $aspsp->toAspsp(),
        state: bin2hex(random_bytes(16)),
        redirectUrl: $app->redirectUrls[0],
        psuType: $psuType,
    ));
    echo "To authenticate open URL {$auth->url}\n";

    $code = readline('Enter value of code parameter from the URL you were redirected to: ');
    $session = $client->authorizeSession((string) $code);
    echo "New user session {$session->sessionId} with " . count($session->accounts) . " account(s)\n";

    foreach ($session->accounts as $account) {
        if ($account->uid === null) {
            continue;
        }
        echo "\nAccount {$account->uid} ({$account->accountId?->iban})\n";

        foreach ($client->getAccountBalances($account->uid) as $balance) {
            printf("  Balance %s: %s %s\n", $balance->balanceType, $balance->balanceAmount->amount, $balance->balanceAmount->currency);
        }

        foreach ($client->getAllAccountTransactions($account->uid, new DateTimeImmutable('-30 days')) as $tx) {
            printf(
                "  %s %s%s %s  %s\n",
                $tx->bookingDate?->format('Y-m-d') ?? '----------',
                $tx->isDebit() ? '-' : '+',
                $tx->transactionAmount->amount,
                $tx->transactionAmount->currency,
                implode(' ', $tx->remittanceInformation),
            );
        }
    }
} catch (EnableBankingException|RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
