<?php

declare(strict_types=1);

namespace App\Application\City\Handler;

use App\Application\City\DTO\ImportResultDTO;
use App\Domain\City\Port\CityDataProviderInterface;
use App\Domain\City\Port\CityRepositoryInterface;

final readonly class ImportCitiesHandler
{
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
            }
        }

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
