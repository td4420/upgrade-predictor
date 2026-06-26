<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Cli;

use Bss\UpgradePredictor\Config\Config;
use Bss\UpgradePredictor\Fetcher\ComposerFetcher;
use Bss\UpgradePredictor\Snapshot\PhpClassMapper;
use Bss\UpgradePredictor\Snapshot\SnapshotBuilder;
use Bss\UpgradePredictor\Snapshot\SnapshotStorage;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SnapshotCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('snapshot')
            ->setDescription('Fetch target Magento version and build class map snapshot')
            ->addArgument('target-version', InputArgument::REQUIRED, 'Target Magento version (e.g. 2.4.10)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Re-fetch even if snapshot exists')
            ->addOption('keep-source', null, InputOption::VALUE_NONE, 'Keep downloaded source files')
            ->addOption('config', 'c', InputOption::VALUE_REQUIRED, 'Path to config file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targetVersion = $input->getArgument('target-version');
        $projectDir = getcwd();
        $config = Config::load($projectDir, $input->getOption('config'));
        $storage = new SnapshotStorage($projectDir . '/' . $config->snapshotDir);

        if ($storage->has($targetVersion) && !$input->getOption('force')) {
            $output->writeln("<info>Snapshot for {$targetVersion} already exists. Use --force to recreate.</info>");
            return Command::SUCCESS;
        }

        $output->writeln("Fetching Magento {$targetVersion} source...");
        $fetcher = new ComposerFetcher($projectDir);
        $fromVersion = $fetcher->getCurrentMagentoVersion();
        $packages = $fetcher->getMagentoPackages();
        $output->writeln(sprintf('Found %d magento/* packages in current install.', count($packages)));

        $targetVendorPath = $fetcher->fetch($targetVersion);
        $output->writeln('Target version fetched. Building class maps...');

        $builder = new SnapshotBuilder(new PhpClassMapper());
        $snapshot = $builder->build($projectDir . '/vendor', $targetVendorPath, $fromVersion, $targetVersion, count($packages));

        $storage->save($snapshot);
        $output->writeln("<info>Snapshot saved for {$fromVersion} → {$targetVersion}.</info>");

        if ($input->getOption('keep-source')) {
            $snapshotDir = $projectDir . '/' . $config->snapshotDir . '/' . $targetVersion;
            file_put_contents($snapshotDir . '/vendor-path.txt', $targetVendorPath);
            $output->writeln("Source files kept at: {$targetVendorPath}");
        } else {
            $fetcher->cleanup($targetVendorPath);
            $output->writeln('Temporary source files cleaned up.');
        }

        return Command::SUCCESS;
    }
}
