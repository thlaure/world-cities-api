<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Address\Model;

use App\Domain\Address\Model\Address;
use App\Domain\Shared\Model\CountryCode;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function testConstructorCreatesAddressWithValidData(): void
    {
        $address = new Address(
            label: '10 Rue de la Paix, 75002 Paris',
            houseNumber: '10',
            street: 'Rue de la Paix',
            postalCode: '75002',
            city: 'Paris',
            countryCode: CountryCode::FR,
            latitude: 48.868995,
            longitude: 2.331141,
        );

        $this->assertSame('10 Rue de la Paix, 75002 Paris', $address->label);
        $this->assertSame('10', $address->houseNumber);
        $this->assertSame('Rue de la Paix', $address->street);
        $this->assertSame('75002', $address->postalCode);
        $this->assertSame('Paris', $address->city);
        $this->assertSame(CountryCode::FR, $address->countryCode);
        $this->assertSame(48.868995, $address->latitude);
        $this->assertSame(2.331141, $address->longitude);
    }

    public function testConstructorAllowsNullOptionalFields(): void
    {
        $address = new Address(
            label: 'Somewhere',
            houseNumber: null,
            street: null,
            postalCode: null,
            city: null,
            countryCode: null,
            latitude: 0.0,
            longitude: 0.0,
        );

        $this->assertNull($address->houseNumber);
        $this->assertNull($address->street);
        $this->assertNull($address->postalCode);
        $this->assertNull($address->city);
        $this->assertNull($address->countryCode);
    }

    public function testConstructorRejectsEmptyLabel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address label must not be empty.');

        new Address(
            label: '',
            houseNumber: null,
            street: null,
            postalCode: null,
            city: null,
            countryCode: null,
            latitude: 0.0,
            longitude: 0.0,
        );
    }

    public function testConstructorRejectsOutOfRangeLatitude(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address latitude must be between -90 and 90.');

        new Address(
            label: 'Somewhere',
            houseNumber: null,
            street: null,
            postalCode: null,
            city: null,
            countryCode: null,
            latitude: 90.1,
            longitude: 0.0,
        );
    }

    public function testConstructorRejectsOutOfRangeLongitude(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Address longitude must be between -180 and 180.');

        new Address(
            label: 'Somewhere',
            houseNumber: null,
            street: null,
            postalCode: null,
            city: null,
            countryCode: null,
            latitude: 0.0,
            longitude: -180.1,
        );
    }
}
