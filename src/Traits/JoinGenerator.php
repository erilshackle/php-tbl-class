<?php

namespace Eril\TblClass\Traits;

use Eril\TblClass\Resolvers\NamingResolver;

trait JoinGenerator
{
    protected NamingResolver $namingResolver;
    protected string $mode = 'class';

    /**
     * Gera métodos on_ para todas as foreign keys
     */
    protected function generateOnMethods(array $foreignKeys): string
    {
        if (empty($foreignKeys)) {
            return '';
        }

        $content = "\n    // --- Join Methods ---\n\n";

        foreach ($foreignKeys as $fk) {
            $content .= $this->generateOnMethod($fk);
        }

        return $content;
    }

    /**
     * Gera um método on_ específico
     */
    protected function generateOnMethod(array $fk): string
    {
        $methodName = 'on_' . $fk['from_table'] . '_' . $fk['to_table'];
        
        $fromColumn = $this->namingResolver->getColumnConstName(
            $fk['from_table'],
            $fk['from_column']
        );
        $toColumn = $this->namingResolver->getColumnConstName(
            $fk['to_table'],
            $fk['to_column']
        );
        
        // Obtém os aliases das tabelas
        $fromAlias = $this->namingResolver->getTableAlias($fk['from_table']);
        $toAlias = $this->namingResolver->getTableAlias($fk['to_table']);
        
        // Sugere nomes de parâmetros baseados nos aliases
        $fromParam = '$' . $fromAlias;
        $toParam = '$' . $toAlias;
        
        $comment = $this->namingResolver->getForeignKeyComment($fk);
        
        return <<<PHP
    {$comment}
    public static function {$methodName}({$fromParam} = '{$fromAlias}', {$toParam} = '{$toAlias}'): string
    {
        return TblJoin::on('{$fromColumn} = {$toColumn}', {$fromParam}, {$toParam});
    }

PHP;
    }

    /**
     * Gera a classe TblJoin
     */
    protected function generateTblJoinClass(): string
    {
        return <<<'PHP'

class TblJoin
{
    public static function on(string $rel, string $aliasA = '', string $aliasB = ''): string
    {
        [$left, $right] = explode('=', $rel);
        return trim("$aliasA." . trim($left) . " = $aliasB." . trim($right));
    }
}

PHP;
    }
}