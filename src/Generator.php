<?php

namespace Eril\TblClass;

use Eril\TblClass\Resolvers\NamingResolver;
use PDO;
use Exception;

class Generator
{
    protected const CLASS_NAME = 'Tbl';
    protected const STATE_DIR = '.tblclass/';
    protected const STATE_FILE = 'state.ini';
    protected Logger $logService;
    protected NamingResolver $namingResolver;

    protected PDO $pdo;
    protected Config $config;
    protected bool $checkMode;
    protected string $mode = 'class'; // 'class' ou 'global'
    protected string $dbName;

    public function __construct(PDO $pdo, Config $config, bool $checkMode = false, string $mode = 'class')
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->checkMode = $checkMode;
        $this->mode = $mode;
        $this->logService = new Logger();
        $this->dbName = $this->getDatabaseName();
        $this->namingResolver = new NamingResolver($config->getNamingConfig());

        // Ensure output directory exists
        $outputDir = $config->getOutputPath();
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
            throw new Exception("Cannot create output directory: $outputDir");
        }

        $this->ensureGitignore();
    }

    protected function getDatabaseName(): string
    {
        $driver = $this->config->getDriver();

        if ($driver === 'mysql') {
            return $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        }

        if ($driver === 'sqlite') {
            $path = $this->config->get('database.path', 'database.sqlite');
            return basename($path, '.sqlite');
        }

        return 'unknown';
    }

    public function run(): void
    {
        $tables = $this->fetchTables();

        if (empty($tables)) {
            $this->logService->log('ERROR', 'NO_TABLES', 'No tables found', '-');
            echo "🚫 No tables found in database\n";
            exit(1);
        }

        // SEMPRE buscar foreign keys
        $foreignKeys = $this->getAllForeignKeys();

        // Gerar conteúdo para arquivo
        $content = $this->generateContent($tables, $foreignKeys);

        // Gerar hash apenas dos dados do schema (não do arquivo formatado)
        $schemaData = $this->getSchemaDataForHash($tables, $foreignKeys);
        $hash = md5(serialize($schemaData));

        if ($this->checkMode) {
            $this->checkSchema($hash);
        } else {
            $this->generateFile($content, $hash, count($tables), count($foreignKeys));
        }
    }

    /**
     * Extrai apenas os dados do schema para gerar hash consistente
     * Sem timestamps, comentários ou formatação variável
     */
    protected function getSchemaDataForHash(array $tables, array $foreignKeys): array
    {
        $schemaData = [
            'dbName' => $this->dbName,
            'tables' => [],
            'foreignKeys' => $foreignKeys
        ];

        foreach ($tables as $table) {
            $columns = $this->getColumns($table);
            if (!empty($columns)) {
                $schemaData['tables'][$table] = $columns;
            }
        }

        // Ordenar para consistência
        ksort($schemaData['tables']);
        sort($schemaData['foreignKeys']);

        return $schemaData;
    }

    protected function fetchTables(): array
    {
        $driver = $this->config->getDriver();

        if ($driver === 'mysql') {
            $stmt = $this->pdo->prepare("
                SELECT TABLE_NAME 
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'
                ORDER BY TABLE_NAME
            ");
            $stmt->execute([$this->dbName]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->query("
                SELECT name 
                FROM sqlite_master 
                WHERE type = 'table' AND name NOT LIKE 'sqlite_%'
                ORDER BY name
            ");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        return [];
    }

    protected function getColumns(string $table): array
    {
        $driver = $this->config->getDriver();

        if ($driver === 'mysql') {
            $stmt = $this->pdo->prepare("
                SELECT COLUMN_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                ORDER BY ORDINAL_POSITION
            ");
            $stmt->execute([$this->dbName, $table]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if ($driver === 'sqlite') {
            $stmt = $this->pdo->prepare("PRAGMA table_info(?)");
            $stmt->execute([$table]);
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return array_column($columns, 'name');
        }

        return [];
    }

    protected function getAllForeignKeys(): array
    {
        $driver = $this->config->getDriver();
        $foreignKeys = [];

        if ($driver === 'mysql') {
            $stmt = $this->pdo->prepare("
                SELECT 
                    TABLE_NAME as from_table,
                    COLUMN_NAME as from_column,
                    REFERENCED_TABLE_NAME as to_table,
                    REFERENCED_COLUMN_NAME as to_column
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = ?
                AND REFERENCED_TABLE_NAME IS NOT NULL
                ORDER BY TABLE_NAME, COLUMN_NAME
            ");
            $stmt->execute([$this->dbName]);
            $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($driver === 'sqlite') {
            $tables = $this->fetchTables();

            foreach ($tables as $table) {
                $stmt = $this->pdo->prepare("PRAGMA foreign_key_list(:table)");
                $stmt->execute([':table' => $table]);
                $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($fks as $fk) {
                    $foreignKeys[] = [
                        'from_table' => $table,
                        'from_column' => $fk['from'],
                        'to_table' => $fk['table'],
                        'to_column' => $fk['to']
                    ];
                }
            }

            // Ordenar para consistência
            usort($foreignKeys, function ($a, $b) {
                $keyA = $a['from_table'] . '|' . $a['from_column'] . '|' . $a['to_table'];
                $keyB = $b['from_table'] . '|' . $b['from_column'] . '|' . $b['to_table'];
                return strcmp($keyA, $keyB);
            });
        }

        return $foreignKeys;
    }

    /**
    * @todo will return enum contants valus to be place in Tbl::class as tbl_table_column_value
    */
    protected function getEnumConstants($tableName)
    {
        $sql = "SELECT 
                    COLUMN_NAME,
                    COLUMN_TYPE
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = ? 
                    AND DATA_TYPE = 'enum'";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$tableName]);
        $enums = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $constants = [];
        foreach ($enums as $enum) {
            $columnName = $enum['COLUMN_NAME'];
            $enumString = $enum['COLUMN_TYPE'];
            $enumString = substr($enumString, 5, -1);
            $values = str_getcsv($enumString, ",", "'");
            
            foreach ($values as $value) {
                $constantName = strtoupper($tableName . '_' . $columnName . '_' . $value);
                $constantName = preg_replace('/[^A-Z0-9_]/', '_', $constantName);
                $constants[$constantName] = $value;
            }
        }
        
        return $constants;
    }

    protected function generateContent(array $tables, array $foreignKeys = []): string
    {
        // Método abstrato para classes filhas
        return '';
    }

    protected function checkSchema(string $currentHash): void
    {
        $stateFile = $this->getStateFile();

        echo "🔍 Checking schema changes...\n";

        $savedHash = '';
        if (file_exists($stateFile)) {
            $config = parse_ini_file($stateFile);
            $savedHash = $config['hash'] ?? '';
        }

        if ($savedHash === $currentHash) {
            $this->logService->log('CHECK', $currentHash, 'UNCHANGED', $this->dbName);
            echo "🟢  Schema unchanged\n";
            exit(0);
        }

        // Update state file
        $stateDir = dirname($stateFile);
        if (!is_dir($stateDir)) {
            mkdir($stateDir, 0755, true);
        }

        $content = "; Schema state\n";
        $content .= "hash=\"$currentHash\"\n";
        $content .= "checked=\"" . date('Y-m-d H:i:s') . "\"\n";

        file_put_contents($stateFile, $content);

        if (empty($savedHash)) {
            $this->logService->log('CHECK', $currentHash, 'INITIAL', $this->dbName);
            echo "⚙ Initial schema snapshot saved\n";
        } else {
            $this->logService->log('CHECK', $currentHash, 'CHANGED', $this->dbName);
            echo "❌ Schema changed!\n";
        }
        exit(1);
    }

    protected function generateFile(string $content, string $hash, int $tableCount, int $fkCount = 0): void
    {
        $outputFile = $this->config->getOutputFile($this->mode);

        if (file_put_contents($outputFile, $content)) {
            $this->logService->log('GENERATE', $hash, 'OK', $this->dbName);

            echo "✔️  Generated: $outputFile\n";
            echo "   Tables: $tableCount\n";
            if ($fkCount > 0) {
                echo "   Foreign Keys: $fkCount\n";
            }
            echo "   Database: {$this->dbName}\n";

            // Save state
            $stateDir = dirname($this->getStateFile());
            if (!is_dir($stateDir)) {
                mkdir($stateDir, 0755, true);
            }

            $stateContent = "; Generated state\n";
            $stateContent .= "hash=\"$hash\"\n";
            $stateContent .= "generated=\"" . date('Y-m-d H:i:s') . "\"\n";

            file_put_contents($this->getStateFile(), $stateContent);

            $this->showInstructions();
        } else {
            $this->logService->log('ERROR', 'WRITE_FAILED', $outputFile, $this->dbName);
            throw new Exception("Failed to write: $outputFile");
        }
    }

    protected function showInstructions(): void
    {
        // Verificar se a classe Tbl já existe (apenas modo class)
        if ($this->mode === 'class') {
            $className = $this->config->get('output.namespace') ?
                trim($this->config->get('output.namespace'), '\\') . '\\Tbl' : 'Tbl';

            if (class_exists($className)) {
                return;
            }
        }

        $namespace = $this->config->get('output.namespace');
        $outputFile = $this->config->getOutputFile($this->mode);
        $relativePath = str_replace(getcwd() . '/', '', $outputFile);

        echo "\n💡 To use Tbl globally, add to composer.json:\n";

        if ($this->mode === 'class' && $namespace) {
            echo "   \"autoload\": {\n";
            echo "       \"psr-4\": {\n";
            echo "           \"$namespace\\\\\": \"" . dirname($relativePath) . "\"\n";
            echo "       }\n";
        } else {
            echo "   \"autoload\": {\n";
            echo "       \"files\": [\"$relativePath\"]\n";
            echo "   }\n";
        }

        echo "\n   Then run: composer dump-autoload\n";
        echo str_repeat('-', 50) . "\r";
    }

    protected function getStateFile(): string
    {
        $stateFile = self::STATE_FILE;

        // State files diferentes por modo
        if ($this->mode === 'global') {
            $stateFile = 'global_' . $stateFile;
        }

        return getcwd() . '/' . self::STATE_DIR . $stateFile;
    }

    private function ensureGitignore(): void
    {
        $gitignorePath = getcwd() . '/' . self::STATE_DIR . '/.gitignore';

        if (!file_exists($gitignorePath)) {
            $dir = dirname($gitignorePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $content = "# Generated by tbl-class\n# Ignore all files in this directory\n*\n!.gitignore\n";
            file_put_contents($gitignorePath, $content);
        }
    }
}
