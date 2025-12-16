<?php
// src/Generator.php

namespace Eril\TblSchemaSync;

use PDO;
use Exception;

class Generator
{
    // Class name generated for the user's project
    public const CLASS_NAME = 'Tbl'; 
    // Output filename (Tbl.php)
    private const DEFAULT_FILENAME = self::CLASS_NAME . '.php'; 
    
    // Log directory hidden in the user's project root (e.g., ./ .tblschema /)
    private const LOG_DIR = __DIR__ . '/../../../.tblschema/';
    // Log file name using INI format (Only stores MD5 now)
    private const CHECK_LOG_FILENAME = '.tblsync.ini'; 
    // Full path for the log file
    private const CHECK_LOG_FILE = self::LOG_DIR . self::CHECK_LOG_FILENAME; 

    private PDO $pdo;
    private string $dbName;
    private string $outputDir;
    private bool $checkMode;
    private string $outputFile;

    public function __construct(PDO $pdo, string $dbName, string $outputDir, bool $checkMode)
    {
        $this->pdo = $pdo;
        $this->dbName = $dbName;
        // Ensure outputDir ends with a slash
        $this->outputDir = rtrim($outputDir, '/') . '/';
        $this->checkMode = $checkMode;

        // The final output file path for Tbl.php
        $this->outputFile = $this->outputDir . self::DEFAULT_FILENAME;
    }
    
    /**
     * Main entry point to execute the operation.
     */
    public function run(): void
    {
        try {
            $tableList = $this->fetchTableList();
            
            if (empty($tableList)) {
                echo "🚫 Error: No BASE tables found in database '{$this->dbName}'.\n";
                exit(1);
            }

            // Generate the content of the Tbl class in memory
            $constantsContent = $this->generateConstantsContent($tableList);
            $currentMd5 = md5($constantsContent);
            
            if ($this->checkMode) {
                $this->handleCheckMode($currentMd5);
            } else {
                $this->handleGenerationMode($constantsContent, $currentMd5, count($tableList));
            }

        } catch (Exception $e) {
            die("❌ Fatal Execution Error: " . $e->getMessage() . "\n");
        }
    }

    /**
     * Fetches the list of tables from the database.
     */
    private function fetchTableList(): array
    {
        $stmt = $this->pdo->prepare("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = :db_name
            AND TABLE_TYPE = 'BASE TABLE'
        ");
        $stmt->bindParam(':db_name', $this->dbName);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Generates the PHP content for the Tbl class (without namespace, to allow global access).
     */
    private function generateConstantsContent(array $tableList): string
    {
        // Generates the class in the global namespace (Tbl::users)
        $constantsContent = "class " . self::CLASS_NAME . "\n{\n"; 

        foreach ($tableList as $tableName) {
            $columns = $this->getTableColumns($tableName);

            if (empty($columns)) {
                echo "⚠️ Warning: Table '$tableName' ignored (no columns).\n";
                continue;
            }

            $tablePrefix = strtolower($tableName);
            $constDeclaration = 'public const';
            $indent = '    ';
            
            $tableConstName = $tablePrefix;
            $columnConstPrefix = $tablePrefix . '_';

            $constantsContent .= "\n" . $indent . "// --- Table: " . $tableName . " ---\n";

            // Table Constant
            $constantsContent .= $indent . "$constDeclaration $tableConstName = '$tableName';\n";

            // Column Constants (table_column)
            foreach ($columns as $column) {
                $constNameColumn = $columnConstPrefix . strtolower($column);
                $constantsContent .= $indent . "$constDeclaration $constNameColumn = '$column';\n";
            }
        }
        $constantsContent .= "}\n";
        
        return $constantsContent;
    }

    /**
     * Fetches columns for a specific table.
     */
    private function getTableColumns(string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :db_name
            AND TABLE_NAME = :tableName
            ORDER BY ORDINAL_POSITION
        ");
        $stmt->bindParam(':db_name', $this->dbName);
        $stmt->bindParam(':tableName', $tableName);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    /**
     * Handles the check mode (--check).
     */
    private function handleCheckMode(string $currentMd5): void
    {
        echo "\n🔎 Starting schema change verification for database '{$this->dbName}'...\n";

        // Read the saved INI file (if it exists)
        $savedConfig = file_exists(self::CHECK_LOG_FILE) ? parse_ini_file(self::CHECK_LOG_FILE) : [];

        $savedMd5 = $savedConfig['md5'] ?? '';

        if ($savedMd5 === $currentMd5) {
            echo "\n✅ Schema has NOT changed (MD5: $currentMd5).\n";
            exit(0);
        } 
        
        // --- If MD5 is different or missing: Alert and Update INI File ---
        
        // 1. Ensure the log directory exists
        if (!is_dir(self::LOG_DIR)) {
            if (!mkdir(self::LOG_DIR, 0777, true)) {
                die("❌ Error: Could not create log directory: " . self::LOG_DIR . "\n");
            }
        }
        
        // 2. Prepare new INI content (Only MD5 is saved)
        $newConfigContent = "; Generated on: " . date('Y-m-d H:i:s') . "\n";
        $newConfigContent .= "md5=\"" . $currentMd5 . "\"\n";
        
        // 3. Write new log content
        if (file_put_contents(self::CHECK_LOG_FILE, $newConfigContent) !== false) {
            if (empty($savedMd5)) {
                echo "\n⚠️ Schema verified for the first time. MD5 saved to " . self::CHECK_LOG_FILENAME . ".\n";
                echo "   Current MD5: $currentMd5\n";
            } else {
                echo "\n❌ ALERT: Database Schema HAS CHANGED!\n";
                echo "   Old MD5: " . ($savedMd5 ?: 'N/A') . "\n";
                echo "   New MD5:   $currentMd5\n";
                echo "   The state file " . self::CHECK_LOG_FILENAME . " has been updated. Constants regeneration is required.\n";
            }
        } else {
             echo "\n❌ Error! Could not write to state file: " . self::CHECK_LOG_FILE . "\n";
        }
        exit(1); // Return error code 1 on change/failure
    }
    
    /**
     * Handles the generation mode (default).
     */
    private function handleGenerationMode(string $constantsContent, string $currentMd5, int $tableCount): void
    {
        // 1. Create Output Directory (if it doesn't exist)
        if (!empty($this->outputDir) && !is_dir($this->outputDir)) {
            if (!mkdir($this->outputDir, 0755, true)) {
                throw new Exception("Could not create output directory: '{$this->outputDir}'");
            }
            echo "📁 Output directory '{$this->outputDir}' created successfully.\n";
        }

        // 2. Assemble and Write Final File (Tbl.php)
        $finalContent = $this->generateFileHeader() . $constantsContent;

        if (file_put_contents($this->outputFile, $finalContent) !== false) {
            echo "\n✅ Success! The constants file was generated:\n";
            echo "   Path: **{$this->outputFile}**\n";
            echo "   Mode: Class " . self::CLASS_NAME . "\n";
            echo "   Tables Processed: $tableCount\n";
            
            // 3. Save MD5 after successful generation
            
            // Ensure the log directory exists
            if (!is_dir(self::LOG_DIR)) {
                if (!mkdir(self::LOG_DIR, 0777, true)) {
                    throw new Exception("Could not create log directory: " . self::LOG_DIR);
                }
            }

            // Save state in INI format (Only MD5 is saved)
            $newConfigContent = "; Generated on: " . date('Y-m-d H:i:s') . "\n";
            $newConfigContent .= "md5=\"" . $currentMd5 . "\"\n";
            
            file_put_contents(self::CHECK_LOG_FILE, $newConfigContent);
            
            // 4. Display Initializer Instructions
            $this->displayInitializerInstructions();
            
        } else {
            throw new Exception("Could not write to file: **{$this->outputFile}**");
        }
    }
    
    /**
     * Generates the dynamic file header.
     */
    private function generateFileHeader(): string {
        $content = "<?php\n\n";
        $content .= "/**\n";
        $content .= " * Constants automatically generated from database '{$this->dbName}'.\n";
        $content .= " * Mode: Class " . self::CLASS_NAME . " (Global Namespace)\n";
        $content .= " * Generated on: " . date('Y-m-d H:i:s') . "\n";
        $content .= " */\n";
        return $content;
    }
    
    /**
     * Exibe instruções claras sobre como o desenvolvedor deve configurar o autoload via TblInitializer.
     */
    private function displayInitializerInstructions(): void 
    {
        // Determina o caminho relativo a ser exibido para a instrução use()
        $cwd = rtrim(getcwd(), '/') . '/';
        // Remove o Tbl.php do caminho e remove o diretório base
        $outputDirName = rtrim(str_replace($cwd, '', $this->outputDir), '/'); 
        
        echo "\n" . str_repeat('-', 30) . " CONFIGURATION REQUIRED " . str_repeat('-', 30) . "\n";
        echo "The " . self::CLASS_NAME . " class has been successfully generated.\n";
        echo "To enable 'use Tbl;' access in your application, add the following lines to your bootstrap file:\n\n";
        
        echo "   use Eril\\TblSchemaSync\\TblInitializer;\n";
        echo "   TblInitializer::use('{$outputDirName}');\n"; // <-- Passa o diretório de saída
        
        echo "\nNOTE: This step replaces manual Composer autoload configuration.\n";
        echo str_repeat('-', 80) . "\n";
    }
}