<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Shared\Model\CountryCode;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity]
#[ORM\Table(name: 'cities')]
#[ORM\UniqueConstraint(name: 'uniq_cities_country_code_local_code', columns: ['country_code', 'local_code'])]
#[ORM\Index(name: 'idx_cities_country_code', columns: ['country_code'])]
#[ORM\Index(name: 'idx_cities_department_code', columns: ['department_code'])]
#[ORM\Index(name: 'idx_cities_region_code', columns: ['region_code'])]
#[ORM\Index(name: 'idx_cities_name', columns: ['name'])]
class City
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidV7 $id;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Column(type: Types::STRING, length: 2, enumType: CountryCode::class)]
        private CountryCode $countryCode,
        #[ORM\Column(type: Types::STRING, length: 10)]
        private string $localCode,
        #[ORM\Column(type: Types::STRING, length: 255)]
        private string $name,
        #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
        private ?string $departmentCode,
        #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
        private ?string $regionCode,
        #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
        private ?string $postalCode = null,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
        $this->id = new UuidV7();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): UuidV7
    {
        return $this->id;
    }

    public function getCountryCode(): CountryCode
    {
        return $this->countryCode;
    }

    public function getLocalCode(): string
    {
        return $this->localCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDepartmentCode(): ?string
    {
        return $this->departmentCode;
    }

    public function getRegionCode(): ?string
    {
        return $this->regionCode;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function updateFromDomainModel(
        string $name,
        ?string $departmentCode,
        ?string $regionCode,
        ?string $postalCode,
    ): void {
        $this->name = $name;
        $this->departmentCode = $departmentCode;
        $this->regionCode = $regionCode;
        $this->postalCode = $postalCode;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
