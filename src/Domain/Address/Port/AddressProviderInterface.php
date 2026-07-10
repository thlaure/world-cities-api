<?php

declare(strict_types=1);

namespace App\Domain\Address\Port;

use App\Domain\Address\Model\Address;
use App\Domain\Shared\Model\CountryCode;

interface AddressProviderInterface
{
    /**
     * Search for addresses matching a partial or full-text query.
     *
     * @return list<Address>
     */
    public function searchAddresses(string $query, ?CountryCode $countryCode, int $limit): array;
}
