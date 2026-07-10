<?php

declare(strict_types=1);

namespace App\Tests\Integration\DependencyInjection;

use App\Application\City\Handler\ImportCitiesHandler;
use App\Infrastructure\External\GeoApiClient;
use App\Infrastructure\External\GeoNamesClient;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Guards the app.city_data_provider tagged_iterator wiring itself (config/services.yaml).
 * A typo'd tag string on either provider, or on ImportCitiesHandler's !tagged_iterator
 * argument, would silently drop a country from every import with no other test catching
 * it — ImportCitiesHandlerTest only exercises the aggregation logic with manual mocks,
 * never the real container.
 */
final class CityDataProviderTaggingTest extends KernelTestCase
{
    public function testImportCitiesHandlerReceivesEveryTaggedProvider(): void
    {
        self::bootKernel();

        $handler = self::getContainer()->get(ImportCitiesHandler::class);

        $property = new \ReflectionProperty(ImportCitiesHandler::class, 'dataProviders');
        $value = $property->getValue($handler);

        assert(is_iterable($value));

        $dataProviders = [...$value];

        $this->assertCount(2, $dataProviders);
        $this->assertInstanceOf(GeoApiClient::class, $dataProviders[0]);
        $this->assertInstanceOf(GeoNamesClient::class, $dataProviders[1]);
    }
}
