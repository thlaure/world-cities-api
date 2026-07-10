<?php

declare(strict_types=1);

namespace App\Infrastructure\External;

use App\Domain\Address\Exception\AddressProviderException;
use App\Domain\Address\Model\Address;
use App\Domain\Address\Port\AddressProviderInterface;
use App\Domain\Shared\Model\CountryCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class PhotonClient implements AddressProviderInterface
{
    private const string SEARCH_PATH = '/api';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
    ) {
    }

    /**
     * Photon has no server-side country filter, so when $countryCode is given, results
     * are fetched then filtered client-side — the returned list may be shorter than
     * $limit in that case.
     *
     * @return list<Address>
     */
    public function searchAddresses(string $query, ?CountryCode $countryCode, int $limit): array
    {
        try {
            $response = $this->httpClient->request(Request::METHOD_GET, $this->baseUrl.self::SEARCH_PATH, [
                'query' => [
                    'q' => $query,
                    'limit' => $limit,
                ],
            ]);

            /** @var array{features?: list<array<string, mixed>>} $data */
            $data = $response->toArray();
        } catch (\Throwable $e) {
            throw AddressProviderException::fromPrevious($e);
        }

        $addresses = [];
        foreach ($data['features'] ?? [] as $index => $feature) {
            $address = $this->mapFeature($feature, $index);

            if ($countryCode instanceof CountryCode && $countryCode !== $address->countryCode) {
                continue;
            }

            $addresses[] = $address;
        }

        return $addresses;
    }

    /**
     * @param array<string, mixed> $feature
     */
    private function mapFeature(array $feature, int $index): Address
    {
        $properties = $this->requireArrayField($feature, 'properties', $index);
        $coordinates = $this->requireCoordinates($feature, $index);

        $name = $this->extractStringField($properties, 'name');
        $houseNumber = $this->extractStringField($properties, 'housenumber');
        $street = $this->extractStringField($properties, 'street');
        $postalCode = $this->extractStringField($properties, 'postcode');
        $city = $this->extractStringField($properties, 'city');
        $countryCodeRaw = $this->extractStringField($properties, 'countrycode');

        return new Address(
            label: $this->buildLabel($name, $houseNumber, $street, $postalCode, $city, $index),
            houseNumber: $houseNumber,
            street: $street,
            postalCode: $postalCode,
            city: $city,
            countryCode: null !== $countryCodeRaw ? CountryCode::tryFrom(strtoupper($countryCodeRaw)) : null,
            latitude: $coordinates[1],
            longitude: $coordinates[0],
        );
    }

    private function buildLabel(?string $name, ?string $houseNumber, ?string $street, ?string $postalCode, ?string $city, int $index): string
    {
        if (null !== $name) {
            return $name;
        }

        $streetPart = null !== $houseNumber && null !== $street ? sprintf('%s %s', $houseNumber, $street) : $street;
        $cityPart = null !== $postalCode && null !== $city ? sprintf('%s %s', $postalCode, $city) : $city;

        $parts = array_values(array_filter([$streetPart, $cityPart], static fn (?string $part): bool => null !== $part));

        if ([] === $parts) {
            throw new \UnexpectedValueException(sprintf('Unable to build a label for address payload at index %d.', $index));
        }

        return implode(', ', $parts);
    }

    /**
     * @param array<string, mixed> $feature
     *
     * @return array<array-key, mixed>
     */
    private function requireArrayField(array $feature, string $field, int $index): array
    {
        if (!array_key_exists($field, $feature) || !is_array($feature[$field])) {
            throw new \UnexpectedValueException(sprintf('Invalid "%s" field for address payload at index %d.', $field, $index));
        }

        return $feature[$field];
    }

    /**
     * @param array<string, mixed> $feature
     *
     * @return array{0: float, 1: float}
     */
    private function requireCoordinates(array $feature, int $index): array
    {
        $geometry = $this->requireArrayField($feature, 'geometry', $index);
        $coordinates = $geometry['coordinates'] ?? null;

        if (!is_array($coordinates) || 2 !== count($coordinates) || !is_numeric($coordinates[0]) || !is_numeric($coordinates[1])) {
            throw new \UnexpectedValueException(sprintf('Invalid "geometry.coordinates" field for address payload at index %d.', $index));
        }

        return [(float) $coordinates[0], (float) $coordinates[1]];
    }

    /**
     * @param array<array-key, mixed> $properties
     */
    private function extractStringField(array $properties, string $field): ?string
    {
        $value = $properties[$field] ?? null;

        return is_string($value) && '' !== trim($value) ? $value : null;
    }
}
