<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Scanner;

use Bss\UpgradePredictor\Model\ScanResult;

class DiXmlScanner
{
    public function scan(array $scanPaths): ScanResult
    {
        $entries = [];

        foreach ($scanPaths as $path) {
            $diFiles = $this->findDiXmlFiles($path);
            foreach ($diFiles as $file) {
                $entries = array_merge($entries, $this->parseFile($file));
            }
        }

        return new ScanResult('DiXmlScanner', $entries);
    }

    /** @return list<string> */
    private function findDiXmlFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $result = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'di.xml') {
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

        // Extract preferences
        $preferences = $xpath->query('//preference');
        if ($preferences !== false) {
            foreach ($preferences as $preference) {
                /** @var \DOMElement $preference */
                $for = $preference->getAttribute('for');
                $type = $preference->getAttribute('type');

                if ($for === '' || $type === '') {
                    continue;
                }

                $entries[] = [
                    'type' => 'preference',
                    'data' => [
                        'originalClass' => $for,
                        'customClass'   => $type,
                        'diXmlFile'     => $filePath,
                        'diXmlLine'     => $preference->getLineNo(),
                    ],
                ];
            }
        }

        // Extract plugins
        $plugins = $xpath->query('//type/plugin');
        if ($plugins !== false) {
            foreach ($plugins as $plugin) {
                /** @var \DOMElement $plugin */
                $pluginType = $plugin->getAttribute('type');
                $parent = $plugin->parentNode;

                if (!$parent instanceof \DOMElement) {
                    continue;
                }

                $targetClass = $parent->getAttribute('name');

                if ($targetClass === '' || $pluginType === '') {
                    continue;
                }

                $entries[] = [
                    'type' => 'plugin',
                    'data' => [
                        'targetClass' => $targetClass,
                        'pluginClass'  => $pluginType,
                        'diXmlFile'   => $filePath,
                        'diXmlLine'   => $plugin->getLineNo(),
                    ],
                ];
            }
        }

        return $entries;
    }
}
