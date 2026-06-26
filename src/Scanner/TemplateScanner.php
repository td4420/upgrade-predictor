<?php
declare(strict_types=1);
namespace Bss\UpgradePredictor\Scanner;

use Bss\UpgradePredictor\Model\ScanResult;

class TemplateScanner
{
    public function __construct(private readonly string $projectRoot) {}

    public function scan(): ScanResult
    {
        $entries = [];

        $designDirs = [
            $this->projectRoot . '/app/design/frontend',
            $this->projectRoot . '/app/design/adminhtml',
        ];

        $pattern = '#/([A-Z][a-zA-Z0-9]*_[A-Z][a-zA-Z0-9]*)/templates/(.+\.phtml)$#';

        foreach ($designDirs as $designDir) {
            if (!is_dir($designDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($designDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'phtml') {
                    continue;
                }

                $filePath = $file->getPathname();

                if (preg_match($pattern, $filePath, $matches)) {
                    $entries[] = [
                        'type' => 'template',
                        'data' => [
                            'overridePath'     => $filePath,
                            'coreModuleName'   => $matches[1],
                            'coreTemplatePath' => $matches[2],
                        ],
                    ];
                }
            }
        }

        return new ScanResult('TemplateScanner', $entries);
    }
}
