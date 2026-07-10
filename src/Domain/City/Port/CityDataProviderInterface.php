<?php

declare(strict_types=1);

namespace App\Domain\City\Port;

use App\Domain\City\Model\City;

interface CityDataProviderInterface
{
    /**
     * Service tag every implementation must carry (see config/services.yaml) so
     * ImportCitiesHandler can collect them via a tagged iterator.
     */
    public const string TAG = 'app.city_data_provider';

    /**
     * Fetch all cities from the external data source.
     *
     * @return iterable<City>
     */
    public function fetchAllCities(): iterable;
}
