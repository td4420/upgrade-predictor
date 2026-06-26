<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Analyzer;

use Bss\UpgradePredictor\Model\Issue;
use Bss\UpgradePredictor\Model\IssueCollection;
use Bss\UpgradePredictor\Model\ScanResult;
use Bss\UpgradePredictor\Model\Severity;
use Bss\UpgradePredictor\Model\Snapshot;

class LayoutAnalyzer implements AnalyzerInterface
{
    /** @var array<string, true>|null */
    private ?array $registry = null;

    public function __construct(
        private readonly string $newVendorPath,
    ) {}

    public function getName(): string
    {
        return 'layout';
    }

    public function analyze(Snapshot $snapshot, ScanResult $scanResult): IssueCollection
    {
        $registry = $this->getRegistry();
        $issues   = [];

        foreach ($scanResult->entries as $entry) {
            $type = $entry['type'];
            $data = $entry['data'];

            if ($type === 'reference') {
                $referenceName = $data['referenceName'];
                $layoutFile    = $data['layoutFile'];
                $layoutLine    = $data['layoutLine'];

                if (!isset($registry[$referenceName])) {
                    $issues[] = new Issue(
                        severity:    Severity::WARNING,
                        analyzer:    $this->getName(),
                        sourceFile:  $layoutFile,
                        sourceLine:  $layoutLine,
                        targetClass: $referenceName,
                        message:     "Layout reference '{$referenceName}' does not exist in the new version's layout registry.",
                    );
                }
            } elseif ($type === 'move') {
                $element     = $data['element'];
                $destination = $data['destination'];
                $layoutFile  = $data['layoutFile'];
                $layoutLine  = $data['layoutLine'];

                if (!isset($registry[$element])) {
                    $issues[] = new Issue(
                        severity:    Severity::WARNING,
                        analyzer:    $this->getName(),
                        sourceFile:  $layoutFile,
                        sourceLine:  $layoutLine,
                        targetClass: $element,
                        message:     "Move element '{$element}' does not exist in the new version's layout registry.",
                    );
                }

                if (!isset($registry[$destination])) {
                    $issues[] = new Issue(
                        severity:    Severity::WARNING,
                        analyzer:    $this->getName(),
                        sourceFile:  $layoutFile,
                        sourceLine:  $layoutLine,
                        targetClass: $destination,
                        message:     "Move destination '{$destination}' does not exist in the new version's layout registry.",
                    );
                }
            }
        }

        return new IssueCollection($issues);
    }

    /**
     * Build (and cache) the registry of all block/container names found in layout XMLs.
     *
     * @return array<string, true>
     */
    private function getRegistry(): array
    {
        if ($this->registry !== null) {
            return $this->registry;
        }

        $this->registry = [];
        $layoutFiles    = $this->findLayoutFiles($this->newVendorPath);

        foreach ($layoutFiles as $file) {
            $this->parseLayoutFile($file, $this->registry);
        }

        return $this->registry;
    }

    /** @return list<string> */
    private function findLayoutFiles(string $vendorPath): array
    {
        if (!is_dir($vendorPath)) {
            return [];
        }

        $result   = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($vendorPath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'xml') {
                continue;
            }

            // Only files under */layout/*
            $pathname = $file->getPathname();
            if (!preg_match('#[/\\\\]layout[/\\\\]#', $pathname)) {
                continue;
            }

            $result[] = $pathname;
        }

        return $result;
    }

    /**
     * @param array<string, true> $registry
     */
    private function parseLayoutFile(string $filePath, array &$registry): void
    {
        $previousUseInternalErrors = libxml_use_internal_errors(true);

        $dom    = new \DOMDocument();
        $loaded = $dom->load($filePath);

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return;
        }

        $xpath = new \DOMXPath($dom);

        // Collect all elements that have a "name" attribute (block, container, referenceBlock, referenceContainer, etc.)
        $namedNodes = $xpath->query('//*[@name]');
        if ($namedNodes === false) {
            return;
        }

        foreach ($namedNodes as $node) {
            /** @var \DOMElement $node */
            $name = $node->getAttribute('name');
            if ($name !== '') {
                $registry[$name] = true;
            }
        }
    }
}
