<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\External;

use App\Domain\City\Exception\CityDataProviderException;
use App\Domain\Shared\Model\CountryCode;
use App\Infrastructure\External\GeoApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeoApiClientTest extends TestCase
{
    public function testFetchAllCitiesMapsValidPayloadToDomainCities(): void
    {
        $client = new GeoApiClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    ['code' => '75'],
                    ['code' => '69'],
                ], \JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    [
                        'code' => '75056',
                        'nom' => 'Paris',
                        'codeDepartement' => '75',
                        'codeRegion' => '11',
                        'codesPostaux' => ['75001'],
                    ],
                ], \JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    [
                        'code' => '69123',
                        'nom' => 'Lyon',
                        'codeDepartement' => '69',
                        'codeRegion' => '84',
                        'codesPostaux' => ['69001'],
                    ],
                ], \JSON_THROW_ON_ERROR)),
            ]),
            'https://geo.api.gouv.fr',
        );

        $cities = [...$client->fetchAllCities()];

        $this->assertCount(2, $cities);
        $this->assertSame(CountryCode::FR, $cities[0]->countryCode);
        $this->assertSame('75056', $cities[0]->localCode);
        $this->assertSame('Paris', $cities[0]->name);
        $this->assertSame('75001', $cities[0]->postalCode);
        $this->assertSame('69001', $cities[1]->postalCode);
        $this->assertSame(CountryCode::FR, $cities[1]->countryCode);
        $this->assertSame('69123', $cities[1]->localCode);
        $this->assertSame('Lyon', $cities[1]->name);
    }

    public function testFetchAllCitiesThrowsWhenPayloadIsMissingRequiredFields(): void
    {
        $client = new GeoApiClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    ['code' => '75'],
                ], \JSON_THROW_ON_ERROR)),
                new MockResponse(json_encode([
                    [
                        'nom' => 'Paris',
                        'codeDepartement' => '75',
                        'codeRegion' => '11',
                        'codesPostaux' => ['75001'],
                    ],
                ], \JSON_THROW_ON_ERROR)),
            ]),
            'https://geo.api.gouv.fr',
        );

        $this->expectException(CityDataProviderException::class);
        $this->expectExceptionMessage('City data provider is unavailable: Invalid "code" field for city payload at index 0.');

        [...$client->fetchAllCities()];
    }

    public function testFetchAllCitiesThrowsWhenDepartmentPayloadIsInvalid(): void
    {
        $client = new GeoApiClient(
            new MockHttpClient([
                new MockResponse(json_encode([
                    ['name' => 'Paris'],
                ], \JSON_THROW_ON_ERROR)),
            ]),
            'https://geo.api.gouv.fr',
        );

        $this->expectException(CityDataProviderException::class);
        $this->expectExceptionMessage('City data provider is unavailable: Invalid "code" field for city payload at index 0.');

        [...$client->fetchAllCities()];
    }
}
