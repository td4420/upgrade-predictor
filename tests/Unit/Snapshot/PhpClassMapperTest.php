<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Snapshot;

use Bss\UpgradePredictor\Snapshot\PhpClassMapper;
use PHPUnit\Framework\TestCase;

class PhpClassMapperTest extends TestCase
{
    private PhpClassMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PhpClassMapper();
    }

    public function testParsesSingleFile(): void
    {
        $classMap = $this->mapper->mapDirectory(__DIR__ . '/../../Fixtures/php');
        $this->assertTrue($classMap->hasClass('Magento\\Catalog\\Model\\Product'));
        $methods = $classMap->getMethods('Magento\\Catalog\\Model\\Product');
        $this->assertArrayHasKey('getName', $methods);
        $this->assertArrayHasKey('setPrice', $methods);
        $this->assertArrayHasKey('beforeSave', $methods); // protected
        $this->assertArrayNotHasKey('internalHelper', $methods); // private excluded
    }

    public function testExtractsMethodSignature(): void
    {
        $classMap = $this->mapper->mapDirectory(__DIR__ . '/../../Fixtures/php');
        $method = $classMap->getMethod('Magento\\Catalog\\Model\\Category', 'getChildren');
        $this->assertNotNull($method);
        $this->assertCount(2, $method['params']);
        $this->assertSame('recursive', $method['params'][0]['name']);
        $this->assertSame('bool', $method['params'][0]['type']);
        $this->assertSame('array', $method['returnType']);
    }

    public function testExtractsParentAndInterfaces(): void
    {
        $classMap = $this->mapper->mapDirectory(__DIR__ . '/../../Fixtures/php');
        $info = $classMap->getClassInfo('Magento\\Catalog\\Model\\Product');
        $this->assertSame('Magento\\Catalog\\Model\\AbstractModel', $info['parent']);
        $this->assertContains('Magento\\Catalog\\Api\\Data\\ProductInterface', $info['interfaces']);
    }

    public function testParsesInterfaces(): void
    {
        $classMap = $this->mapper->mapDirectory(__DIR__ . '/../../Fixtures/php');
        $this->assertTrue($classMap->hasClass('Magento\\Catalog\\Api\\Data\\ProductInterface'));
        $methods = $classMap->getMethods('Magento\\Catalog\\Api\\Data\\ProductInterface');
        $this->assertArrayHasKey('getName', $methods);
        $this->assertArrayHasKey('setPrice', $methods);
    }

    public function testEmptyDirectory(): void
    {
        $dir = sys_get_temp_dir() . '/empty-php-test-' . uniqid();
        mkdir($dir);
        $classMap = $this->mapper->mapDirectory($dir);
        $this->assertSame([], $classMap->toArray());
        rmdir($dir);
    }
}
