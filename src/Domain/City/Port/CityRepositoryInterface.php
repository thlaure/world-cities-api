<?php

declare(strict_types=1);

namespace App\Domain\City\Port;

use App\Domain\City\Model\City;

interface CityRepositoryInterface
{
    /**
     * Persist a city. Returns true if created, false if updated.
     */
    public function save(City $city): bool;
}
