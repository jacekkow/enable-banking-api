<?php

declare(strict_types=1);

namespace EnableBanking\Auth;

/**
 * Supplies the bearer token sent in the Authorization header of every API request.
 */
interface TokenProviderInterface
{
    public function getToken(): string;
}
