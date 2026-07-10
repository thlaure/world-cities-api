<?php

declare(strict_types=1);

namespace App\UI\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use App\Domain\Address\Model\Address;
use App\Domain\Shared\Model\CountryCode;
use App\Infrastructure\Http\Provider\AddressSearchProvider;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

#[ApiResource(shortName: 'Address', operations: [
    new GetCollection(
        uriTemplate: '/addresses/search',
        paginationEnabled: false,
        provider: AddressSearchProvider::class,
        parameters: [
            'q' => new QueryParameter(
                schema: ['type' => 'string'],
                property: 'q',
                description: 'Partial or full-text address query.',
                required: true,
                constraints: [
                    new NotBlank(message: 'The "q" parameter must not be blank.'),
                    new Length(max: 255),
                ],
                castToArray: false,
            ),
            'countryCode' => new QueryParameter(
                schema: ['type' => 'string'],
                property: 'countryCode',
                description: 'Restrict results to this ISO 3166-1 alpha-2 country code.',
                constraints: [
                    new NotBlank(message: 'The "countryCode" parameter must not be blank. Omit it to disable this filter.', allowNull: true),
                    new Choice(callback: [CountryCode::class, 'values'], message: 'The "countryCode" parameter must be a valid ISO 3166-1 alpha-2 country code.'),
                ],
                castToArray: false,
            ),
            'limit' => new QueryParameter(
                schema: ['type' => 'integer'],
                property: 'limit',
                description: 'Maximum number of results (1-20, default 10).',
                constraints: [
                    new Range(notInRangeMessage: 'The "limit" parameter must be between {{ min }} and {{ max }}.', min: 1, max: 20),
                ],
                castToArray: false,
            ),
        ],
    ),
], formats: ['json' => ['application/json']], normalizationContext: ['groups' => ['address:read']])]
final readonly class AddressResource
{
    public function __construct(
        #[Groups(['address:read'])]
        public string $label,
        #[Groups(['address:read'])]
        public ?string $houseNumber,
        #[Groups(['address:read'])]
        public ?string $street,
        #[Groups(['address:read'])]
        public ?string $postalCode,
        #[Groups(['address:read'])]
        public ?string $city,
        #[Groups(['address:read'])]
        public ?string $countryCode,
        #[Groups(['address:read'])]
        public float $latitude,
        #[Groups(['address:read'])]
        public float $longitude,
    ) {
    }

    public static function fromDomain(Address $address): self
    {
        return new self(
            label: $address->label,
            houseNumber: $address->houseNumber,
            street: $address->street,
            postalCode: $address->postalCode,
            city: $address->city,
            countryCode: $address->countryCode?->value,
            latitude: $address->latitude,
            longitude: $address->longitude,
        );
    }
}
