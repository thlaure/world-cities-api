<?php

declare(strict_types=1);

namespace App\Tests\Unit\UI\ApiResource;

use App\Domain\Shared\Model\CountryCode;
use App\Entity\City;
use App\UI\ApiResource\CityResource;
use PHPUnit\Framework\TestCase;

final class CityResourceTest extends TestCase
{
    public function testFromEntityMapsAllFields(): void
    {
        $entity = new City(
            countryCode: CountryCode::FR,
            localCode: '75056',
            name: 'Paris',
            departmentCode: '75',
            regionCode: '11',
            postalCode: '75001',
        );

        $resource = CityResource::fromEntity($entity);

        self::assertSame(CountryCode::FR, $resource->countryCode);
        self::assertSame('75056', $resource->localCode);
        self::assertSame('Paris', $resource->name);
        self::assertSame('75', $resource->departmentCode);
        self::assertSame('11', $resource->regionCode);
        self::assertSame('75001', $resource->postalCode);
    }

    public function testFromEntityMapsNullPostalCode(): void
    {
        $entity = new City(
            countryCode: CountryCode::FR,
            localCode: '75056',
            name: 'Paris',
            departmentCode: '75',
            regionCode: '11',
        );

        $resource = CityResource::fromEntity($entity);

        self::assertNull($resource->postalCode);
    }

    public function testFromEntityMapsNullDepartmentAndRegionCodes(): void
    {
        $entity = new City(
            countryCode: CountryCode::DE,
            localCode: '08111000',
            name: 'Stuttgart',
            departmentCode: null,
            regionCode: null,
            postalCode: '70173',
        );

        $resource = CityResource::fromEntity($entity);

        self::assertNull($resource->departmentCode);
        self::assertNull($resource->regionCode);
    }
}
