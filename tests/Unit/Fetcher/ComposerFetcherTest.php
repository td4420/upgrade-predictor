<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Fetcher;

use Bss\UpgradePredictor\Fetcher\ComposerFetcher;
use PHPUnit\Framework\TestCase;

class ComposerFetcherTest extends TestCase
{
    public function testExtractsMagentoPackagesFromLock(): void
    {
        $dir = sys_get_temp_dir() . '/fetcher-test-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/composer.lock', json_encode([
            'packages' => [
                ['name' => 'magento/module-catalog', 'version' => '104.0.9'],
                ['name' => 'magento/module-sales', 'version' => '103.0.8'],
                ['name' => 'magento/framework', 'version' => '103.0.9'],
                ['name' => 'symfony/console', 'version' => 'v6.4.0'],
            ],
            'packages-dev' => [],
        ]));
        $fetcher = new ComposerFetcher($dir);
        $packages = $fetcher->getMagentoPackages();
        $this->assertCount(3, $packages);
        $this->assertSame('104.0.9', $packages['magento/module-catalog']);
        unlink($dir . '/composer.lock');
        rmdir($dir);
    }

    public function testGetCurrentVersion(): void
    {
        $dir = sys_get_temp_dir() . '/fetcher-ver-test-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/composer.json', json_encode([
            'name' => 'magento/project-community-edition',
            'require' => ['magento/product-community-edition' => '2.4.9'],
        ]));
        $fetcher = new ComposerFetcher($dir);
        $this->assertSame('2.4.9', $fetcher->getCurrentMagentoVersion());
        unlink($dir . '/composer.json');
        rmdir($dir);
    }

    public function testMissingLockFileThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        (new ComposerFetcher('/nonexistent/path'))->getMagentoPackages();
    }
}
