<?php

declare(strict_types=1);

namespace App\UI\Command;

use App\Application\City\Handler\ImportCitiesHandler;
use App\Domain\City\Exception\CityDataProviderException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import-cities',
    description: 'Import cities from every configured data provider into the database.',
)]
final class ImportCitiesCommand extends Command
{
    public function __construct(
        private readonly ImportCitiesHandler $importCitiesHandler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Importing cities...');

        try {
            $result = ($this->importCitiesHandler)();
        } catch (CityDataProviderException $e) {
            $io->error('Failed to fetch city data: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Import complete. Created: %d | Updated: %d | Total processed: %d',
            $result->created,
            $result->updated,
            $result->totalProcessed,
        ));

        return Command::SUCCESS;
    }
}
