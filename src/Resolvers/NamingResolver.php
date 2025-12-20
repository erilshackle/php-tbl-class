<?php
// src/NamingResolver.php

namespace Eril\TblClass\Resolvers;

class NamingResolver
{
    private array $usedNames = [];
    private array $config;
    private TableAbbreviator $abbreviator;

    private const DICTIONARY_FILES = [
        'en' => 'common_tables_en.php',
        'pt' => 'common_tables_pt.php',
    ];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'table' => 'full',
            'column' => 'abbr',
            'foreign_key' => 'smart',
            'abbreviation' => [
                'dictionary_path' => null,  // Caminho customizado do usuário
                'dictionary_lang' => 'en',   // 'en', 'pt', ou 'all'
                'max_length' => 20,
            ],
        ], $config);

        $this->abbreviator = new TableAbbreviator($this->loadDictionary());
    }

    /**
     * Carrega dicionário hierárquico:
     * 1. dictionary_path do usuário (se especificado e existir) - sobrescreve tudo
     * 2. Dicionários base conforme dictionary_lang configurado
     */
    private function loadDictionary(): array
    {
        $dictionary = [];

        // 1. Tenta dictionary_path do usuário (se especificado)
        $userPath = $this->config['abbreviation']['dictionary_path'] ?? null;
        if ($userPath) {
            $userDict = $this->loadUserDictionary($userPath);
            if (!empty($userDict)) {
                // Se o usuário especificou um dicionário, usa apenas ele
                return $userDict;
            }
        }

        // 2. Carrega dicionários base conforme language configurada
        $dictionaryLang = $this->config['abbreviation']['dictionary_lang'] ?? 'en';
        $baseDicts = $this->loadBaseDictionaries($dictionaryLang);

        // Retorna o que conseguiu carregar (pode ser array vazio)
        return $baseDicts;
    }

    /**
     * Carrega dicionários base conforme linguagem especificada
     */
    private function loadBaseDictionaries(string $lang = 'en'): array
    {
        $combinedDict = [];

        // Se for 'all', carrega todos os dicionários
        $langsToLoad = ($lang === 'all')
            ? array_keys(self::DICTIONARY_FILES)
            : [$lang];

        foreach ($langsToLoad as $language) {
            if (isset(self::DICTIONARY_FILES[$language])) {
                $dictPath = $this->findDictionaryPath(self::DICTIONARY_FILES[$language]);
                if ($dictPath) {
                    $dict = $this->safeInclude($dictPath);
                    if (!empty($dict)) {
                        $combinedDict = array_merge($combinedDict, $dict);
                    }
                }
            }
        }

        return $combinedDict;
    }

    /**
     * Carrega dicionário customizado do usuário (relativo ao projeto)
     */
    private function loadUserDictionary(string $relativePath): array
    {
        $fullPath = getcwd() . '/' . ltrim($relativePath, '/');

        if (file_exists($fullPath)) {
            return $this->safeInclude($fullPath);
        }

        // Se não encontrou, log opcional
        error_log("tbl-class: User dictionary not found: {$relativePath}");

        return [];
    }

    /**
     * Encontra o caminho completo para um arquivo de dicionário
     */
    private function findDictionaryPath(string $filename): ?string
    {
        // Tenta caminhos possíveis:
        $possiblePaths = [
            // 1. No diretório data do package (vendor)
            dirname(__DIR__, 2) . '/data/' . $filename,
            // 2. No diretório atual do projeto (para desenvolvimento)
            getcwd() . '/data/' . $filename,
            // 3. No diretório do composer
            dirname(__DIR__, 5) . '/data/' . $filename,
            // 4. No mesmo diretório do NamingResolver (fallback extremo)
            dirname(__DIR__) . '/data/' . $filename,
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function safeInclude(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $dictionary = include $path;
        return is_array($dictionary) ? $dictionary : [];
    }

    // ====================== MÉTODOS PÚBLICOS ======================

    public function getTableConstName(string $table, string $mode = 'class', $strategy = null): string
    {
        $strategy = $strategy ?? $this->config['table'];
        $name = $this->getTableName($table, $strategy);

        return ($mode === 'global') ? 'tbl_' . $name : $name;
    }

    public function getColumnConstName(string $table, string $column, string $mode = 'class'): string
    {
        $strategy = $this->config['column'];
        $name = $this->getColumnName($table, $column, $strategy);
        return ($mode === 'global') ? 'tbl_' . $name : $name;
    }

    public function getForeignKeyConstName(string $fromTable, string $toTable, string $mode = 'class'): string
    {
        $strategy = $this->config['foreign_key'];
        $prefix = ($mode === 'global') ? 'tfk_' : 'fk_';
        $baseName = $this->getForeignKeyName($fromTable, $toTable, $strategy);

        return $this->getUniqueName($prefix . $baseName);
    }

    // ====================== ESTRATÉGIAS SIMPLIFICADAS ======================

    private function getTableName(string $table, string $strategy): string
    {
        $normalized = $this->normalizeName($table);

        return match ($strategy) {
            'abbr' => $this->abbreviateWithFallback($table),
            'smart' => (strlen($normalized) < 10) ? $normalized : $this->abbreviateWithFallback($table),
            default => $normalized, // 'full'
        };
    }

    private function getColumnName(string $table, string $column, string $strategy): string
    {
        $normalizedTable = $this->normalizeName($table);
        $normalizedColumn = $this->normalizeName($column);


        return match ($strategy) {
            'abbr' => $this->abbreviateWithFallback($table) . '_' . $normalizedColumn,
            'smart' => (strlen($normalizedTable) < 8)
                ? $normalizedTable . '_' . $normalizedColumn
                : $this->abbreviateWithFallback($table) . '_' . $normalizedColumn,
            default => $normalizedTable . '_' . $normalizedColumn, // 'full'
        };
    }

    private function getForeignKeyName(string $fromTable, string $toTable, string $strategy): string
    {
        $from = $this->normalizeName($fromTable);
        $to = $this->normalizeName($toTable);

        return match ($strategy) {
            'abbr' => $this->abbreviateWithFallback($fromTable) . '_' . $this->abbreviateWithFallback($toTable),
            'smart' => (strlen($from) < 10 && strlen($to) < 10)
                ? $from . '_' . $to
                : $this->abbreviateWithFallback($fromTable) . '_' . $this->abbreviateWithFallback($toTable),
            default => $from . '_' . $to, // 'full'
        };
    }

    // ====================== MÉTODOS AUXILIARES ======================

    private function abbreviateWithFallback(string $name): string
    {
        $maxLength = $this->config['abbreviation']['max_length'] ?? 20;
        $abbr = $this->abbreviator->abbreviate($name, $maxLength);

        // Se não abreviou, usa fallback simples
        $normalized = $this->normalizeName($name);
        if ($abbr === $normalized && strlen($abbr) > 10) {
            $abbr = $this->simpleFallback($name, $maxLength);
        }

        return $abbr;
    }

    private function simpleFallback(string $name, int $maxLength): string
    {
        $normalized = $this->normalizeName($name);

        // Se tem underscore, pega primeiras letras
        if (str_contains($normalized, '_')) {
            $parts = explode('_', $normalized);
            $result = '';
            foreach ($parts as $part) {
                if (!empty($part)) {
                    $result .= substr($part, 0, min(3, strlen($part)));
                }
            }
            return substr($result, 0, $maxLength);
        }

        // Palavra única muito longa: limita
        return substr($normalized, 0, $maxLength);
    }

    private function normalizeName(string $name): string
    {
        return strtolower($name);
    }

    private function getUniqueName(string $baseName): string
    {
        $counter = 1;
        $finalName = $baseName;

        while (isset($this->usedNames[$finalName])) {
            $finalName = $baseName . '_' . $counter;
            $counter++;
        }

        $this->usedNames[$finalName] = true;
        return $finalName;
    }

    public function getForeignKeyComment(array $fk): string
    {
        return "/** {$fk['from_table']}.{$fk['from_column']} → {$fk['to_table']}.{$fk['to_column']} */";
    }

    public function reset(): void
    {
        $this->usedNames = [];
    }
}
