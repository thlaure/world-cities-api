<?php

declare(strict_types=1);

namespace App\Application\City\Handler;

use App\Application\City\DTO\ImportResultDTO;
use App\Domain\City\Port\CityDataProviderInterface;
use App\Domain\City\Port\CityRepositoryInterface;

final readonly class ImportCitiesHandler
{
    private const int FLUSH_BATCH_SIZE = 50;

    /**
     * @param iterable<CityDataProviderInterface> $dataProviders
     */
    public function __construct(
        private iterable $dataProviders,
        private CityRepositoryInterface $cityRepository,
    ) {
    }

    /**
     * @param callable(string): void|null        $onProviderStarted called once per provider with its short class name
     * @param callable(int, int, int): void|null $onCityImported    called after each city with (created, updated, totalProcessed)
     */
    public function __invoke(?callable $onProviderStarted = null, ?callable $onCityImported = null): ImportResultDTO
    {
        $created = 0;
        $updated = 0;
        $batchCount = 0;

        foreach ($this->dataProviders as $dataProvider) {
            if (null !== $onProviderStarted) {
                $onProviderStarted($this->resolveProviderLabel($dataProvider));
            }

            foreach ($dataProvider->fetchAllCities() as $city) {
                $isNew = $this->cityRepository->save($city);
                $isNew ? ++$created : ++$updated;

                if (null !== $onCityImported) {
                    $onCityImported($created, $updated, $created + $updated);
                }

                if (0 === ++$batchCount % self::FLUSH_BATCH_SIZE) {
                    $this->cityRepository->flush();
                }
            }
        }

        $this->cityRepository->flush();

        return new ImportResultDTO(
            created: $created,
            updated: $updated,
            totalProcessed: $created + $updated,
        );
    }

    private function resolveProviderLabel(CityDataProviderInterface $dataProvider): string
    {
        $className = $dataProvider::class;

        return substr($className, strrpos($className, '\\') + 1);
    }
}
