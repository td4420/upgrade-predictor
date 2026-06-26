<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Fetcher;

use RuntimeException;
use Symfony\Component\Process\Process;

class ComposerFetcher
{
    public function __construct(private readonly string $projectDir) {}

    /**
     * Read composer.lock and return all magento/* packages with their versions.
     *
     * @return array<string, string>  ['magento/module-catalog' => '104.0.9', ...]
     * @throws RuntimeException if composer.lock is missing
     */
    public function getMagentoPackages(): array
    {
        $lockFile = $this->projectDir . '/composer.lock';
        if (!file_exists($lockFile)) {
            throw new RuntimeException("composer.lock not found in {$this->projectDir}");
        }

        $lock = json_decode(file_get_contents($lockFile), true);
        if (!is_array($lock)) {
            throw new RuntimeException('composer.lock is not valid JSON');
        }

        $packages = [];
        $allPackages = array_merge(
            $lock['packages'] ?? [],
            $lock['packages-dev'] ?? []
        );

        foreach ($allPackages as $pkg) {
            $name = $pkg['name'] ?? '';
            if (str_starts_with($name, 'magento/')) {
                $packages[$name] = $pkg['version'];
            }
        }

        return $packages;
    }

    /**
     * Read composer.json and return the current Magento version.
     */
    public function getCurrentMagentoVersion(): string
    {
        $composerFile = $this->projectDir . '/composer.json';
        if (!file_exists($composerFile)) {
            return 'unknown';
        }

        $data = json_decode(file_get_contents($composerFile), true);
        if (!is_array($data)) {
            return 'unknown';
        }

        // Direct version field
        if (isset($data['version']) && $data['version'] !== '') {
            return $data['version'];
        }

        // From require: magento/product-community-edition or enterprise-edition
        $require = $data['require'] ?? [];
        foreach (['magento/product-community-edition', 'magento/product-enterprise-edition'] as $pkg) {
            if (isset($require[$pkg]) && $require[$pkg] !== '') {
                return $require[$pkg];
            }
        }

        return 'unknown';
    }

    /**
     * Create a temp directory, write a composer.json requiring all current magento/* packages
     * at $targetVersion, run composer install, and return path to the vendor/ directory.
     */
    public function fetch(string $targetVersion): string
    {
        $tempDir = sys_get_temp_dir() . '/upgrade-predictor-' . uniqid('', true);
        mkdir($tempDir, 0755, true);

        // Build composer.json with all magento/* packages required at target version
        $magentoPackages = $this->getMagentoPackages();
        $require = [];
        foreach (array_keys($magentoPackages) as $name) {
            $require[$name] = $targetVersion;
        }

        $composerJson = [
            'name'        => 'bss/upgrade-predictor-temp',
            'description' => 'Temporary project for upgrade predictor',
            'require'     => $require,
            'minimum-stability' => 'dev',
            'prefer-stable'     => true,
        ];

        file_put_contents(
            $tempDir . '/composer.json',
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Copy auth.json if present
        $authFile = $this->projectDir . '/auth.json';
        if (file_exists($authFile)) {
            copy($authFile, $tempDir . '/auth.json');
        }

        $process = new Process(
            ['composer', 'install', '--no-dev', '--no-autoloader', '--no-scripts', '--prefer-dist'],
            $tempDir
        );
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                "composer install failed:\n" . $process->getErrorOutput()
            );
        }

        return $tempDir . '/vendor';
    }

    /**
     * Remove the temp directory that was created by fetch().
     * Safety: only removes directories under sys_get_temp_dir().
     */
    public function cleanup(string $tempDir): void
    {
        // tempDir is the vendor/ path — parent is the temp project dir
        $parentDir = dirname($tempDir);
        $sysTempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR);

        if (!str_starts_with(realpath($parentDir) ?: $parentDir, $sysTempDir)) {
            throw new RuntimeException("Refusing to delete directory outside of temp: {$parentDir}");
        }

        $this->removeDirectory($parentDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
