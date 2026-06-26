<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Cli;

use Bss\UpgradePredictor\Config\Config;
use Bss\UpgradePredictor\Snapshot\SnapshotStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ListCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('list-snapshots')
            ->setDescription('List all available snapshots')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectDir = getcwd();
        $config = Config::load($projectDir, $input->getOption('config'));
        $storage = new SnapshotStorage($projectDir . '/' . $config->snapshotDir);

        $snapshots = $storage->listSnapshots();

        if (empty($snapshots)) {
            $output->writeln('<comment>No snapshots found. Run "snapshot <target-version>" to create one.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Version', 'From Version', 'Created At', 'Package Count']);

        foreach ($snapshots as $snap) {
            $table->addRow([
                $snap['version'],
                $snap['from_version'],
                $snap['created_at'],
                $snap['package_count'],
            ]);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
