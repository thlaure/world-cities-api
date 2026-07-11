<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\City\Model\City;
use App\Domain\Shared\Model\CountryCode;
use App\Entity\City as CityEntity;
use App\Infrastructure\Persistence\DoctrineCityRepository;
use App\Tests\Integration\DatabaseTestCase;

final class DoctrineCityRepositoryTest extends DatabaseTestCase
{
    private DoctrineCityRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = self::getContainer()->get(DoctrineCityRepository::class);
    }

    public function testSaveCreatesNewCityAndReturnsTrue(): void
    {
        $city = $this->makeCity(CountryCode::FR, '75056', 'Paris', '75', '11');

        $isNew = $this->repository->save($city);

        $this->assertTrue($isNew);
        $entity = $this->entityManager->getRepository(CityEntity::class)->findOneBy(['countryCode' => CountryCode::FR, 'localCode' => '75056']);
        $this->assertInstanceOf(CityEntity::class, $entity);
        $this->assertSame('Paris', $entity->getName());
    }

    public function testSaveUpdatesExistingCityAndReturnsFalse(): void
    {
        $city = $this->makeCity(CountryCode::FR, '75056', 'Paris', '75', '11');
        $this->repository->save($city);

        $updated = $this->makeCity(CountryCode::FR, '75056', 'Paris Updated', '75', '11');
        $isNew = $this->repository->save($updated);

        $this->assertFalse($isNew);
        $entity = $this->entityManager->getRepository(CityEntity::class)->findOneBy(['countryCode' => CountryCode::FR, 'localCode' => '75056']);
        $this->assertInstanceOf(CityEntity::class, $entity);
        $this->assertSame('Paris Updated', $entity->getName());
    }

    public function testSavePersistsPostalCode(): void
    {
        $this->repository->save($this->makeCity(CountryCode::FR, '75056', 'Paris', '75', '11', '75001'));

        $entity = $this->entityManager->getRepository(CityEntity::class)->findOneBy(['countryCode' => CountryCode::FR, 'localCode' => '75056']);
        $this->assertInstanceOf(CityEntity::class, $entity);
        $this->assertSame('75001', $entity->getPostalCode());
    }

    public function testSavePersistsNullDepartmentAndRegionCodes(): void
    {
        $this->repository->save($this->makeCity(CountryCode::DE, '08111000', 'Stuttgart', null, null, '70173'));

        $entity = $this->entityManager->getRepository(CityEntity::class)->findOneBy(['countryCode' => CountryCode::DE, 'localCode' => '08111000']);
        $this->assertInstanceOf(CityEntity::class, $entity);
        $this->assertNull($entity->getDepartmentCode());
        $this->assertNull($entity->getRegionCode());
    }

    public function testSameLocalCodeInDifferentCountriesArePersistedIndependently(): void
    {
        $this->repository->save($this->makeCity(CountryCode::FR, '75056', 'Paris', '75', '11'));
        $this->repository->save($this->makeCity(CountryCode::DE, '75056', 'Some German City', null, null));

        $frenchEntity = $this->entityManager->getRepository(CityEntity::class)->findOneBy(['countryCode' => CountryCode::FR, 'localCode' => '75056']);
        $germanEntity = $this->entityManager->getRepository(CityEntity::class)->findOneBy(['countryCode' => CountryCode::DE, 'localCode' => '75056']);

        $this->assertInstanceOf(CityEntity::class, $frenchEntity);
        $this->assertInstanceOf(CityEntity::class, $germanEntity);
        $this->assertSame('Paris', $frenchEntity->getName());
        $this->assertSame('Some German City', $germanEntity->getName());
    }

    private function makeCity(CountryCode $countryCode, string $localCode, string $name, ?string $dept, ?string $region, ?string $postalCode = null): City
    {
        return new City(
            countryCode: $countryCode,
            localCode: $localCode,
            name: $name,
            departmentCode: $dept,
            regionCode: $region,
            postalCode: $postalCode,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
