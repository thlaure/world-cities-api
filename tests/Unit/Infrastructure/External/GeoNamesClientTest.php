<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\External;

use App\Domain\City\Exception\CityDataProviderException;
use App\Domain\Shared\Model\CountryCode;
use App\Infrastructure\External\GeoNamesClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class GeoNamesClientTest extends TestCase
{
    /**
     * A small real extract from https://download.geonames.org/export/dump/DE.zip
     * (2 populated places with full admin codes, 1 populated place with an empty
     * admin2 code, 1 non-populated-place row) — not hand-guessed.
     */
    private const string FIXTURE_PATH = __DIR__.'/../../../Fixtures/geonames-de-sample.zip';

    public function testFetchAllCitiesMapsPopulatedPlacesAndFiltersOthers(): void
    {
        $client = new GeoNamesClient(
            new MockHttpClient([new MockResponse($this->loadFixture())]),
            'https://geonames.example.test',
            CountryCode::DE,
        );

        $cities = [...$client->fetchAllCities()];

        $this->assertCount(3, $cities);

        $this->assertSame('2657946', $cities[0]->localCode);
        $this->assertSame('Wyhlen', $cities[0]->name);
        $this->assertSame('01', $cities[0]->regionCode);
        $this->assertSame('083', $cities[0]->departmentCode);
        $this->assertSame(CountryCode::DE, $cities[0]->countryCode);
        $this->assertNull($cities[0]->postalCode);

        $this->assertSame('2803468', $cities[1]->localCode);
        $this->assertSame('Zyfflich', $cities[1]->name);
        $this->assertSame('07', $cities[1]->regionCode);
        $this->assertSame('051', $cities[1]->departmentCode);
    }

    public function testFetchAllCitiesMapsEmptyAdminCodeToNull(): void
    {
        $client = new GeoNamesClient(
            new MockHttpClient([new MockResponse($this->loadFixture())]),
            'https://geonames.example.test',
            CountryCode::DE,
        );

        $cities = [...$client->fetchAllCities()];

        $this->assertSame('Zschernick', $cities[2]->name);
        $this->assertSame('14', $cities[2]->regionCode);
        $this->assertNull($cities[2]->departmentCode);
    }

    public function testFetchAllCitiesThrowsWhenTransportFails(): void
    {
        $client = new GeoNamesClient(
            new MockHttpClient(static fn (): MockResponse => throw new TransportException('connection failed')),
            'https://geonames.example.test',
            CountryCode::DE,
        );

        $this->expectException(CityDataProviderException::class);

        [...$client->fetchAllCities()];
    }

    public function testFetchAllCitiesThrowsOnInvalidArchive(): void
    {
        $client = new GeoNamesClient(
            new MockHttpClient([new MockResponse('not a zip file')]),
            'https://geonames.example.test',
            CountryCode::DE,
        );

        $this->expectException(CityDataProviderException::class);

        [...$client->fetchAllCities()];
    }

    public function testFetchAllCitiesCleansUpTempFileWhenStreamingFailsMidBody(): void
    {
        $client = new GeoNamesClient(
            new MockHttpClient([new MockResponse(['PK', new TransportException('connection dropped mid-stream')])]),
            'https://geonames.example.test',
            CountryCode::DE,
        );

        $tempFilesBefore = glob(sys_get_temp_dir().'/geonames_*');

        try {
            [...$client->fetchAllCities()];
            $this->fail('Expected a CityDataProviderException to be thrown.');
        } catch (CityDataProviderException) {
            // Expected — swallowed so the cleanup assertion below can run.
        }

        $this->assertSame($tempFilesBefore, glob(sys_get_temp_dir().'/geonames_*'), 'A partially written GeoNames temp file must be cleaned up when streaming fails mid-body.');
    }

    private function loadFixture(): string
    {
        $contents = file_get_contents(self::FIXTURE_PATH);

        if (false === $contents) {
            throw new \RuntimeException('Unable to read the GeoNames test fixture at '.self::FIXTURE_PATH);
        }

        return $contents;
    }
}
