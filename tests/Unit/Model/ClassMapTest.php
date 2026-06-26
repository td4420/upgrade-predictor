<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Model;

use Bss\UpgradePredictor\Model\ClassMap;
use PHPUnit\Framework\TestCase;

class ClassMapTest extends TestCase
{
    public function testHasClass(): void
    {
        $map = new ClassMap(['Magento\\Catalog\\Model\\Product' => ['file' => 'Product.php', 'parent' => 'Magento\\Catalog\\Model\\AbstractModel', 'interfaces' => [], 'methods' => ['getName' => ['params' => [], 'returnType' => '?string', 'visibility' => 'public']]]]);
        $this->assertTrue($map->hasClass('Magento\\Catalog\\Model\\Product'));
        $this->assertFalse($map->hasClass('Magento\\Catalog\\Model\\Category'));
    }

    public function testGetMethods(): void
    {
        $methods = ['getName' => ['params' => [], 'returnType' => '?string', 'visibility' => 'public'], 'setPrice' => ['params' => [['name' => 'price', 'type' => 'float', 'default' => null]], 'returnType' => 'self', 'visibility' => 'public']];
        $map = new ClassMap(['Magento\\Catalog\\Model\\Product' => ['file' => 'Product.php', 'parent' => null, 'interfaces' => [], 'methods' => $methods]]);
        $this->assertSame($methods, $map->getMethods('Magento\\Catalog\\Model\\Product'));
        $this->assertNull($map->getMethods('NonExistent'));
    }

    public function testGetMethod(): void
    {
        $map = new ClassMap(['Magento\\Catalog\\Model\\Product' => ['file' => 'Product.php', 'parent' => null, 'interfaces' => [], 'methods' => ['getName' => ['params' => [], 'returnType' => '?string', 'visibility' => 'public']]]]);
        $this->assertNotNull($map->getMethod('Magento\\Catalog\\Model\\Product', 'getName'));
        $this->assertNull($map->getMethod('Magento\\Catalog\\Model\\Product', 'nonExistent'));
        $this->assertNull($map->getMethod('NonExistent', 'getName'));
    }

    public function testToArrayAndFromArray(): void
    {
        $data = ['Magento\\Catalog\\Model\\Product' => ['file' => 'Product.php', 'parent' => null, 'interfaces' => [], 'methods' => []]];
        $map = new ClassMap($data);
        $this->assertSame($data, $map->toArray());
        $restored = ClassMap::fromArray($data);
        $this->assertSame($data, $restored->toArray());
    }
}
