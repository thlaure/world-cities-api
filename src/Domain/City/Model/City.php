<?php

declare(strict_types=1);

namespace App\Domain\City\Model;

use App\Domain\Shared\Model\CountryCode;

final readonly class City
{
    public function __construct(
        public CountryCode $countryCode,
        public string $localCode,
        public string $name,
        public ?string $departmentCode,
        public ?string $regionCode,
        public ?string $postalCode,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $updatedAt,
    ) {
        if ('' === trim($localCode)) {
            throw new \InvalidArgumentException('City local code must not be empty.');
        }

        if ('' === trim($name)) {
            throw new \InvalidArgumentException('City name must not be empty.');
        }

        if (null !== $departmentCode && '' === trim($departmentCode)) {
            throw new \InvalidArgumentException('City department code must not be empty.');
        }

        if (null !== $regionCode && '' === trim($regionCode)) {
            throw new \InvalidArgumentException('City region code must not be empty.');
        }
    }
}
