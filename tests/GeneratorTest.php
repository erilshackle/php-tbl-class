<?php
// tests/GeneratorTest.php - Última Tentativa Robusta

use PHPUnit\Framework\TestCase;
use Eril\TblSchemaSync\Generator;

class GeneratorTest extends TestCase
{
    private $outputDir = 'tests/output/';
    private $dbName = 'test_db';
    private $logFile;
    private $logDir; 

    protected function setUp(): void
    {
        // 1. Define o caminho absoluto para a raiz do projeto e os logs
        $projectRoot = dirname(__DIR__); 
        
        $this->logDir = $projectRoot . '/.tblschema';
        $this->logFile = $this->logDir . '/.tblsync.ini';

        // 2. Garante que os diretórios existam
        if (!is_dir($this->outputDir)) {
             @mkdir($this->outputDir, 0777, true);
        }
        if (!is_dir($this->logDir)) {
             @mkdir($this->logDir, 0777, true);
        }
        
        // 3. Limpa o arquivo de log para garantir que o teste 1 comece do zero
        if (file_exists($this->logFile)) {
             @unlink($this->logFile);
        }
        
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Limpa Tbl.php, .ini e diretórios
        $outputFile = $this->outputDir . Generator::CLASS_NAME . '.php';
        if (file_exists($outputFile)) {
            @unlink($outputFile);
        }
        if (file_exists($this->logFile)) {
             @unlink($this->logFile);
        }
        
        @rmdir($this->outputDir);
        @rmdir($this->logDir);

        parent::tearDown();
    }

    // --- Helper para o Mock do PDO permanece o mesmo ---
    private function getMockPDO(array $tables, array $columns)
    {
        $stmtMock = $this->createMock(\PDOStatement::class);
        $fetchSequence = array_merge([$tables], array_fill(0, count($tables), $columns));
        
        $stmtMock->expects($this->any())
            ->method('fetchAll')
            ->will($this->onConsecutiveCalls(...$fetchSequence));

        $pdoMock = $this->createMock(\PDO::class);
        $pdoMock->expects($this->any())
            ->method('prepare')
            ->willReturn($stmtMock);

        return $pdoMock;
    }


    public function test1GeneratorCreatesCorrectContentAndState()
    {
        $tables = ['users', 'products'];
        $columns = ['id', 'name', 'created_at'];
        $pdoMock = $this->getMockPDO($tables, $columns);

        $generator = new Generator($pdoMock, $this->dbName, $this->outputDir, false);
        $generator->run(); 

        $outputFile = $this->outputDir . Generator::CLASS_NAME . '.php';

        // 1. Assertivas de Conteúdo Gerado
        $this->assertFileExists($outputFile);

        // 2. Assertivas do Arquivo de Estado (.ini) - O problema na linha 95!
        $this->assertFileExists($this->logFile, "O arquivo de log .tblsync.ini deveria ter sido criado pelo Generator.");
        
        $logContent = parse_ini_file($this->logFile);
        $this->assertArrayHasKey('md5', $logContent);
        // ... (resto das assertivas de conteúdo) ...
    }

    public function test2CheckModeReturnsSuccessIfSchemaDidNotChange()
    {
        $tables = ['test_table'];
        $columns = ['c1', 'c2'];
        $pdoMock = $this->getMockPDO($tables, $columns);
        
        // 1. Geração inicial para salvar o estado .ini
        $generator = new Generator($pdoMock, $this->dbName, $this->outputDir, false);
        
        // HACK: Simula a execução completa (sem saída no terminal)
        // Isso é necessário porque o PHPUnit 9 não limpa a saída do terminal 
        // da execução anterior (test1) a menos que explicitamente solicitado.
        ob_start();
        $generator->run();
        ob_end_clean(); 
        // FIM HACK

        // Lê o MD5 salvo
        $logContent = parse_ini_file($this->logFile);
        $initialMd5 = $logContent['md5'];

        // 2. Executa o Check Mode e verifica a saída do terminal
        $expectedOutput = "\n🔎 Starting schema change verification for database '{$this->dbName}'...\n\n✅ Schema has NOT changed (MD5: {$initialMd5}).\n";

        $this->expectOutputString($expectedOutput);
        
        $generatorCheck = new Generator($pdoMock, $this->dbName, $this->outputDir, true);
        $generatorCheck->run(); 
    }
}