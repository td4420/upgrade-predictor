<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Snapshot;

use Bss\UpgradePredictor\Snapshot\PhpClassMapper;
use Bss\UpgradePredictor\Snapshot\SnapshotBuilder;
use PHPUnit\Framework\TestCase;

class SnapshotBuilderTest extends TestCase
{
    public function testBuildCreatesSnapshotWithBothClassMaps(): void
    {
        $fixtureDir = __DIR__ . '/../../Fixtures/php';
        $builder = new SnapshotBuilder(new PhpClassMapper());
        $snapshot = $builder->build($fixtureDir, $fixtureDir, '2.4.9', '2.4.10', 3);
        $this->assertSame('2.4.9', $snapshot->fromVersion);
        $this->assertSame('2.4.10', $snapshot->toVersion);
        $this->assertSame(3, $snapshot->packageCount);
        $this->assertTrue($snapshot->oldClassMap->hasClass('Magento\\Catalog\\Model\\Product'));
        $this->assertTrue($snapshot->newClassMap->hasClass('Magento\\Catalog\\Model\\Product'));
    }
}
