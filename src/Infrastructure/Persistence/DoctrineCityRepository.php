<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\City\Model\City as DomainCity;
use App\Domain\City\Port\CityRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\UuidV7;

final readonly class DoctrineCityRepository implements CityRepositoryInterface
{
    private Connection $connection;

    public function __construct(
        EntityManagerInterface $entityManager,
    ) {
        $this->connection = $entityManager->getConnection();
    }

    public function save(DomainCity $city): bool
    {
        $isNew = $this->connection->fetchOne(
            <<<'SQL'
                INSERT INTO cities (id, country_code, local_code, name, department_code, region_code, postal_code, created_at, updated_at)
                VALUES (:id, :country_code, :local_code, :name, :department_code, :region_code, :postal_code, :created_at, :updated_at)
                ON CONFLICT (country_code, local_code) DO UPDATE SET
                    name = EXCLUDED.name,
                    department_code = EXCLUDED.department_code,
                    region_code = EXCLUDED.region_code,
                    postal_code = EXCLUDED.postal_code,
                    updated_at = EXCLUDED.updated_at
                RETURNING CASE WHEN xmax = 0 THEN 1 ELSE 0 END
            SQL,
            [
                'id' => (string) new UuidV7(),
                'country_code' => $city->countryCode->value,
                'local_code' => $city->localCode,
                'name' => $city->name,
                'department_code' => $city->departmentCode,
                'region_code' => $city->regionCode,
                'postal_code' => $city->postalCode ?? null,
                'created_at' => $this->formatDateTime($city->createdAt),
                'updated_at' => $this->formatDateTime($city->updatedAt),
            ],
            [
                'id' => ParameterType::STRING,
                'country_code' => ParameterType::STRING,
                'local_code' => ParameterType::STRING,
                'name' => ParameterType::STRING,
                'department_code' => null !== $city->departmentCode ? ParameterType::STRING : ParameterType::NULL,
                'region_code' => null !== $city->regionCode ? ParameterType::STRING : ParameterType::NULL,
                'postal_code' => null !== $city->postalCode ? ParameterType::STRING : ParameterType::NULL,
                'created_at' => ParameterType::STRING,
                'updated_at' => ParameterType::STRING,
            ],
        );

        if (!in_array($isNew, [0, 1, '0', '1'], true)) {
            throw new \UnexpectedValueException('Unable to determine whether the city was created or updated.');
        }

        return 1 === $isNew || '1' === $isNew;
    }

    private function formatDateTime(\DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }
}
