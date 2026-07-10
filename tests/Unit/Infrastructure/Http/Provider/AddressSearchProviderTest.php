<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http\Provider;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use App\Domain\Address\Exception\AddressProviderException;
use App\Domain\Address\Model\Address;
use App\Domain\Address\Port\AddressProviderInterface;
use App\Domain\Shared\Model\CountryCode;
use App\Infrastructure\Http\Provider\AddressSearchProvider;
use App\UI\ApiResource\Exception\AddressSearchUnavailableException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class AddressSearchProviderTest extends TestCase
{
    private AddressProviderInterface&MockObject $addressProvider;

    private AddressSearchProvider $provider;

    protected function setUp(): void
    {
        $this->addressProvider = $this->createMock(AddressProviderInterface::class);
        $this->provider = new AddressSearchProvider($this->addressProvider, new NullLogger());
    }

    public function testProvideReadsParametersAndMapsResults(): void
    {
        $address = new Address(
            label: 'Paris, France',
            houseNumber: null,
            street: null,
            postalCode: null,
            city: 'Paris',
            countryCode: CountryCode::FR,
            latitude: 48.8566,
            longitude: 2.3522,
        );

        $this->addressProvider->expects($this->once())
            ->method('searchAddresses')
            ->with('paris', CountryCode::FR, 5)
            ->willReturn([$address]);

        $resources = $this->provider->provide($this->operationWith(q: 'paris', countryCode: 'FR', limit: '5'));

        $this->assertCount(1, $resources);
        $this->assertSame('Paris, France', $resources[0]->label);
        $this->assertSame('FR', $resources[0]->countryCode);
    }

    public function testProvideDefaultsLimitWhenMissing(): void
    {
        $this->addressProvider->expects($this->once())
            ->method('searchAddresses')
            ->with('paris', null, 10)
            ->willReturn([]);

        $this->provider->provide($this->operationWith(q: 'paris'));
    }

    public function testProvideClampsLimitAboveMaximum(): void
    {
        $this->addressProvider->expects($this->once())
            ->method('searchAddresses')
            ->with('paris', null, 20)
            ->willReturn([]);

        $this->provider->provide($this->operationWith(q: 'paris', limit: '500'));
    }

    public function testProvideClampsLimitBelowMinimum(): void
    {
        $this->addressProvider->expects($this->once())
            ->method('searchAddresses')
            ->with('paris', null, 1)
            ->willReturn([]);

        $this->provider->provide($this->operationWith(q: 'paris', limit: '0'));
    }

    public function testProvideThrowsUnavailableExceptionWhenProviderFails(): void
    {
        $this->addressProvider->method('searchAddresses')
            ->willThrowException(AddressProviderException::fromPrevious(new \RuntimeException('timeout')));

        $this->expectException(AddressSearchUnavailableException::class);

        $this->provider->provide($this->operationWith(q: 'paris'));
    }

    private function operationWith(string $q, ?string $countryCode = null, ?string $limit = null): GetCollection
    {
        $parameters = [
            'q' => new QueryParameter()->setValue($q),
        ];

        if (null !== $countryCode) {
            $parameters['countryCode'] = new QueryParameter()->setValue($countryCode);
        }

        if (null !== $limit) {
            $parameters['limit'] = new QueryParameter()->setValue($limit);
        }

        return new GetCollection()->withParameters(new Parameters($parameters));
    }
}
