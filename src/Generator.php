<?php
// src/Generator.php - Final Tooling Version

namespace Eril\TblSchemaSync;

use PDO;
use Exception;

class Generator
{
    public const CLASS_NAME = 'Tbl'; 
    private const DEFAULT_FILENAME = self::CLASS_NAME . '.php'; 
    
    // Nomes baseados no projeto do usuário, não em caminhos relativos internos
    private const LOG_DIR_NAME = '.tblschema/';
    private const CHECK_LOG_FILENAME = '.tblsync.ini'; 

    private PDO $pdo;
    private string $dbName;
    private string $outputDir;
    private bool $checkMode;
    private string $outputFile;
    
    // Variáveis de instância para armazenar os caminhos absolutos (necessário para getcwd())
    private string $checkLogFile; 
    private string $logDirPath; 

    public function __construct(PDO $pdo, string $dbName, string $outputDir, bool $checkMode)
    {
        $this->pdo = $pdo;
        $this->dbName = $dbName;
        $this->checkMode = $checkMode;

        // --- Calcula o Diretório de Saída (Output Directory) ---
        // Se outputDir for omitido, usa a raiz do projeto (CWD).
        if (empty($outputDir)) {
             $outputDir = getcwd();
        }
        
        $this->outputDir = rtrim($outputDir, '/') . '/';
        $this->outputFile = $this->outputDir . self::DEFAULT_FILENAME;
        
        // --- Cálculo Absoluto do Caminho de Log (Robusto via getcwd()) ---
        $projectRoot = getcwd();
        $this->logDirPath = $projectRoot . '/' . self::LOG_DIR_NAME;
        $this->checkLogFile = $this->logDirPath . self::CHECK_LOG_FILENAME;
    }
    
    public function run(): void
    {
        try {
            $tableList = $this->fetchTableList();
            
            if (empty($tableList)) {
                echo "🚫 Error: No BASE tables found in database '{$this->dbName}'.\n";
                exit(1);
            }

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

    private function generateConstantsContent(array $tableList): string
    {
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
            
            // Usando $tablePrefix como constante de nome de tabela
            $constantsContent .= "\n" . $indent . "// --- Table: " . $tableName . " ---\n";
            $constantsContent .= $indent . "$constDeclaration $tablePrefix = '$tableName';\n";

            // Column Constants (table_column)
            foreach ($columns as $column) {
                $constNameColumn = $tablePrefix . '_' . strtolower($column);
                $constantsContent .= $indent . "$constDeclaration $constNameColumn = '$column';\n";
            }
        }
        $constantsContent .= "}\n";
        
        return $constantsContent;
    }

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
    
    private function handleCheckMode(string $currentMd5): void
    {
        echo "\n🔎 Starting schema change verification for database '{$this->dbName}'...\n";

        // Read the saved INI file (agora usa $this->checkLogFile)
        $savedConfig = file_exists($this->checkLogFile) ? parse_ini_file($this->checkLogFile) : [];
        $savedMd5 = $savedConfig['md5'] ?? '';

        if ($savedMd5 === $currentMd5) {
            echo "\n✅ Schema has NOT changed (MD5: $currentMd5).\n";
            exit(0);
        } 
        
        // --- If MD5 is different or missing: Alert and Update INI File ---
        
        // 1. Ensure the log directory exists (agora usa $this->logDirPath)
        if (!is_dir($this->logDirPath)) {
            if (!mkdir($this->logDirPath, 0777, true)) {
                die("❌ Error: Could not create log directory: " . $this->logDirPath . "\n");
            }
        }
        
        // 2. Prepare new INI content
        $newConfigContent = "; Generated on: " . date('Y-m-d H:i:s') . "\n";
        $newConfigContent .= "md5=\"" . $currentMd5 . "\"\n";
        
        // 3. Write new log content (agora usa $this->checkLogFile)
        if (file_put_contents($this->checkLogFile, $newConfigContent) !== false) {
            if (empty($savedMd5)) {
                echo "\n⚠️ Schema verified for the first time. MD5 saved to " . self::CHECK_LOG_FILENAME . ".\n";
                echo "   Current MD5: $currentMd5\n";
            } else {
                echo "\n❌ ALERT: Database Schema HAS CHANGED!\n";
                echo "   Old MD5: " . ($savedMd5 ?: 'N/A') . "\n";
                echo "   New MD5:   $currentMd5\n";
                echo "   The state file " . self::CHECK_LOG_FILENAME . " has been updated. Regeneration required.\n";
            }
        } else {
            echo "\n❌ Error! Could not write to state file: " . $this->checkLogFile . "\n";
        }
        exit(1); 
    }
    
    private function handleGenerationMode(string $constantsContent, string $currentMd5, int $tableCount): void
    {
        // 1. Create Output Directory
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
            if (!is_dir($this->logDirPath)) {
                if (!mkdir($this->logDirPath, 0777, true)) {
                    throw new Exception("Could not create log directory: " . $this->logDirPath);
                }
            }

            $newConfigContent = "; Generated on: " . date('Y-m-d H:i:s') . "\n";
            $newConfigContent .= "md5=\"" . $currentMd5 . "\"\n";
            
            file_put_contents($this->checkLogFile, $newConfigContent);
            
            // 4. Display instructions for manual autoload (sem TblInitializer)
            $this->displayAutoloadInstructions();
            
        } else {
            throw new Exception("Could not write to file: **{$this->outputFile}**");
        }
    }
    
    /**
     * Generates the dynamic file header, including the suggested PSR-4 namespace (commented out).
     */
    private function generateFileHeader(): string {
        $content = "<?php\n\n";
        
        $projectRoot = getcwd();
        $outputDirAbs = rtrim($this->outputDir, DIRECTORY_SEPARATOR); 

        // Calculate the relative path from the project root
        $relativeDir = trim(str_replace($projectRoot, '', $outputDirAbs), DIRECTORY_SEPARATOR); 
        $suggestedNamespace = '';

        if (!empty($relativeDir)) {
             $segments = explode(DIRECTORY_SEPARATOR, $relativeDir);
             
             // Capitalize the first segment for PSR-4 convention
             $segments = array_map(function($segment) use ($segments) {
                 if ($segment === $segments[0]) {
                     return ucfirst($segment);
                 }
                 return $segment;
             }, $segments);
             
             $suggestedNamespace = implode('\\', $segments);
        }

        // --- Include Commented Namespace ---
        if (!empty($suggestedNamespace)) {
             $content .= "// namespace " . $suggestedNamespace . ";\n"; // Namespace sugerido
        } else {
             $content .= "// File is in the project root (global scope).\n";
        }
        
        $content .= "\n/**\n";
        $content .= " * Constants automatically generated from database '{$this->dbName}'.\n";
        $content .= " * Mode: Class " . self::CLASS_NAME . " (Global/Suggested Namespace)\n";
        $content .= " * Generated on: " . date('Y-m-d H:i:s') . "\n";
        $content .= " */\n";
        return $content;
    }
    
    /**
     * Displays instructions on how the developer must configure the autoload via composer.json.
     */
    private function displayAutoloadInstructions(): void 
    {
        $cwd = rtrim(getcwd(), '/') . '/';
        $relativeFilePath = str_replace($cwd, '', $this->outputFile);
        
        echo "\n" . str_repeat('-', 30) . " MANUAL AUTOLOAD REQUIRED " . str_repeat('-', 26) . "\n";
        echo "The " . self::CLASS_NAME . ".php file has been successfully generated.\n";
        echo "To use 'Tbl::...' (in global scope) you must manually configure Composer's 'autoload.files' section:\n\n";
        
        echo "   // composer.json\n";
        echo "   \"autoload\": {\n";
        echo "       \"files\": [\n";
        echo "           \"{$relativeFilePath}\"\n"; 
        echo "       ]\n";
        echo "   }\n";
        
        echo "\nNOTE: After editing composer.json, run 'composer dump-autoload'.\n";
        echo str_repeat('-', 80) . "\n";
    }
}