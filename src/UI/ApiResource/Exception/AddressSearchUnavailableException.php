<?php

declare(strict_types=1);

namespace App\UI\ApiResource\Exception;

use ApiPlatform\Metadata\Exception\ProblemExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

final class AddressSearchUnavailableException extends \RuntimeException implements ProblemExceptionInterface
{
    public function getType(): string
    {
        return '/errors/503';
    }

    public function getTitle(): string
    {
        return 'Address search is temporarily unavailable';
    }

    public function getStatus(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }

    public function getDetail(): string
    {
        return 'The address search provider could not be reached. Please try again later.';
    }

    public function getInstance(): ?string
    {
        return null;
    }
}
