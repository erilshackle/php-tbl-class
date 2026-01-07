<?php

namespace Eril\TblClass\Cli;

use Eril\TblClass\Generators\Generator;
use Eril\TblClass\Generators\TableClassesGenerator;

class SchemaCommand extends AbstractCommand
{
    protected function createGenerator(): TableClassesGenerator
    {
        return new TableClassesGenerator($this->pdo, $this->config, $this->check);
    }

    protected function help(): void
    {
        echo <<<HELP
tbl-class — Generate schema-based table classes (v4)

Usage:
  tbl-class [output]

Generates:
  - Tbl class
  - Tbl<Table> classes
  - Columns, foreign keys and enums

Options:
  --check     Compare schema hash

HELP;
    }
}
