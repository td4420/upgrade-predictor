<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Analyzer;

use Bss\UpgradePredictor\Model\Issue;
use Bss\UpgradePredictor\Model\IssueCollection;
use Bss\UpgradePredictor\Model\ScanResult;
use Bss\UpgradePredictor\Model\Severity;
use Bss\UpgradePredictor\Model\Snapshot;

class PluginAnalyzer implements AnalyzerInterface
{
    public function getName(): string
    {
        return 'plugin';
    }

    public function analyze(Snapshot $snapshot, ScanResult $scanResult): IssueCollection
    {
        $issues = [];

        foreach ($scanResult->filterByType('plugin')->entries as $entry) {
            $data        = $entry['data'];
            $targetClass = $data['targetClass'];
            $pluginClass = $data['pluginClass'];
            $diXmlFile   = $data['diXmlFile'];
            $diXmlLine   = $data['diXmlLine'];

            // Target class removed
            if (!$snapshot->newClassMap->hasClass($targetClass)) {
                $issues[] = new Issue(
                    severity:    Severity::CRITICAL,
                    analyzer:    $this->getName(),
                    sourceFile:  $diXmlFile,
                    sourceLine:  $diXmlLine,
                    targetClass: $targetClass,
                    message:     "Target class '{$targetClass}' has been removed. Plugin '{$pluginClass}' will never be executed.",
                );
                continue;
            }

            // Resolve plugin methods — use injected list (for tests) or discover from file
            $pluginMethods = $data['pluginMethods'] ?? $this->discoverPluginMethods($pluginClass);

            foreach ($pluginMethods as $pluginMethodName) {
                $parsed = $this->parsePluginMethod($pluginMethodName);
                if ($parsed === null) {
                    continue;
                }

                [$prefix, $originalMethod] = $parsed;

                $oldMethod = $snapshot->oldClassMap->getMethod($targetClass, $originalMethod);
                $newMethod = $snapshot->newClassMap->getMethod($targetClass, $originalMethod);

                // Method removed → plugin never called
                if ($oldMethod !== null && $newMethod === null) {
                    $issues[] = new Issue(
                        severity:    Severity::CRITICAL,
                        analyzer:    $this->getName(),
                        sourceFile:  $diXmlFile,
                        sourceLine:  $diXmlLine,
                        targetClass: $targetClass,
                        message:     "Method '{$originalMethod}' was removed from '{$targetClass}'. Plugin '{$pluginClass}::{$pluginMethodName}' will never be called.",
                    );
                    continue;
                }

                if ($oldMethod === null || $newMethod === null) {
                    continue;
                }

                // Parameter count changed
                $oldCount = count($oldMethod['params'] ?? []);
                $newCount = count($newMethod['params'] ?? []);

                if ($oldCount !== $newCount) {
                    $severity = ($prefix === 'after') ? Severity::WARNING : Severity::CRITICAL;
                    $issues[] = new Issue(
                        severity:    $severity,
                        analyzer:    $this->getName(),
                        sourceFile:  $diXmlFile,
                        sourceLine:  $diXmlLine,
                        targetClass: $targetClass,
                        message:     "Method '{$originalMethod}' in '{$targetClass}' changed parameter count from {$oldCount} to {$newCount}. Plugin '{$pluginClass}::{$pluginMethodName}' may be incompatible.",
                    );
                }
            }
        }

        return new IssueCollection($issues);
    }

    /**
     * Parse a plugin method name into [prefix, originalMethodName].
     * e.g. aroundSetPrice → ['around', 'setPrice']
     *      beforeSave     → ['before', 'save']
     *      afterGetName   → ['after', 'getName']
     *
     * @return array{string, string}|null
     */
    private function parsePluginMethod(string $pluginMethodName): ?array
    {
        foreach (['around', 'before', 'after'] as $prefix) {
            if (str_starts_with($pluginMethodName, $prefix)) {
                $rest = substr($pluginMethodName, strlen($prefix));
                if ($rest === '') {
                    continue;
                }
                $originalMethod = lcfirst($rest);
                return [$prefix, $originalMethod];
            }
        }
        return null;
    }

    /**
     * Discover plugin methods from a plugin class file via reflection/regex.
     * Used in production when pluginMethods is not injected.
     *
     * @return list<string>
     */
    private function discoverPluginMethods(string $pluginClass): array
    {
        // Try to autoload the class
        if (!class_exists($pluginClass, true)) {
            return [];
        }

        try {
            $ref     = new \ReflectionClass($pluginClass);
            $methods = [];
            foreach ($ref->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $name = $method->getName();
                foreach (['around', 'before', 'after'] as $prefix) {
                    if (str_starts_with($name, $prefix)) {
                        $methods[] = $name;
                        break;
                    }
                }
            }
            return $methods;
        } catch (\ReflectionException) {
            return [];
        }
    }
}
