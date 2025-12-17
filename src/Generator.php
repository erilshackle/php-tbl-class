<?php
// src/Generator.php

namespace Eril\TblClass;

use PDO;
use Exception;

class Generator
{
    private const CLASS_NAME = 'Tbl';
    private const STATE_DIR = '.tblclass/';
    private const STATE_FILE = 'state.ini';
    private Logger $logService;

    private PDO $pdo;
    private Config $config;
    private bool $checkMode;
    private string $dbName;

    public function __construct(PDO $pdo, Config $config, bool $checkMode = false)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->checkMode = $checkMode;
        $this->logService = new Logger();
        $this->dbName = $this->getDatabaseName();

        // Ensure output directory exists
        $outputDir = $config->getOutputPath();
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true)) {
            throw new Exception("Cannot create output directory: $outputDir");
        }
    }

    private function getDatabaseName(): string
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
            $this->logService->log('ERROR', 'NO_TABLES', 'No tables found');
            echo "🚫 No tables found in database\n";
            exit(1);
        }

        $content = $this->generateClass($tables);
        $hash = md5($content);

        if ($this->checkMode) {
            $this->checkSchema($hash);
        } else {
            $this->generateFile($content, $hash, count($tables));
        }
    }

    private function fetchTables(): array
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

    private function getColumns(string $table): array
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

    private function generateClass(array $tables): string
    {
        $namespace = $this->config->get('output.namespace');

        $content = "<?php\n\n";

        if ($namespace) {
            $content .= "namespace ". trim($namespace, '\\') .";\n\n";
        }

        $content .= "/**\n";
        $content .= " * Database table constants\n";
        $content .= " * - Schema: {$this->dbName}\n";
        $content .= " * - Date: " . date('Y-m-d H:i:s') . "\n";
        $content .= " */\n";
        $content .= "class " . self::CLASS_NAME . "\n{\n";

        foreach ($tables as $table) {
            $columns = $this->getColumns($table);

            if (empty($columns)) {
                continue;
            }

            $prefix = strtolower($table);
            $content .= "\n    // Table: $table\n";
            $content .= "    public const $prefix = '$table';\n";

            foreach ($columns as $column) {
                $const = $prefix . '_' . strtolower($column);
                $content .= "    public const $const = '$column';\n";
            }
        }

        $content .= "}\n";
        return $content;
    }

    private function checkSchema(string $currentHash): void
    {
        $stateFile = $this->getStateFile();

        echo "🔍 Checking schema changes...\n";

        $savedHash = '';
        if (file_exists($stateFile)) {
            $config = parse_ini_file($stateFile);
            $savedHash = $config['hash'] ?? '';
        }

        if ($savedHash === $currentHash) {
            $this->logService->log('CHECK', $currentHash, 'UNCHANGED');
            echo "✅ Schema unchanged\n";
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
            $this->logService->log('CHECK', $currentHash, 'INITIAL');
            echo "📝 Initial schema snapshot saved\n";
        } else {
            $this->logService->log('CHECK', $currentHash, 'CHANGED');
            echo "❌ Schema changed!\n";
        }
        exit(1);
    }

    private function generateFile(string $content, string $hash, int $tableCount): void
    {
        $outputFile = $this->config->getOutputFile();

        if (file_put_contents($outputFile, $content)) {
            $this->logService->log('GENERATE', $hash, 'OK');

            echo "✅ Generated: $outputFile\n";
            echo "   Tables: $tableCount\n";
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
            $this->logService->log('ERROR', 'WRITE_FAILED', $outputFile);
            throw new Exception("Failed to write: $outputFile");
        }
    }

    private function showInstructions(): void
    {
        // Verificar se a classe Tbl já existe
        if (class_exists('Tbl') || ($this->config->get('output.namespace') && class_exists($this->config->get('output.namespace') . '\\Tbl'))) {
            // Classe já carregada, não precisa mostrar instruções
            return;
        }
        
        $namespace = $this->config->get('output.namespace');
        $outputFile = $this->config->getOutputFile();
        $relativePath = str_replace(getcwd() . '/', '', $outputFile);
    
        echo "\n📚 Autoload setup:\n\n";
    
        if ($namespace) {
            echo "   // composer.json\n";
            echo "   \"autoload\": {\n";
            echo "       \"psr-4\": {\n";
            echo "           \"$namespace\\\\\": \"" . dirname($relativePath) . "\"\n";
            echo "       }\n";
            echo "   }\n";
        } else {
            echo "   // composer.json\n";
            echo "   \"autoload\": {\n";
            echo "       \"files\": [\"$relativePath\"]\n";
            echo "   }\n";
        }
    
        echo "\n   Then: composer dump-autoload\n";
        echo str_repeat('-', 50) . "\n";
    }

    private function getStateFile(): string
    {
        return getcwd() . '/' . self::STATE_DIR . self::STATE_FILE;
    }
}
