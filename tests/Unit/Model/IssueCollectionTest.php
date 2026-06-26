<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Model;

use Bss\UpgradePredictor\Model\Issue;
use Bss\UpgradePredictor\Model\IssueCollection;
use Bss\UpgradePredictor\Model\Severity;
use PHPUnit\Framework\TestCase;

class IssueCollectionTest extends TestCase
{
    private function makeIssue(Severity $severity, string $analyzer, string $sourceFile): Issue
    {
        return new Issue(severity: $severity, analyzer: $analyzer, sourceFile: $sourceFile, sourceLine: 10, targetClass: 'Magento\\Catalog\\Model\\Product', message: 'Test issue');
    }

    public function testCountAndIterate(): void
    {
        $collection = new IssueCollection([$this->makeIssue(Severity::CRITICAL, 'class-diff', 'a.xml'), $this->makeIssue(Severity::WARNING, 'template', 'b.phtml')]);
        $this->assertCount(2, $collection);
        $this->assertCount(2, iterator_to_array($collection));
    }

    public function testFilterBySeverity(): void
    {
        $collection = new IssueCollection([$this->makeIssue(Severity::CRITICAL, 'class-diff', 'a.xml'), $this->makeIssue(Severity::WARNING, 'template', 'b.phtml'), $this->makeIssue(Severity::CRITICAL, 'plugin', 'c.xml')]);
        $this->assertCount(2, $collection->filterBySeverity(Severity::CRITICAL));
        $this->assertCount(1, $collection->filterBySeverity(Severity::WARNING));
    }

    public function testFilterByAnalyzer(): void
    {
        $collection = new IssueCollection([$this->makeIssue(Severity::CRITICAL, 'class-diff', 'a.xml'), $this->makeIssue(Severity::CRITICAL, 'plugin', 'b.xml'), $this->makeIssue(Severity::WARNING, 'class-diff', 'c.xml')]);
        $this->assertCount(2, $collection->filterByAnalyzer('class-diff'));
    }

    public function testSummary(): void
    {
        $collection = new IssueCollection([$this->makeIssue(Severity::CRITICAL, 'class-diff', 'a.xml'), $this->makeIssue(Severity::WARNING, 'template', 'b.phtml'), $this->makeIssue(Severity::WARNING, 'layout', 'c.xml'), $this->makeIssue(Severity::INFO, 'layout', 'd.xml')]);
        $this->assertSame(['critical' => 1, 'warning' => 2, 'info' => 1], $collection->summary());
    }

    public function testMerge(): void
    {
        $a = new IssueCollection([$this->makeIssue(Severity::CRITICAL, 'class-diff', 'a.xml')]);
        $b = new IssueCollection([$this->makeIssue(Severity::WARNING, 'template', 'b.phtml')]);
        $merged = $a->merge($b);
        $this->assertCount(2, $merged);
        $this->assertCount(1, $a);
    }

    public function testEmptyCollection(): void
    {
        $collection = new IssueCollection([]);
        $this->assertCount(0, $collection);
        $this->assertSame(['critical' => 0, 'warning' => 0, 'info' => 0], $collection->summary());
    }
}
