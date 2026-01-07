<?php

namespace Eril\TblClass\Cli;

use Eril\TblClass\Generators\LegacyGenerator;

class LegacyCommand extends AbstractCommand
{
    protected function createGenerator(): LegacyGenerator
    {
        return new LegacyGenerator($this->pdo, $this->config, $this->check);
    }

    protected function help(): void
    {
        echo <<<HELP
tbl-class-legacy — Generate legacy global constants

⚠ Deprecated — for old projects only

Usage:
  tbl-class-legacy [output]

HELP;
    }
}
