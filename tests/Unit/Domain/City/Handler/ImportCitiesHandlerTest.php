<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\City\Handler;

use App\Application\City\Handler\ImportCitiesHandler;
use App\Domain\City\Exception\CityDataProviderException;
use App\Domain\City\Model\City;
use App\Domain\City\Port\CityDataProviderInterface;
use App\Domain\City\Port\CityRepositoryInterface;
use App\Domain\Shared\Model\CountryCode;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ImportCitiesHandlerTest extends TestCase
{
    private CityDataProviderInterface&MockObject $dataProvider;

    private CityRepositoryInterface&MockObject $cityRepository;

    private ImportCitiesHandler $handler;

    protected function setUp(): void
    {
        $this->dataProvider = $this->createMock(CityDataProviderInterface::class);
        $this->cityRepository = $this->createMock(CityRepositoryInterface::class);
        $this->handler = new ImportCitiesHandler([$this->dataProvider], $this->cityRepository);
    }

    public function testInvokeWithEmptyDataReturnsZeroTotals(): void
    {
        $this->dataProvider->expects($this->once())
            ->method('fetchAllCities')
            ->willReturn([]);

        $result = ($this->handler)();

        $this->assertSame(0, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertSame(0, $result->totalProcessed);
    }

    public function testInvokeCreatesNewCitiesAndReturnsCounts(): void
    {
        $cities = [
            $this->makeCity('75056', 'Paris', '75', '11', '75001'),
            $this->makeCity('69123', 'Lyon', '69', '84', '69001'),
        ];

        $this->dataProvider->expects($this->once())
            ->method('fetchAllCities')
            ->willReturn($cities);

        $this->cityRepository->expects($this->exactly(2))
            ->method('save')
            ->willReturn(true);

        $result = ($this->handler)();

        $this->assertSame(2, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertSame(2, $result->totalProcessed);
    }

    public function testInvokeUpdatesExistingCitiesAndReturnsCounts(): void
    {
        $cities = [
            $this->makeCity('75056', 'Paris', '75', '11', '75001'),
        ];

        $this->dataProvider->expects($this->once())
            ->method('fetchAllCities')
            ->willReturn($cities);

        $this->cityRepository->expects($this->once())
            ->method('save')
            ->willReturn(false);

        $result = ($this->handler)();

        $this->assertSame(0, $result->created);
        $this->assertSame(1, $result->updated);
        $this->assertSame(1, $result->totalProcessed);
    }

    public function testInvokeThrowsWhenDataProviderFails(): void
    {
        $this->dataProvider->expects($this->once())
            ->method('fetchAllCities')
            ->willThrowException(CityDataProviderException::fromPrevious(new \RuntimeException('timeout')));

        $this->expectException(CityDataProviderException::class);

        ($this->handler)();
    }

    public function testInvokeAggregatesAcrossMultipleProviders(): void
    {
        $franceProvider = $this->createMock(CityDataProviderInterface::class);
        $franceProvider->expects($this->once())
            ->method('fetchAllCities')
            ->willReturn([$this->makeCity('75056', 'Paris', '75', '11', '75001')]);

        $germanyProvider = $this->createMock(CityDataProviderInterface::class);
        $germanyProvider->expects($this->once())
            ->method('fetchAllCities')
            ->willReturn([
                $this->makeCity('2911298', 'Berlin', null, '16', null, CountryCode::DE),
                $this->makeCity('2867714', 'Munich', null, '02', null, CountryCode::DE),
            ]);

        $cityRepository = $this->createMock(CityRepositoryInterface::class);
        $cityRepository->expects($this->exactly(3))
            ->method('save')
            ->willReturn(true);
        $handler = new ImportCitiesHandler([$franceProvider, $germanyProvider], $cityRepository);

        $result = $handler();

        $this->assertSame(3, $result->created);
        $this->assertSame(0, $result->updated);
        $this->assertSame(3, $result->totalProcessed);
    }

    public function testInvokeCallsProgressCallbacksWithExpectedArguments(): void
    {
        $cities = [
            $this->makeCity('75056', 'Paris', '75', '11', '75001'),
            $this->makeCity('69123', 'Lyon', '69', '84', '69001'),
        ];

        $this->dataProvider->expects($this->once())
            ->method('fetchAllCities')
            ->willReturn($cities);

        $this->cityRepository->expects($this->exactly(2))
            ->method('save')
            ->willReturnOnConsecutiveCalls(true, false);

        $providerLabels = [];
        $cityProgress = [];

        ($this->handler)(
            onProviderStarted: function (string $providerLabel) use (&$providerLabels): void {
                $providerLabels[] = $providerLabel;
            },
            onCityImported: function (int $created, int $updated, int $totalProcessed) use (&$cityProgress): void {
                $cityProgress[] = [$created, $updated, $totalProcessed];
            },
        );

        $this->assertCount(1, $providerLabels);
        $this->assertSame([[1, 0, 1], [1, 1, 2]], $cityProgress);
    }

    private function makeCity(
        string $localCode,
        string $name,
        ?string $departmentCode,
        ?string $regionCode,
        ?string $postalCode,
        CountryCode $countryCode = CountryCode::FR,
    ): City {
        return new City(
            countryCode: $countryCode,
            localCode: $localCode,
            name: $name,
            departmentCode: $departmentCode,
            regionCode: $regionCode,
            postalCode: $postalCode,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
