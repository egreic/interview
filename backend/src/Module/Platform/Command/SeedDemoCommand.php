<?php

declare(strict_types=1);

namespace App\Module\Platform\Command;

use App\Module\Platform\Seed\DemoSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed:demo',
    description: 'Seed the demo dataset: École Démo + Institut Nord tenants and their fictional alumni (idempotent).',
)]
final class SeedDemoCommand extends Command
{
    public function __construct(private readonly DemoSeeder $seeder)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->seeder->seed();

        (new SymfonyStyle($input, $output))->success('Demo dataset seeded (two tenants + alumni).');

        return Command::SUCCESS;
    }
}
