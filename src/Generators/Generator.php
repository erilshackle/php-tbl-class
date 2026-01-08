<?php

namespace Eril\TblClass\Generators;

use Eril\TblClass\Cli\CliPrinter;
use Eril\TblClass\Config;
use Eril\TblClass\Introspection\GeneratedClassMetadata;
use Eril\TblClass\Introspection\Logger;
use Eril\TblClass\Introspection\SchemaHasher;
use Eril\TblClass\OutputWriter;
use Eril\TblClass\Resolvers\NamingResolver;
use Eril\TblClass\Schema\SchemaReaderInterface;

abstract class Generator
{
    private OutputWriter $output;
    protected NamingResolver $namingResolver;
    protected Logger $logger;


    public function __construct(
        protected SchemaReaderInterface $schema,
        protected Config $config,
        protected bool $checkMode = false,
        protected string $mode = 'class'
    ) {
        $this->output = new OutputWriter($this->config, new Logger());
        $this->namingResolver = new NamingResolver($config->getNamingConfig());
        $this->logger = new Logger();
    }

    public function run(): void
    {
        $tables = $this->schema->getTables();

        if (!$tables) {
            CliPrinter::error('No tables found');
            exit(1);
        }

        $foreignKeys = $this->schema->getForeignKeys();

        $schemaData = $this->buildSchemaHashData($tables, $foreignKeys);
        $hash = SchemaHasher::hash($schemaData);

        $content = $this->generateContent($tables, $foreignKeys, $hash);

        if ($this->checkMode) {
            $this->checkSchema($hash);
            return;
        }

        $this->output->ensureDirectory();
        $this->output->write(
            $content,
            $hash,
            count($tables),
            count($foreignKeys),
            $this->schema->getDatabaseName(),
            $this->mode
        );
    }

    abstract protected function generateContent(array $tables, array $foreignKeys = [], ?string $schemaHash = null): string;

    protected function buildSchemaHashData(array $tables, array $foreignKeys)
    {
        $schemaData = [
            'dbName' => $this->schema->getDatabaseName(),
            'tables' => [],
            'foreignKeys' => $foreignKeys
        ];

        foreach ($tables as $table) {
            $columns = $this->schema->getColumns($table);
            if (!empty($columns)) {
                $schemaData['tables'][$table] = $columns;
            }
        }

        // Ordenar para consistência
        ksort($schemaData['tables']);
        sort($schemaData['foreignKeys']);

        return $schemaData;
    }

    protected function checkSchema(string $currentHash): void
    {
        CliPrinter::title('Checking schema changes...');

        $outputFile = $this->config->getOutputFile($this->mode);
        $savedHash = GeneratedClassMetadata::extractSchemaHash($outputFile);

        if (empty($savedHash)) {
            $this->logger->log('CHECK', $currentHash, 'INITIAL');
            CliPrinter::warn('⚙ Initial generation required');
            exit(2);
        }

        if ($savedHash === $currentHash) {
            CliPrinter::success('🟢 Schema unchanged');
            exit(0);
        }

        $this->logger->log('CHECK', $currentHash, 'CHANGED');
        CliPrinter::error('❌ Schema changed');
        exit(1);
    }
}
