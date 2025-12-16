<?php
// src/TblInitializer.php

namespace Eril\TblSchemaSync;

use Exception;

/**
 * Classe responsável por incluir a classe Tbl gerada no escopo global
 * durante o processo de bootstrap da aplicação.
 */
class TblInitializer
{
    private const FILENAME = 'Tbl.php';

    /**
     * Carrega a classe Tbl gerada para o escopo global.
     * * @param string $outputDir O caminho do diretório onde o Tbl.php foi gerado (ex: 'app/Constants').
     * @param bool $throwOnError Se deve lançar exceção em caso de erro.
     * @return bool Retorna true se a classe Tbl foi carregada.
     */
    public static function use(string $outputDir, bool $throwOnError = true): bool
    {
        // 1. Constrói o caminho absoluto do arquivo Tbl.php
        
        // O caminho é relativo à raiz do projeto (onde o vendor/ está).
        // Usamos getcwd() para a raiz, e voltamos dois níveis para sair de vendor/eril/tbl-schema-sync/
        $projectRoot = __DIR__ . '/../../../'; 
        
        // Remove barras extras e constrói o caminho completo
        $normalizedDir = rtrim($outputDir, '/');
        $tblFilePath = $projectRoot . $normalizedDir . '/' . self::FILENAME;

        // 2. Valida e Inclui o arquivo
        
        if (file_exists($tblFilePath)) {
            // Este include carrega a classe Tbl para o escopo global.
            include_once $tblFilePath;
            return true;
        }
        
        // 3. Lançamento de Erro (se o arquivo não for encontrado)
        
        $relativeTblPath = $normalizedDir . '/' . self::FILENAME;
        $message = "Tbl Initialization Error: The generated constant file was not found at expected path: '{$relativeTblPath}'.";
        $message .= "\nAction required: Run 'vendor/bin/tbl-class-generate {$outputDir} -db ...' to create the file.";

        if ($throwOnError) {
            throw new Exception($message);
        }
        
        error_log($message);
        return false;
    }
}