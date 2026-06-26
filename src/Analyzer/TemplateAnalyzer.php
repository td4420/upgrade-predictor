<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Analyzer;

use Bss\UpgradePredictor\Model\Issue;
use Bss\UpgradePredictor\Model\IssueCollection;
use Bss\UpgradePredictor\Model\ScanResult;
use Bss\UpgradePredictor\Model\Severity;
use Bss\UpgradePredictor\Model\Snapshot;

class TemplateAnalyzer implements AnalyzerInterface
{
    private const AREAS = ['frontend', 'adminhtml', 'base'];

    public function __construct(
        private readonly string $oldVendorPath,
        private readonly string $newVendorPath,
    ) {}

    public function getName(): string
    {
        return 'template';
    }

    public function analyze(Snapshot $snapshot, ScanResult $scanResult): IssueCollection
    {
        $issues = [];

        foreach ($scanResult->filterByType('template')->entries as $entry) {
            $data             = $entry['data'];
            $overridePath     = $data['overridePath'];
            $coreModuleName   = $data['coreModuleName'];
            $coreTemplatePath = $data['coreTemplatePath'];

            $moduleDir = $this->moduleNameToDir($coreModuleName);

            $oldFile = $this->resolveTemplatePath($this->oldVendorPath, $moduleDir, $coreTemplatePath);
            $newFile = $this->resolveTemplatePath($this->newVendorPath, $moduleDir, $coreTemplatePath);

            // Old doesn't exist → not a real core override, skip
            if ($oldFile === null) {
                continue;
            }

            // New doesn't exist → template removed
            if ($newFile === null) {
                $issues[] = new Issue(
                    severity:    Severity::WARNING,
                    analyzer:    $this->getName(),
                    sourceFile:  $overridePath,
                    sourceLine:  null,
                    targetClass: $coreModuleName,
                    message:     "Core template '{$coreTemplatePath}' from '{$coreModuleName}' was removed in the new version. Override '{$overridePath}' may be orphaned.",
                );
                continue;
            }

            // Both exist — compare
            $oldContent = file_get_contents($oldFile);
            $newContent = file_get_contents($newFile);

            if ($oldContent === $newContent) {
                continue;
            }

            $diff = $this->generateDiff($oldFile, $newFile);

            $issues[] = new Issue(
                severity:    Severity::WARNING,
                analyzer:    $this->getName(),
                sourceFile:  $overridePath,
                sourceLine:  null,
                targetClass: $coreModuleName,
                message:     "Core template '{$coreTemplatePath}' from '{$coreModuleName}' has changed. Review override '{$overridePath}' for compatibility.",
                diff:        $diff,
            );
        }

        return new IssueCollection($issues);
    }

    /**
     * Convert Magento_Catalog → magento/module-catalog
     * ConfigurableProduct → configurable-product
     */
    private function moduleNameToDir(string $moduleName): string
    {
        // Split on underscore: Magento_Catalog → ['Magento', 'Catalog']
        $parts = explode('_', $moduleName, 2);
        if (count($parts) !== 2) {
            return strtolower($moduleName);
        }

        [$vendor, $module] = $parts;

        // Convert CamelCase module name to kebab-case: ConfigurableProduct → configurable-product
        $kebabModule = $this->camelToKebab($module);

        return strtolower($vendor) . '/module-' . $kebabModule;
    }

    private function camelToKebab(string $input): string
    {
        // Insert hyphen before each uppercase letter that follows a lowercase letter or digit
        $result = preg_replace('/([a-z\d])([A-Z])/', '$1-$2', $input);
        return strtolower($result ?? $input);
    }

    /**
     * Try each area (frontend, adminhtml, base) to find the template file.
     */
    private function resolveTemplatePath(string $vendorPath, string $moduleDir, string $templatePath): ?string
    {
        foreach (self::AREAS as $area) {
            $fullPath = rtrim($vendorPath, '/') . '/' . $moduleDir . '/view/' . $area . '/templates/' . $templatePath;
            if (file_exists($fullPath)) {
                return $fullPath;
            }
        }
        return null;
    }

    private function generateDiff(string $oldFile, string $newFile): string
    {
        // Use system diff if available, otherwise produce a simple inline diff
        $command = 'diff -u ' . escapeshellarg($oldFile) . ' ' . escapeshellarg($newFile) . ' 2>/dev/null';
        $output  = shell_exec($command);
        if ($output !== null && $output !== '') {
            return $output;
        }

        // Fallback: show old/new content
        $old = file_get_contents($oldFile) ?: '';
        $new = file_get_contents($newFile) ?: '';
        return "--- old\n+++ new\n- " . implode("\n- ", explode("\n", $old))
            . "\n+ " . implode("\n+ ", explode("\n", $new));
    }
}
