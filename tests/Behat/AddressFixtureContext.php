<?php

declare(strict_types=1);

namespace App\Tests\Behat;

use App\Domain\Address\Model\Address;
use App\Domain\Shared\Model\CountryCode;
use App\Tests\Fake\FakeAddressProvider;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;

final readonly class AddressFixtureContext implements Context
{
    public function __construct(
        private FakeAddressProvider $addressProvider,
    ) {
    }

    /**
     * @BeforeScenario
     */
    public function resetAddressProvider(BeforeScenarioScope $scope): void
    {
        $this->addressProvider->reset();
    }

    /**
     * @Given the address search returns:
     */
    public function theAddressSearchReturns(TableNode $table): void
    {
        $addresses = array_map(
            static fn (array $row): Address => new Address(
                label: $row['label'],
                houseNumber: '' !== $row['houseNumber'] ? $row['houseNumber'] : null,
                street: '' !== $row['street'] ? $row['street'] : null,
                postalCode: '' !== $row['postalCode'] ? $row['postalCode'] : null,
                city: '' !== $row['city'] ? $row['city'] : null,
                countryCode: '' !== $row['countryCode'] ? CountryCode::from($row['countryCode']) : null,
                latitude: (float) $row['latitude'],
                longitude: (float) $row['longitude'],
            ),
            $table->getHash(),
        );

        $this->addressProvider->setAddresses($addresses);
    }

    /**
     * @Given the address search provider is unavailable
     */
    public function theAddressSearchProviderIsUnavailable(): void
    {
        $this->addressProvider->simulateFailure();
    }
}
