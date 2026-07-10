<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\City\Model;

use App\Domain\City\Model\City;
use App\Domain\Shared\Model\CountryCode;
use PHPUnit\Framework\TestCase;

final class CityTest extends TestCase
{
    public function testConstructorCreatesCityWithValidData(): void
    {
        $city = new City(
            countryCode: CountryCode::FR,
            localCode: '75056',
            name: 'Paris',
            departmentCode: '75',
            regionCode: '11',
            postalCode: '75001',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $this->assertSame(CountryCode::FR, $city->countryCode);
        $this->assertSame('75056', $city->localCode);
        $this->assertSame('Paris', $city->name);
        $this->assertSame('75', $city->departmentCode);
        $this->assertSame('11', $city->regionCode);
        $this->assertSame('75001', $city->postalCode);
    }

    public function testConstructorAllowsNullPostalCode(): void
    {
        $city = new City(
            countryCode: CountryCode::FR,
            localCode: '01001',
            name: 'L\'Abergement-Clemenciat',
            departmentCode: '01',
            regionCode: '84',
            postalCode: null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $this->assertNull($city->postalCode);
    }

    public function testConstructorAllowsNullDepartmentAndRegionCodes(): void
    {
        $city = new City(
            countryCode: CountryCode::DE,
            localCode: '08111000',
            name: 'Stuttgart',
            departmentCode: null,
            regionCode: null,
            postalCode: '70173',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $this->assertNull($city->departmentCode);
        $this->assertNull($city->regionCode);
    }

    public function testConstructorRejectsEmptyLocalCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('City local code must not be empty.');

        new City(
            countryCode: CountryCode::FR,
            localCode: '',
            name: 'Paris',
            departmentCode: '75',
            regionCode: '11',
            postalCode: '75001',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function testConstructorRejectsEmptyDepartmentCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('City department code must not be empty.');

        new City(
            countryCode: CountryCode::FR,
            localCode: '75056',
            name: 'Paris',
            departmentCode: '',
            regionCode: '11',
            postalCode: '75001',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function testConstructorRejectsEmptyRegionCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('City region code must not be empty.');

        new City(
            countryCode: CountryCode::FR,
            localCode: '75056',
            name: 'Paris',
            departmentCode: '75',
            regionCode: '',
            postalCode: '75001',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }

    public function testConstructorRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('City name must not be empty.');

        new City(
            countryCode: CountryCode::FR,
            localCode: '75056',
            name: '',
            departmentCode: '75',
            regionCode: '11',
            postalCode: '75001',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
