<?php

declare(strict_types=1);

namespace EnableBanking\Exception;

/**
 * The HTTP request could not be completed (connection error, timeout, ...).
 */
final class NetworkException extends \RuntimeException implements EnableBankingException
{
}
