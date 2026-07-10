<?php

declare(strict_types=1);

namespace App\Tests\Fake;

use App\Domain\Address\Exception\AddressProviderException;
use App\Domain\Address\Model\Address;
use App\Domain\Address\Port\AddressProviderInterface;
use App\Domain\Shared\Model\CountryCode;

final class FakeAddressProvider implements AddressProviderInterface
{
    /** @var list<Address> */
    private array $addresses = [];

    private bool $shouldThrow = false;

    /**
     * @return list<Address>
     */
    public function searchAddresses(string $query, ?CountryCode $countryCode, int $limit): array
    {
        if ($this->shouldThrow) {
            throw AddressProviderException::fromPrevious(new \RuntimeException('Simulated provider failure.'));
        }

        $addresses = $countryCode instanceof CountryCode
            ? array_values(array_filter($this->addresses, static fn (Address $address): bool => $countryCode === $address->countryCode))
            : $this->addresses;

        return array_slice($addresses, 0, $limit);
    }

    /**
     * @param list<Address> $addresses
     */
    public function setAddresses(array $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function simulateFailure(bool $shouldThrow = true): void
    {
        $this->shouldThrow = $shouldThrow;
    }

    public function reset(): void
    {
        $this->addresses = [];
        $this->shouldThrow = false;
    }
}
