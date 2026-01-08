<?php

namespace Eril\TblClass\Cli;

use Eril\TblClass\Generators\GlobalGenerator;

class GlobalCommand extends AbstractCommand
{
    protected function createGenerator(): GlobalGenerator
    {
        return new GlobalGenerator(
            $this->schema,
            $this->config,
            $this->check
        );
    }

    protected function help(): void
    {
        echo <<<HELP
tbl-class-global — Generate flat global constants

Usage:
  tbl-class-global [output]

HELP;
    }
}
