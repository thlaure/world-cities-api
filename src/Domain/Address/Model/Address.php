<?php

declare(strict_types=1);

namespace App\Domain\Address\Model;

use App\Domain\Shared\Model\CountryCode;

final readonly class Address
{
    public function __construct(
        public string $label,
        public ?string $houseNumber,
        public ?string $street,
        public ?string $postalCode,
        public ?string $city,
        public ?CountryCode $countryCode,
        public float $latitude,
        public float $longitude,
    ) {
        if ('' === trim($label)) {
            throw new \InvalidArgumentException('Address label must not be empty.');
        }
    }
}
