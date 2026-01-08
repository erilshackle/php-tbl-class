<?php

namespace Eril\TblClass;

use Eril\TblClass\Introspection\GeneratedClassMetadata;
use Eril\TblClass\Introspection\Logger;
use RuntimeException;

class OutputWriter
{
    public function __construct(
        private Config $config,
        private Logger $logger
    ) {}

    public function ensureDirectory(): void
    {
        $dir = $this->config->getOutputPath();

        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Cannot create output directory: $dir");
        }
    }

    public function echo(string $text, $color = null)
    {
        $map = [];
        $color = $map[$color] ?? '';
        $reset = $map['reset'] ?? '';
        echo $map[$color] . $text . $reset;
    }

    public function write(
        string $content,
        string $hash,
        int $tables,
        int $foreignKeys,
        string $dbName,
        string $mode
    ): void {
        $file = $this->config->getOutputFile($mode);

        if (!file_put_contents($file, $content)) {
            throw new RuntimeException("Failed to write: $file");
        }

        $this->logger->log('GENERATE', $hash, 'OK', $dbName);

        echo "✔️  Generated: $file\n";
        echo "   Tables: $tables\n";
        echo "   Foreign Keys: $foreignKeys\n";
        echo "   Database: $dbName\n";

        $this->printInstructions($mode);
    }

    public function hashCheckResult(string $currentHash, string $savedHash): void
    {
        if ($savedHash === $currentHash) {
            echo "🟢 Schema unchanged\n";
        } else {
            if (empty($savedHash)) {
                $this->logger->log('CHECK', $currentHash, 'INITIAL');
                echo "⚙ Initial schema snapshot saved\n";
            } else {
                $this->logger->log('CHECK', $currentHash, 'CHANGED');
                echo "❌ Schema changed!\n";
            }
        }
    }

    private function printInstructions(string $mode): void
    {
        // if (in_array($this->mode, ['class', 'schemas'])) {
        //     $className = $this->config->get('output.namespace') ?
        //         trim($this->config->get('output.namespace'), '\\') . '\\Tbl' : 'Tbl';

        //     if (class_exists($className)) {
        //         return;
        //     }
        // }

        // $namespace = $this->config->get('output.namespace');
        // $outputFile = $this->config->getOutputFile($this->mode);
        // $relativePath = str_replace(getcwd() . '/', '', $outputFile);

        // echo "\n💡 To use Tbl globally, add to composer.json:\n";

        // if (in_array($this->mode, ['class', 'schemas'])) {
        //     if($namespace){
        //         echo "   \"autoload\": {\n";
        //         echo "       \"psr-4\": {\n";
        //         echo "           \"$namespace\\\\\": \"" . dirname($relativePath) . "\"\n";
        //         echo "       }\n";
        //     } else {
        //         echo "   \"autoload\": {\n";
        //         echo "       \"files\": [\n";
        //         echo "           \"". $relativePath . "\"\n";
        //         echo "       ]\n";
        //         echo "   }\n";
        //     }
        // } else {
        //     echo "   \"autoload\": {\n";
        //     echo "       \"files\": [\"$relativePath\"]\n";
        //     echo "   }\n";
        // }

        // echo "\n   Then run: composer dump-autoload\n";
        // echo str_repeat('-', 50) . "\r";
    }
}
