<?php

namespace Eril\TblClass\Traits;

use Eril\TblClass\Resolvers\NamingResolver;

trait OnJoinGenerator
{
    protected NamingResolver $namingResolver;
    protected string $mode = 'class';

    /**
     * Gera constantes on_ para todas as foreign keys
     */
    protected function generateOnConstants(array $foreignKeys): string
    {
        if (empty($foreignKeys)) {
            return '';
        }

        $content = "\n\n\n    // ==================== Join Constants ====================\n\n";

        foreach ($foreignKeys as $fk) {
            $content .= $this->generateOnConstant($fk);
        }

        return $content;
    }

    /**
     * Gera uma constante on_ específica
     */
    protected function generateOnConstant(array $fk): string
    {
        // Obtém o nome da constante: on_tbl1_tbl2
        $constName = $this->getOnConstantName($fk);
        
        // Obtém os aliases das tabelas
        $fromAlias = $this->namingResolver->getTableAlias($fk['from_table']);
        $toAlias = $this->namingResolver->getTableAlias($fk['to_table']);
        
        // Obtém nomes das colunas (sem prefixo de tabela)
        $fromColumn = $fk['from_column']; // Nome real da coluna
        $toColumn = $fk['to_column'];     // Nome real da coluna
        
        // Monta a string SQL: "alias.coluna = alias.coluna"
        $sqlValue = "{$fromAlias}.{$fromColumn} = {$toAlias}.{$toColumn}";
        
        $comment = $this->namingResolver->getForeignKeyComment($fk, false);
        
        return <<<PHP
    {$comment}
    public const {$constName} = '{$sqlValue}';\n\n
PHP;
    }

    /**
     * Obtém o nome da constante on_
     */
    protected function getOnConstantName(array $fk): string
    {
        // Usa a lógica de FK do namingResolver mas transforma em on_
        $fkName = $this->namingResolver->getForeignKeyConstName(
            $fk['from_table'],
            $fk['to_table'],
            'class',
            false
        );
        
        // Se começa com 'fk_', troca por 'on_', senão adiciona 'on_'
        if (str_starts_with($fkName, 'fk_')) {
            return 'on' . substr($fkName, 2); // Troca 'fk_' por 'on_'
        }
        
        return 'on_' . $fkName;
    }
}