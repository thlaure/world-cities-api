<?php

declare(strict_types=1);

namespace App\Domain\Address\Exception;

final class AddressProviderException extends \RuntimeException
{
    public static function fromPrevious(\Throwable $previous): self
    {
        return new self(
            'Address data provider is unavailable: '.$previous->getMessage(),
            0,
            $previous,
        );
    }
}
