<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Cli;

use Bss\UpgradePredictor\Config\Config;
use Bss\UpgradePredictor\Snapshot\SnapshotStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('clean')
            ->setDescription('Delete one or all snapshots')
            ->addArgument('version', InputArgument::OPTIONAL, 'Snapshot version to delete (omit to delete all)')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectDir = getcwd();
        $config = Config::load($projectDir, $input->getOption('config'));
        $storage = new SnapshotStorage($projectDir . '/' . $config->snapshotDir);

        $version = $input->getArgument('version');

        if ($version !== null) {
            // Delete a specific snapshot
            if (!$storage->has($version)) {
                $output->writeln("<comment>Snapshot for version '{$version}' does not exist.</comment>");
                return Command::SUCCESS;
            }

            $storage->delete($version);
            $output->writeln("<info>Snapshot for version '{$version}' deleted.</info>");
        } else {
            // Delete all snapshots
            $snapshots = $storage->listSnapshots();

            if (empty($snapshots)) {
                $output->writeln('<comment>No snapshots to delete.</comment>');
                return Command::SUCCESS;
            }

            foreach ($snapshots as $snap) {
                $storage->delete($snap['version']);
                $output->writeln("Deleted snapshot for version '{$snap['version']}'.");
            }

            $output->writeln('<info>All snapshots deleted.</info>');
        }

        return Command::SUCCESS;
    }
}
