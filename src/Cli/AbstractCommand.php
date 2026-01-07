<?php

namespace Eril\TblClass\Cli;

use PDO;
use Eril\TblClass\Config;
use Eril\TblClass\Resolvers\ConnectionResolver;
use Eril\TblClass\Generators\Generator;

abstract class AbstractCommand
{
    protected Config $config;
    protected PDO $pdo;
    protected ?string $output = null;
    protected bool $check = false;

    final public function run(array $argv): void
    {
        $this->parseArgs($argv);
        $this->bootstrap();
        $this->connect();
        $this->execute();
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
        }

        if ($this->config->isNew()) {
            echo "✓ Config created: {$this->config->getConfigFile()}\n";
            echo "Edit it and run again.\n";
            exit(0);
        }
    }

    protected function connect(): void
    {
        $this->pdo = ConnectionResolver::fromConfig($this->config);
    }

    protected function execute(): void
    {
        $generator = $this->createGenerator();
        $generator->run();
    }

    abstract protected function createGenerator(): Generator;
    abstract protected function help(): void;
}
