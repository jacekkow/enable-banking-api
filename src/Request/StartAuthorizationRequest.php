<?php

declare(strict_types=1);

namespace EnableBanking\Request;

use EnableBanking\Enum\PsuType;
use EnableBanking\Model\Aspsp;

/**
 * Body of POST /auth.
 *
 * @see https://enablebanking.com/docs/api/reference/#startauthorizationrequest
 */
final class StartAuthorizationRequest
{
    /**
     * @param string                     $state        Arbitrary string returned in the redirect URL query
     * @param string                     $redirectUrl  Must be one of the application's registered redirect URLs
     * @param array<string, string>|null $credentials  PSU credentials; requires $authMethod to be set
     * @param string|null                $language     Two-letter lowercase language code
     * @param string|null                $psuId        Application's (preferably anonymised) PSU identifier
     */
    public function __construct(
        public readonly Access $access,
        public readonly Aspsp $aspsp,
        public readonly string $state,
        public readonly string $redirectUrl,
        public readonly ?PsuType $psuType = null,
        public readonly ?string $authMethod = null,
        public readonly ?array $credentials = null,
        public readonly ?bool $credentialsAutosubmit = null,
        public readonly ?string $language = null,
        public readonly ?string $psuId = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'access' => $this->access->toArray(),
            'aspsp' => $this->aspsp->toArray(),
            'state' => $this->state,
            'redirect_url' => $this->redirectUrl,
            'psu_type' => $this->psuType?->value,
            'auth_method' => $this->authMethod,
            'credentials' => $this->credentials,
            'credentials_autosubmit' => $this->credentialsAutosubmit,
            'language' => $this->language,
            'psu_id' => $this->psuId,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
