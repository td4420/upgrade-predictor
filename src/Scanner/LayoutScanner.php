<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Scanner;

use Bss\UpgradePredictor\Model\ScanResult;

class LayoutScanner
{
    public function scan(array $scanPaths): ScanResult
    {
        $entries = [];

        foreach ($scanPaths as $path) {
            $xmlFiles = $this->findXmlFiles($path);
            foreach ($xmlFiles as $file) {
                $entries = array_merge($entries, $this->parseFile($file));
            }
        }

        return new ScanResult('LayoutScanner', $entries);
    }

    /** @return list<string> */
    private function findXmlFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $result = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'xml') {
                $result[] = $file->getPathname();
            }
        }

        return $result;
    }

    /** @return list<array{type: string, data: array<string, mixed>}> */
    private function parseFile(string $filePath): array
    {
        $entries = [];

        $previousUseInternalErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        $loaded = $dom->load($filePath);

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);

        // Extract referenceBlock elements
        $referenceBlocks = $xpath->query('//referenceBlock[@name]');
        if ($referenceBlocks !== false) {
            foreach ($referenceBlocks as $node) {
                /** @var \DOMElement $node */
                $entries[] = [
                    'type' => 'reference',
                    'data' => [
                        'referenceType' => 'block',
                        'referenceName' => $node->getAttribute('name'),
                        'layoutFile'    => $filePath,
                        'layoutLine'    => $node->getLineNo(),
                    ],
                ];
            }
        }

        // Extract referenceContainer elements
        $referenceContainers = $xpath->query('//referenceContainer[@name]');
        if ($referenceContainers !== false) {
            foreach ($referenceContainers as $node) {
                /** @var \DOMElement $node */
                $entries[] = [
                    'type' => 'reference',
                    'data' => [
                        'referenceType' => 'container',
                        'referenceName' => $node->getAttribute('name'),
                        'layoutFile'    => $filePath,
                        'layoutLine'    => $node->getLineNo(),
                    ],
                ];
            }
        }

        // Extract move elements
        $moves = $xpath->query('//move[@element]');
        if ($moves !== false) {
            foreach ($moves as $node) {
                /** @var \DOMElement $node */
                $entries[] = [
                    'type' => 'move',
                    'data' => [
                        'element'     => $node->getAttribute('element'),
                        'destination' => $node->getAttribute('destination'),
                        'layoutFile'  => $filePath,
                        'layoutLine'  => $node->getLineNo(),
                    ],
                ];
            }
        }

        return $entries;
    }
}
