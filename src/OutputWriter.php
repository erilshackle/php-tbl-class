<?php

namespace Eril\TblClass;

use Eril\TblClass\Cli\CliPrinter;
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

        CliPrinter::success("Generated: $file");
        CliPrinter::line("  > Tables: $tables");
        CliPrinter::line("  > Foreign Keys: $foreignKeys");
        CliPrinter::line("  > Database: $dbName");

        $this->printInstructions($mode);
    }

    private function printInstructions(string $mode): void
    {
        if (in_array($mode, ['class', 'schemas'])) {
            $className = $this->config->get('output.namespace') ?
                trim($this->config->get('output.namespace'), '\\') . '\\Tbl' : 'Tbl';

            if (class_exists($className)) {
                return;
            }
        }

        $namespace = $this->config->get('output.namespace');
        $outputFile = $this->config->getOutputFile($mode);
        $relativePath = str_replace(getcwd() . '/', '', $outputFile);

        CliPrinter::line("\n💡 To use Tbl:: globally, add to composer.json:", 'cyan');
        CliPrinter::line("We recommend using Composer autoload [\"file\"].", 'cyan');

        $out = '';

        if (in_array($mode, ['class', 'schemas'])) {
            if ($namespace) {
                $out .= "   \"autoload\": {\n";
                $out .= "       \"psr-4\": {\n";
                $out .= "           \"$namespace\\\\\": \"" . dirname($relativePath) . "\"\n";
                $out .= "       }\n";
            } else {
                $out .= "   \"autoload\": {\n";
                $out .= "       \"files\": [\n";
                $out .= "           \"" . $relativePath . "\"\n";
                $out .= "       ]\n";
                $out .= "   }\n";
            }
        } else {
            $out .= "   \"autoload\": {\n";
            $out .= "       \"files\": [\"$relativePath\"]\n";
            $out .= "   }\n";
        }

        $out .= "\n$ Then run: composer dump-autoload\n";
        $out .= str_repeat('-', 50) . "\r";
        CliPrinter::line($out);
    }
}
