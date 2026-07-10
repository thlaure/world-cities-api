<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Domain\Address\Exception\AddressProviderException;
use App\Domain\Address\Port\AddressProviderInterface;
use App\Domain\Shared\Model\CountryCode;
use App\UI\ApiResource\AddressResource;
use App\UI\ApiResource\Exception\AddressSearchUnavailableException;
use Psr\Log\LoggerInterface;

/**
 * @implements ProviderInterface<AddressResource>
 */
final readonly class AddressSearchProvider implements ProviderInterface
{
    private const int DEFAULT_LIMIT = 10;

    private const int MIN_LIMIT = 1;

    private const int MAX_LIMIT = 20;

    public function __construct(
        private AddressProviderInterface $addressProvider,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<AddressResource>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $parameters = $operation->getParameters();

        $query = $parameters?->get('q')?->getValue();
        $countryCodeRaw = $parameters?->get('countryCode')?->getValue();
        $limitRaw = $parameters?->get('limit')?->getValue();

        assert(is_string($query));

        $countryCode = is_string($countryCodeRaw) ? CountryCode::tryFrom($countryCodeRaw) : null;
        $limit = $this->resolveLimit($limitRaw);

        try {
            $addresses = $this->addressProvider->searchAddresses($query, $countryCode, $limit);
        } catch (AddressProviderException $e) {
            $this->logger->error('Address search provider failed.', ['exception' => $e]);

            throw new AddressSearchUnavailableException();
        }

        return array_map(AddressResource::fromDomain(...), $addresses);
    }

    private function resolveLimit(mixed $limitRaw): int
    {
        if (!is_string($limitRaw) && !is_int($limitRaw)) {
            return self::DEFAULT_LIMIT;
        }

        $limit = (int) $limitRaw;

        return max(self::MIN_LIMIT, min(self::MAX_LIMIT, $limit));
    }
}
