<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Tests\Unit\Reporter;

use Bss\UpgradePredictor\Model\Issue;
use Bss\UpgradePredictor\Model\IssueCollection;
use Bss\UpgradePredictor\Model\Severity;
use Bss\UpgradePredictor\Reporter\GithubAnnotationReporter;
use Bss\UpgradePredictor\Reporter\JsonReporter;
use Bss\UpgradePredictor\Reporter\MarkdownReporter;
use PHPUnit\Framework\TestCase;

class ReporterTest extends TestCase
{
    private IssueCollection $issues;
    protected function setUp(): void
    {
        $this->issues = new IssueCollection([
            new Issue(Severity::CRITICAL, 'class-diff', 'app/code/Bss/Test/etc/di.xml', 15, 'Magento\\Catalog\\Model\\Product', 'Class removed', null, 'Find replacement'),
            new Issue(Severity::WARNING, 'template', 'app/design/frontend/Bss/theme/template.phtml', null, 'Magento_Catalog::product/view.phtml', 'Template changed', "- old\n+ new", 'Merge changes'),
        ]);
    }

    public function testMarkdownContainsSummaryAndIssues(): void
    {
        $output = (new MarkdownReporter())->render($this->issues, '2.4.9', '2.4.10');
        $this->assertStringContainsString('2.4.9', $output);
        $this->assertStringContainsString('2.4.10', $output);
        $this->assertStringContainsString('CRITICAL', $output);
        $this->assertStringContainsString('WARNING', $output);
        $this->assertStringContainsString('Class removed', $output);
        $this->assertStringContainsString('Template changed', $output);
    }

    public function testJsonIsValidAndComplete(): void
    {
        $output = (new JsonReporter())->render($this->issues, '2.4.9', '2.4.10');
        $data = json_decode($output, true);
        $this->assertNotNull($data);
        $this->assertSame('2.4.9', $data['from']);
        $this->assertSame('2.4.10', $data['to']);
        $this->assertSame(1, $data['summary']['critical']);
        $this->assertSame(1, $data['summary']['warning']);
        $this->assertCount(2, $data['issues']);
    }

    public function testGithubAnnotationsFormat(): void
    {
        $output = (new GithubAnnotationReporter())->render($this->issues, '2.4.9', '2.4.10');
        $this->assertStringContainsString('::error file=app/code/Bss/Test/etc/di.xml,line=15::', $output);
        $this->assertStringContainsString('::warning file=app/design/frontend/Bss/theme/template.phtml::', $output);
    }

    public function testEmptyCollectionOutput(): void
    {
        $empty = new IssueCollection([]);
        $md = (new MarkdownReporter())->render($empty, '2.4.9', '2.4.10');
        $this->assertStringContainsString('No issues found', $md);
        $json = (new JsonReporter())->render($empty, '2.4.9', '2.4.10');
        $data = json_decode($json, true);
        $this->assertSame(0, $data['summary']['critical']);
        $this->assertCount(0, $data['issues']);
    }
}
