<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Config;

use Bss\UpgradePredictor\Config\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = Config::load('/nonexistent/path');
        $this->assertSame(['app/code'], $config->scanPaths);
        $this->assertSame([], $config->extraVendors);
        $this->assertSame('.upgrade-predictor/snapshots', $config->snapshotDir);
        $this->assertSame('markdown', $config->output);
        $this->assertSame('critical', $config->failOn);
        $this->assertSame([], $config->ignore);
    }

    public function testLoadFromFile(): void
    {
        $dir = sys_get_temp_dir() . '/upgrade-predictor-config-test-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/upgrade-predictor.json', json_encode([
            'scan_paths' => ['app/code', 'app/design'],
            'extra_vendors' => ['Amasty'],
            'fail_on' => 'warning',
        ]));
        $config = Config::load($dir);
        $this->assertSame(['app/code', 'app/design'], $config->scanPaths);
        $this->assertSame(['Amasty'], $config->extraVendors);
        $this->assertSame('warning', $config->failOn);
        $this->assertSame('.upgrade-predictor/snapshots', $config->snapshotDir);
        unlink($dir . '/upgrade-predictor.json');
        rmdir($dir);
    }

    public function testLoadFromExplicitPath(): void
    {
        $file = sys_get_temp_dir() . '/custom-config-' . uniqid() . '.json';
        file_put_contents($file, json_encode(['extra_vendors' => ['Mirasvit', 'Amasty'], 'output' => 'json']));
        $config = Config::load(dirname($file), $file);
        $this->assertSame(['Mirasvit', 'Amasty'], $config->extraVendors);
        $this->assertSame('json', $config->output);
        unlink($file);
    }
}
