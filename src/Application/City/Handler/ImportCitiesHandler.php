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

    public function __invoke(): ImportResultDTO
    {
        $created = 0;
        $updated = 0;
        $batchCount = 0;

        foreach ($this->dataProviders as $dataProvider) {
            foreach ($dataProvider->fetchAllCities() as $city) {
                $isNew = $this->cityRepository->save($city);
                $isNew ? ++$created : ++$updated;

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
}
