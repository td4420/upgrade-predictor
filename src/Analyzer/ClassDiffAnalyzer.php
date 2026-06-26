<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Analyzer;

use Bss\UpgradePredictor\Model\Issue;
use Bss\UpgradePredictor\Model\IssueCollection;
use Bss\UpgradePredictor\Model\ScanResult;
use Bss\UpgradePredictor\Model\Severity;
use Bss\UpgradePredictor\Model\Snapshot;

class ClassDiffAnalyzer implements AnalyzerInterface
{
    public function getName(): string
    {
        return 'class-diff';
    }

    public function analyze(Snapshot $snapshot, ScanResult $scanResult): IssueCollection
    {
        $issues = [];

        foreach ($scanResult->filterByType('preference')->entries as $entry) {
            $data = $entry['data'];
            $originalClass = $data['originalClass'];
            $customClass   = $data['customClass'];
            $diXmlFile     = $data['diXmlFile'];
            $diXmlLine     = $data['diXmlLine'];

            // 1. Class removed in new version
            if (!$snapshot->newClassMap->hasClass($originalClass)) {
                $issues[] = new Issue(
                    severity:   Severity::CRITICAL,
                    analyzer:   $this->getName(),
                    sourceFile: $diXmlFile,
                    sourceLine: $diXmlLine,
                    targetClass: $originalClass,
                    message:    "Class '{$originalClass}' has been removed in the new version. Preference '{$customClass}' may be broken.",
                );
                continue;
            }

            // 2. Compare methods between old and new class maps
            $oldMethods = $snapshot->oldClassMap->getMethods($originalClass) ?? [];
            $newMethods = $snapshot->newClassMap->getMethods($originalClass) ?? [];

            foreach ($oldMethods as $methodName => $oldMethod) {
                // Method removed
                if (!isset($newMethods[$methodName])) {
                    $issues[] = new Issue(
                        severity:   Severity::CRITICAL,
                        analyzer:   $this->getName(),
                        sourceFile: $diXmlFile,
                        sourceLine: $diXmlLine,
                        targetClass: $originalClass,
                        message:    "Method '{$methodName}' was removed from '{$originalClass}'. Preference '{$customClass}' overrides a method that no longer exists.",
                    );
                    continue;
                }

                $newMethod = $newMethods[$methodName];

                // Parameter count changed
                $oldParamCount = count($oldMethod['params'] ?? []);
                $newParamCount = count($newMethod['params'] ?? []);
                if ($oldParamCount !== $newParamCount) {
                    $issues[] = new Issue(
                        severity:   Severity::CRITICAL,
                        analyzer:   $this->getName(),
                        sourceFile: $diXmlFile,
                        sourceLine: $diXmlLine,
                        targetClass: $originalClass,
                        message:    "Method '{$methodName}' in '{$originalClass}' changed parameter count from {$oldParamCount} to {$newParamCount}. Preference '{$customClass}' may be incompatible.",
                    );
                    continue;
                }

                // Parameter type changed
                $oldParams = $oldMethod['params'] ?? [];
                $newParams = $newMethod['params'] ?? [];
                $paramTypeChanged = false;
                foreach ($oldParams as $i => $oldParam) {
                    if (($oldParam['type'] ?? '') !== ($newParams[$i]['type'] ?? '')) {
                        $issues[] = new Issue(
                            severity:   Severity::CRITICAL,
                            analyzer:   $this->getName(),
                            sourceFile: $diXmlFile,
                            sourceLine: $diXmlLine,
                            targetClass: $originalClass,
                            message:    "Method '{$methodName}' in '{$originalClass}' parameter '{$oldParam['name']}' type changed. Preference '{$customClass}' may be incompatible.",
                        );
                        $paramTypeChanged = true;
                        break;
                    }
                }
                if ($paramTypeChanged) {
                    continue;
                }

                // Return type changed
                $oldReturn = $oldMethod['returnType'] ?? '';
                $newReturn = $newMethod['returnType'] ?? '';
                if ($oldReturn !== $newReturn) {
                    $issues[] = new Issue(
                        severity:   Severity::WARNING,
                        analyzer:   $this->getName(),
                        sourceFile: $diXmlFile,
                        sourceLine: $diXmlLine,
                        targetClass: $originalClass,
                        message:    "Method '{$methodName}' in '{$originalClass}' return type changed from '{$oldReturn}' to '{$newReturn}'. Preference '{$customClass}' may need updating.",
                    );
                }
            }
        }

        return new IssueCollection($issues);
    }
}
