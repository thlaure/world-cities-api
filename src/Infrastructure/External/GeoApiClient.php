<?php

declare(strict_types=1);

namespace App\Infrastructure\External;

use App\Domain\City\Exception\CityDataProviderException;
use App\Domain\City\Model\City;
use App\Domain\City\Port\CityDataProviderInterface;
use App\Domain\Shared\Model\CountryCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GeoApiClient implements CityDataProviderInterface
{
    private const string DEPARTMENTS_PATH = '/departements';

    private const string COMMUNES_PATH = '/communes';

    private const string FIELDS = 'code,nom,codeDepartement,codeRegion,codesPostaux';

    private const CountryCode COUNTRY_CODE = CountryCode::FR;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
    ) {
    }

    /**
     * @return iterable<City>
     */
    public function fetchAllCities(): iterable
    {
        try {
            foreach ($this->fetchDepartmentCodes() as $departmentCode) {
                foreach ($this->fetchCitiesByDepartment($departmentCode) as $index => $rawCity) {
                    yield $this->mapCity($rawCity, $index);
                }
            }
        } catch (\Throwable $e) {
            throw CityDataProviderException::fromPrevious($e);
        }
    }

    /**
     * @return list<string>
     */
    private function fetchDepartmentCodes(): array
    {
        $response = $this->httpClient->request(Request::METHOD_GET, $this->baseUrl.self::DEPARTMENTS_PATH, [
            'query' => [
                'fields' => 'code',
                'format' => 'json',
                'geometry' => 'none',
            ],
        ]);

        /** @var list<array<string, mixed>> $data */
        $data = $response->toArray();

        return array_map(fn (array $department, int $index): string => $this->requireStringField($department, 'code', $index), $data, array_keys($data));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchCitiesByDepartment(string $departmentCode): array
    {
        $response = $this->httpClient->request(Request::METHOD_GET, sprintf('%s%s/%s%s', $this->baseUrl, self::DEPARTMENTS_PATH, $departmentCode, self::COMMUNES_PATH), [
            'query' => [
                'fields' => self::FIELDS,
                'format' => 'json',
                'geometry' => 'none',
            ],
        ]);

        /** @var list<array<string, mixed>> $data */
        $data = $response->toArray();

        return $data;
    }

    /**
     * @param array<string, mixed> $rawCity
     */
    private function mapCity(array $rawCity, int $index): City
    {
        $localCode = $this->requireStringField($rawCity, 'code', $index);
        $name = $this->requireStringField($rawCity, 'nom', $index);
        $departmentCode = $this->requireStringField($rawCity, 'codeDepartement', $index);
        $regionCode = $this->requireStringField($rawCity, 'codeRegion', $index);
        $postalCode = $this->extractPostalCode($rawCity['codesPostaux'] ?? null, $index);
        $now = new \DateTimeImmutable();

        return new City(
            countryCode: self::COUNTRY_CODE,
            localCode: $localCode,
            name: $name,
            departmentCode: $departmentCode,
            regionCode: $regionCode,
            postalCode: $postalCode,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @param array<string, mixed> $rawCity
     */
    private function requireStringField(array $rawCity, string $field, int $index): string
    {
        if (!array_key_exists($field, $rawCity) || !is_string($rawCity[$field]) || '' === trim($rawCity[$field])) {
            throw new \UnexpectedValueException(sprintf('Invalid "%s" field for city payload at index %d.', $field, $index));
        }

        return $rawCity[$field];
    }

    private function extractPostalCode(mixed $postalCodes, int $index): ?string
    {
        if (null === $postalCodes) {
            return null;
        }

        if (!is_array($postalCodes)) {
            throw new \UnexpectedValueException(sprintf('Invalid "codesPostaux" field for city payload at index %d.', $index));
        }

        if ([] === $postalCodes) {
            return null;
        }

        $firstPostalCode = $postalCodes[0] ?? null;

        if (!is_string($firstPostalCode)) {
            throw new \UnexpectedValueException(sprintf('Invalid first postal code for city payload at index %d.', $index));
        }

        return $firstPostalCode;
    }
}
