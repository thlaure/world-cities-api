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
        $this->handler = new ImportCitiesHandler($this->dataProvider, $this->cityRepository);
    }

    public function testInvokeWithEmptyDataReturnsZeroTotals(): void
    {
        $this->dataProvider->expects($this->once())
            ->method('fetchAllCities')
            ->willReturn([]);

        $this->cityRepository->expects($this->once())
            ->method('flush');

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

    public function testInvokeFlushesEvery50Cities(): void
    {
        $cities = [];

        for ($i = 0; $i < 100; ++$i) {
            $cities[] = $this->makeCity(sprintf('75%03d', $i), sprintf('Paris %d', $i), '75', '11', '');
        }

        $this->dataProvider->expects($this->once())
            ->method('fetchAllCities')
            ->willReturn($cities);

        $this->cityRepository->expects($this->any())
            ->method('save')
            ->willReturn(true);

        // flush called at 50, 100, and final = 3 times
        $this->cityRepository->expects($this->exactly(3))
            ->method('flush');

        ($this->handler)();
    }

    public function testInvokeThrowsWhenDataProviderFails(): void
    {
        $this->dataProvider->expects($this->once())
            ->method('fetchAllCities')
            ->willThrowException(CityDataProviderException::fromPrevious(new \RuntimeException('timeout')));

        $this->expectException(CityDataProviderException::class);

        ($this->handler)();
    }

    private function makeCity(
        string $localCode,
        string $name,
        string $departmentCode,
        string $regionCode,
        string $postalCode,
    ): City {
        return new City(
            countryCode: CountryCode::FR,
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
