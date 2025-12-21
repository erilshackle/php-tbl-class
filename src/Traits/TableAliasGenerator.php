<?php

namespace Eril\TblClass\Traits;

trait TableAliasGenerator
{
    private array $tableAliases = [];

    /**
     * Obtém um alias inteligente para a tabela
     * - 1 letra para tabelas de uma palavra (se única)
     * - 2 letras para tabelas de duas palavras
     * - Até 3 letras se necessário para evitar conflitos
     */
    public function getTableAlias(string $table): string
    {
        // Se já temos um alias para esta tabela, retorna ele
        if (isset($this->tableAliases[$table])) {
            return $this->tableAliases[$table];
        }
        
        // Gera um novo alias seguindo as regras
        $alias = $this->generateSmartAlias($table);
        
        // Armazena para reutilização
        $this->tableAliases[$table] = $alias;
        
        return $alias;
    }
    
    /**
     * Gera um alias inteligente baseado no nome da tabela
     */
    private function generateSmartAlias(string $table): string
    {
        $normalized = strtolower($table);
        $parts = explode('_', $normalized);
        $wordCount = count($parts);
        
        // Remove palavras vazias
        $parts = array_filter($parts, fn($part) => !empty($part));
        $wordCount = count($parts);
        
        // Para 1 palavra: tenta 1 letra, depois 2, depois 3
        if ($wordCount === 1) {
            return $this->getBestAliasForSingleWord($parts[0]);
        }
        
        // Para 2 palavras: começa com 2 letras
        if ($wordCount === 2) {
            return $this->getBestAliasForTwoWords($parts[0], $parts[1]);
        }
        
        // Para 3+ palavras: começa com 3 letras
        return $this->getBestAliasForMultipleWords($parts);
    }
    
    /**
     * Gera melhor alias para palavras únicas
     */
    private function getBestAliasForSingleWord(string $word): string
    {
        // Tenta 1 letra
        $alias = substr($word, 0, 1);
        if (!$this->isAliasUsed($alias)) {
            return $alias;
        }
        
        // Tenta 2 letras (primeira + segunda)
        if (strlen($word) >= 2) {
            $alias = substr($word, 0, 2);
            if (!$this->isAliasUsed($alias)) {
                return $alias;
            }
        }
        
        // Tenta combinações diferentes
        return $this->generateUniqueAlias($word, 2);
    }
    
    /**
     * Gera melhor alias para duas palavras
     */
    private function getBestAliasForTwoWords(string $word1, string $word2): string
    {
        // Primeira opção: primeira letra de cada palavra
        $alias = substr($word1, 0, 1) . substr($word2, 0, 1);
        if (!$this->isAliasUsed($alias)) {
            return $alias;
        }
        
        // Segunda opção: duas primeiras letras da primeira palavra
        if (strlen($word1) >= 2) {
            $alias = substr($word1, 0, 2);
            if (!$this->isAliasUsed($alias)) {
                return $alias;
            }
        }
        
        // Terceira opção: letras diferentes
        return $this->generateUniqueAlias($word1 . $word2, 2);
    }
    
    /**
     * Gera melhor alias para múltiplas palavras
     */
    private function getBestAliasForMultipleWords(array $parts): string
    {
        // Primeira letra de cada palavra (até 3)
        $alias = '';
        foreach ($parts as $part) {
            if (strlen($alias) < 3) {
                $alias .= substr($part, 0, 1);
            }
        }
        
        if (strlen($alias) >= 2 && !$this->isAliasUsed($alias)) {
            return $alias;
        }
        
        // Se não deu certo, tenta combinações
        $combined = implode('', $parts);
        return $this->generateUniqueAlias($combined, 3);
    }
    
    /**
     * Gera um alias único com fallbacks
     */
    private function generateUniqueAlias(string $word, int $maxLength = 3): string
    {
        $letters = preg_replace('/[^a-z]/', '', $word);
        
        if (strlen($letters) < 2) {
            return $this->generateFallbackAlias($letters);
        }
        
        // Tenta todas as combinações possíveis
        $combinations = $this->generateLetterCombinations($letters, min(3, $maxLength));
        
        foreach ($combinations as $combination) {
            if (!$this->isAliasUsed($combination)) {
                return $combination;
            }
        }
        
        // Se não achou combinação única, usa fallback
        return $this->generateFallbackAlias($letters);
    }
    
    /**
     * Gera combinações de letras
     */
    private function generateLetterCombinations(string $letters, int $length): array
    {
        $combinations = [];
        
        // Combinações de 1-3 letras
        for ($i = 1; $i <= $length; $i++) {
            if ($i === 1) {
                // Letras únicas
                for ($j = 0; $j < strlen($letters); $j++) {
                    $combinations[] = $letters[$j];
                }
            } elseif ($i === 2) {
                // Pares
                for ($j = 0; $j < strlen($letters); $j++) {
                    for ($k = 0; $k < strlen($letters); $k++) {
                        if ($j !== $k) {
                            $combinations[] = $letters[$j] . $letters[$k];
                        }
                    }
                }
            } else {
                // Trios (apenas primeiras combinações para performance)
                $max = min(3, strlen($letters));
                for ($j = 0; $j < $max; $j++) {
                    for ($k = 0; $k < $max; $k++) {
                        for ($l = 0; $l < $max; $l++) {
                            if ($j !== $k && $j !== $l && $k !== $l) {
                                $combinations[] = $letters[$j] . $letters[$k] . $letters[$l];
                            }
                        }
                    }
                }
            }
        }
        
        // Remove duplicados e retorna
        return array_unique($combinations);
    }
    
    /**
     * Fallback para quando não consegue combinação única
     */
    private function generateFallbackAlias(string $letters): string
    {
        $base = substr($letters, 0, 1);
        if (empty($base)) {
            $base = 't'; // fallback geral
        }
        
        $counter = 1;
        do {
            $alias = $base . $counter;
            $counter++;
        } while ($this->isAliasUsed($alias) && $counter < 100);
        
        return $alias;
    }
    
    /**
     * Verifica se um alias já está em uso
     */
    private function isAliasUsed(string $alias): bool
    {
        return in_array($alias, $this->tableAliases, true);
    }
    
    /**
     * Reseta todos os aliases (útil para testes)
     */
    public function resetAliases(): void
    {
        $this->tableAliases = [];
    }
    
    /**
     * Obtém todos os aliases mapeados (para debug)
     */
    public function getAliasesMap(): array
    {
        return $this->tableAliases;
    }
    
    /**
     * Obtém alias sem armazenar (apenas para consulta)
     */
    public function peekTableAlias(string $table): string
    {
        return $this->generateSmartAlias($table);
    }
}