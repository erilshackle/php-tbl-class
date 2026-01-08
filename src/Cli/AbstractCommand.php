<?php

namespace Eril\TblClass\Cli;

use Eril\TblClass\Schema\PgSqlSchemaReader;
use Eril\TblClass\Schema\SqliteSchemaReader;
use Eril\TblClass\Resolvers\ConnectionResolver;
use Eril\TblClass\Schema\MySqlSchemaReader;
use Eril\TblClass\Schema\SchemaReaderInterface;
use Eril\TblClass\Config;
use Exception;
use PDO;

abstract class AbstractCommand
{
    protected Config $config;
    protected PDO $pdo;
    protected ?SchemaReaderInterface $schema = null;
    protected ?string $output = null;
    protected bool $check = false;

    final public function run(array $argv): void
    {
        try {
            $this->parseArgs($argv);
            $this->bootstrap();
            $this->connect();
            $this->execute();
        } catch (Exception $e) {
            $error = $e->getMessage();

            CliPrinter::error("Error: $error");

            if (str_contains($error, 'DB_NAME') || str_contains($error, 'database name')) {
                CliPrinter::line("💡 Tip: Set 'database.name' in " . $this->config->getConfigFile(), 'warn');
                CliPrinter::line("   Try use environment variable: export DB_NAME=your_database");
            } elseif (str_contains($error, 'connection failed')) {
                CliPrinter::line("💡 Tip: Check your database credentials in " . $this->config->getConfigFile(), "warn");
                CliPrinter::line("   Make sure your database server is running.\n");
            }

            exit(1);
        }
    }


    protected function parseArgs(array $argv): void
    {
        foreach ($argv as $i => $arg) {
            if ($i === 0) continue;

            if ($arg === '--check') {
                $this->check = true;
            } elseif ($arg === '--help' || $arg === '-h') {
                $this->help();
                exit(0);
            } elseif ($arg[0] !== '-') {
                $this->output = $arg;
            }
        }
    }

    protected function bootstrap(): void
    {
        $this->config = new Config();

        if ($this->output) {
            $this->config->set('output.path', $this->output);
            CliPrinter::info("Output path set to {$this->output}");
        }

        if ($this->config->isNew()) {
            CliPrinter::success("Config created: {$this->config->getConfigFile()}");
            CliPrinter::warn("Edit it and run again.");
            exit(0);
        }

        CliPrinter::info('↻ Reading configuration');

    }


    protected function connect(): void
    {
        $this->pdo = ConnectionResolver::fromConfig($this->config);

        $this->schema = match ($this->config->getDriver()) {
            'mysql'  => new MySqlSchemaReader($this->pdo, $this->config->getDatabaseName()),
            'pgsql'  => new PgSqlSchemaReader($this->pdo, $this->config->getDatabaseName()),
            'sqlite' => new SqliteSchemaReader($this->pdo, $this->config->getDatabaseName()),
            default  => throw new Exception('Unsupported database driver'),
        };

        CliPrinter::success("Database connected ({$this->config->getDriver()})");
    }


    protected function execute(): void
    {
        $generator = $this->createGenerator();
        $generator->run();
    }

    abstract protected function createGenerator();
    abstract protected function help(): void;
}
