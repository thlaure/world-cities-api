<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Catch_\ThrowWithPreviousExceptionRector;
use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withImportNames(importShortClasses: false)
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/public',
        __DIR__.'/src',
        __DIR__.'/migrations',
        __DIR__.'/tests',
    ])
    ->withSkip([
        __DIR__.'/var',
        __DIR__.'/vendor',
        __DIR__.'/config/reference.php',
        // Skip Override attribute for PHP 8.5 compatibility
        AddOverrideAttributeToOverriddenMethodsRector::class,
        // Deliberately don't forward the caught exception's message into
        // AddressSearchUnavailableException — it implements ProblemExceptionInterface
        // with a fixed, safe "detail", and forwarding $previous->getMessage() into the
        // constructor risks leaking internal provider errors to API clients.
        ThrowWithPreviousExceptionRector::class => [
            __DIR__.'/src/Infrastructure/Http/Provider/AddressSearchProvider.php',
        ],
    ])
    ->withPhpSets(php85: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
    )
    ->withAttributesSets(
        symfony: true,
        doctrine: true,
    )
    ->withComposerBased(doctrine: true)
    ->withSets([
        SymfonySetList::SYMFONY_74,
        SymfonySetList::SYMFONY_CODE_QUALITY,
        SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
        SymfonySetList::ANNOTATIONS_TO_ATTRIBUTES,
        SymfonySetList::CONFIGS,
        DoctrineSetList::DOCTRINE_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_100,
    ]);
