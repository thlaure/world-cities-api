<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\External;

use App\Domain\Address\Exception\AddressProviderException;
use App\Domain\Shared\Model\CountryCode;
use App\Infrastructure\External\PhotonClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class PhotonClientTest extends TestCase
{
    /**
     * Fixture captured from a real request against Photon's public demo instance
     * (GET /api?q=10+rue+de+la+paix+paris&limit=1) — not hand-guessed.
     */
    private const string HOUSE_FEATURE_RESPONSE = <<<'JSON'
        {
          "type": "FeatureCollection",
          "features": [
            {
              "type": "Feature",
              "properties": {
                "osm_type": "N",
                "osm_id": 689142458,
                "osm_key": "place",
                "osm_value": "house",
                "type": "house",
                "housenumber": "10",
                "street": "Rue de la Paix",
                "locality": "Quartier Gaillon",
                "district": "Paris",
                "city": "Paris",
                "state": "Île-de-France",
                "country": "France",
                "postcode": "75002",
                "countrycode": "FR"
              },
              "geometry": {
                "type": "Point",
                "coordinates": [2.3311419, 48.8689953]
              }
            }
          ]
        }
        JSON;

    public function testSearchAddressesMapsHouseFeatureToDomainAddress(): void
    {
        $client = new PhotonClient(new MockHttpClient([new MockResponse(self::HOUSE_FEATURE_RESPONSE)]), 'https://photon.example.test');

        $addresses = $client->searchAddresses('10 rue de la paix paris', null, 1);

        $this->assertCount(1, $addresses);
        $address = $addresses[0];
        $this->assertSame('10 Rue de la Paix, 75002 Paris', $address->label);
        $this->assertSame('10', $address->houseNumber);
        $this->assertSame('Rue de la Paix', $address->street);
        $this->assertSame('75002', $address->postalCode);
        $this->assertSame('Paris', $address->city);
        $this->assertSame(CountryCode::FR, $address->countryCode);
        $this->assertSame(48.8689953, $address->latitude);
        $this->assertSame(2.3311419, $address->longitude);
    }

    public function testSearchAddressesUsesNameWhenPresent(): void
    {
        $response = json_encode([
            'features' => [
                [
                    'properties' => [
                        'name' => 'Eiffel Tower',
                        'city' => 'Paris',
                        'countrycode' => 'FR',
                    ],
                    'geometry' => ['coordinates' => [2.2945, 48.8584]],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        $client = new PhotonClient(new MockHttpClient([new MockResponse($response)]), 'https://photon.example.test');

        $addresses = $client->searchAddresses('eiffel tower', null, 1);

        $this->assertSame('Eiffel Tower', $addresses[0]->label);
    }

    public function testSearchAddressesFiltersByCountryCodeClientSide(): void
    {
        $response = json_encode([
            'features' => [
                ['properties' => ['name' => 'Paris', 'countrycode' => 'FR'], 'geometry' => ['coordinates' => [2.35, 48.85]]],
                ['properties' => ['name' => 'Berlin', 'countrycode' => 'DE'], 'geometry' => ['coordinates' => [13.4, 52.5]]],
            ],
        ], \JSON_THROW_ON_ERROR);

        $client = new PhotonClient(new MockHttpClient([new MockResponse($response)]), 'https://photon.example.test');

        $addresses = $client->searchAddresses('city', CountryCode::DE, 10);

        $this->assertCount(1, $addresses);
        $this->assertSame('Berlin', $addresses[0]->label);
        $this->assertSame(CountryCode::DE, $addresses[0]->countryCode);
    }

    public function testSearchAddressesReturnsNullCountryCodeForUnrecognizedCode(): void
    {
        $response = json_encode([
            'features' => [
                ['properties' => ['name' => 'Somewhere', 'countrycode' => 'ZZ'], 'geometry' => ['coordinates' => [0, 0]]],
            ],
        ], \JSON_THROW_ON_ERROR);

        $client = new PhotonClient(new MockHttpClient([new MockResponse($response)]), 'https://photon.example.test');

        $addresses = $client->searchAddresses('somewhere', null, 1);

        $this->assertNull($addresses[0]->countryCode);
    }

    public function testSearchAddressesThrowsWhenTransportFails(): void
    {
        $client = new PhotonClient(new MockHttpClient([new MockResponse('not json')]), 'https://photon.example.test');

        $this->expectException(AddressProviderException::class);

        $client->searchAddresses('paris', null, 1);
    }
}
