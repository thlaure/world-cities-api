<?php

declare(strict_types=1);

namespace App\Infrastructure\External;

use App\Domain\City\Exception\CityDataProviderException;
use App\Domain\City\Model\City;
use App\Domain\City\Port\CityDataProviderInterface;
use App\Domain\Shared\Model\CountryCode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Imports cities from GeoNames (download.geonames.org), a free worldwide gazetteer
 * distributed as one bulk file per country. Reusable across any GeoNames-covered
 * country: only $countryCode changes between instances, see config/services.yaml.
 */
final readonly class GeoNamesClient implements CityDataProviderInterface
{
    private const string POPULATED_PLACE_FEATURE_CLASS = 'P';

    private const int GEONAME_ID_COLUMN = 0;

    private const int NAME_COLUMN = 1;

    private const int FEATURE_CLASS_COLUMN = 6;

    private const int ADMIN1_CODE_COLUMN = 10;

    private const int ADMIN2_CODE_COLUMN = 11;

    private const int EXPECTED_COLUMN_COUNT = 19;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private CountryCode $countryCode,
    ) {
    }

    /**
     * @return iterable<City>
     */
    public function fetchAllCities(): iterable
    {
        $archivePath = null;

        try {
            $response = $this->httpClient->request(Request::METHOD_GET, sprintf('%s/%s.zip', $this->baseUrl, $this->countryCode->value));
            $archivePath = $this->writeResponseToTempFile($response);

            foreach ($this->readCountryFile($archivePath) as $index => $line) {
                $city = $this->mapLine($line, $index);

                if ($city instanceof City) {
                    yield $city;
                }
            }
        } catch (\Throwable $e) {
            throw CityDataProviderException::fromPrevious($e);
        } finally {
            if (null !== $archivePath) {
                unlink($archivePath);
            }
        }
    }

    private function writeResponseToTempFile(ResponseInterface $response): string
    {
        $path = tempnam(sys_get_temp_dir(), 'geonames_');

        if (false === $path) {
            throw new \RuntimeException('Unable to create a temporary file for the GeoNames archive.');
        }

        try {
            $destination = fopen($path, 'wb');

            if (false === $destination) {
                throw new \RuntimeException('Unable to open the GeoNames temporary file for writing.');
            }

            try {
                foreach ($this->httpClient->stream($response) as $chunk) {
                    $contents = $chunk->getContent();
                    $writtenBytes = fwrite($destination, $contents);

                    if (false === $writtenBytes || strlen($contents) !== $writtenBytes) {
                        throw new \RuntimeException('Unable to write the GeoNames archive to the temporary file.');
                    }
                }
            } finally {
                fclose($destination);
            }
        } catch (\Throwable $e) {
            unlink($path);

            throw $e;
        }

        return $path;
    }

    /**
     * @return iterable<int, string>
     */
    private function readCountryFile(string $archivePath): iterable
    {
        $archive = new \ZipArchive();

        if (true !== $archive->open($archivePath)) {
            throw new \UnexpectedValueException(sprintf('Unable to open the GeoNames archive for country "%s".', $this->countryCode->value));
        }

        $entryName = sprintf('%s.txt', $this->countryCode->value);
        $countryFile = null;

        try {
            $countryFile = $archive->getStream($entryName);

            if (false === $countryFile) {
                throw new \UnexpectedValueException(sprintf('Archive for country "%s" does not contain the expected "%s.txt" entry.', $this->countryCode->value, $this->countryCode->value));
            }

            while (false !== $line = fgets($countryFile)) {
                if ('' !== trim($line)) {
                    yield rtrim($line, "\r\n");
                }
            }
        } finally {
            if (false !== $countryFile && null !== $countryFile) {
                fclose($countryFile);
            }

            $archive->close();
        }
    }

    private function mapLine(string $line, int $index): ?City
    {
        $columns = explode("\t", $line);

        if (count($columns) < self::EXPECTED_COLUMN_COUNT) {
            throw new \UnexpectedValueException(sprintf('Invalid GeoNames row for country "%s" at index %d: expected %d columns, got %d.', $this->countryCode->value, $index, self::EXPECTED_COLUMN_COUNT, count($columns)));
        }

        if (self::POPULATED_PLACE_FEATURE_CLASS !== $columns[self::FEATURE_CLASS_COLUMN]) {
            return null;
        }

        $now = new \DateTimeImmutable();

        return new City(
            countryCode: $this->countryCode,
            localCode: $columns[self::GEONAME_ID_COLUMN],
            name: $columns[self::NAME_COLUMN],
            departmentCode: $this->nullIfEmpty($columns[self::ADMIN2_CODE_COLUMN]),
            regionCode: $this->nullIfEmpty($columns[self::ADMIN1_CODE_COLUMN]),
            postalCode: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    private function nullIfEmpty(string $value): ?string
    {
        return '' !== $value ? $value : null;
    }
}
