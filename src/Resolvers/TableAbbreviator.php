<?php

namespace Eril\TblClass\Resolvers;

class TableAbbreviator
{
    private array $dictionary;
    private array $abbreviationCache = [];
    private array $ignore = ['de', 'da', 'do', 'para', 'the', 'and', 'of', 'for'];

    // Apenas regras básicas para inglês (mantidas para compatibilidade)
    private const EN_SIMPLE_PLURAL_RULES = [
        '/ies$/' => 'y',        // cities → city
        '/ves$/' => 'fe',       // wives → wife
        '/es$/'  => '',         // boxes → box
        '/s$/'   => '',         // users → user
    ];

    private const EN_IRREGULAR_PLURALS = [
        'people' => 'person',
        'children' => 'child',
        'men' => 'man',
        'women' => 'woman',
        'teeth' => 'tooth',
        'feet' => 'foot',
        'mice' => 'mouse',
        'geese' => 'goose',
    ];

    public function __construct(array $dictionary = [])
    {
        $this->dictionary = $dictionary;
    }

    public function abbreviate(string $tableName, int $maxLength = 25): string
    {
        $cacheKey = $tableName . '|' . $maxLength;
        if (isset($this->abbreviationCache[$cacheKey])) {
            return $this->abbreviationCache[$cacheKey];
        }

        $normalized = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $tableName));
        $words = explode('_', $normalized);
        $abbreviatedWords = [];

        foreach ($words as $word) {
            $word = trim($word);
            if (empty($word) || in_array($word, $this->ignore)) {
                continue;
            }

            $abbreviatedWords[] = $this->abbreviateWord($word);
        }

        $result = implode('', $abbreviatedWords);

        if (strlen($result) > $maxLength) {
            $result = substr($result, 0, $maxLength);
        }

        $this->abbreviationCache[$cacheKey] = $result;
        return $result;
    }

    private function abbreviateWord(string $word): string
    {
        // 1. Procura palavra exata no dicionário (funciona para qualquer língua)
        if (isset($this->dictionary[$word])) {
            return $this->dictionary[$word];
        }

        // 2. Apenas para inglês: tenta converter plural para singular
        $englishSingular = $this->tryEnglishSingular($word);
        if ($englishSingular !== $word && isset($this->dictionary[$englishSingular])) {
            return $this->dictionary[$englishSingular];
        }

        // 3. Tenta sem 's' final (fallback simples para inglês e português)
        if (str_ends_with($word, 's') && strlen($word) > 3) {
            $withoutS = substr($word, 0, -1);
            if (isset($this->dictionary[$withoutS])) {
                return $this->dictionary[$withoutS];
            }
        }

        // 4. Remove sufixos comuns em inglês (apenas para inglês)
        $base = $this->removeCommonSuffixes($word);
        if ($base !== $word && isset($this->dictionary[$base])) {
            return $this->dictionary[$base];
        }

        // 5. Se nada funcionou, retorna a palavra original (sem abreviação)
        return $word;
    }

    /**
     * Tenta converter palavra inglesa do plural para singular
     * Apenas regras simples para inglês
     */
    private function tryEnglishSingular(string $word): string
    {
        // Verifica irregulares
        if (isset(self::EN_IRREGULAR_PLURALS[$word])) {
            return self::EN_IRREGULAR_PLURALS[$word];
        }

        // Aplica regras simples
        foreach (self::EN_SIMPLE_PLURAL_RULES as $pattern => $replacement) {
            if (preg_match($pattern, $word)) {
                $singular = preg_replace($pattern, $replacement, $word);
                if (strlen($singular) > 2) {
                    return $singular;
                }
            }
        }

        return $word;
    }

    private function removeCommonSuffixes(string $word): string
    {
        if (strlen($word) <= 4) return $word;

        $suffixes = [
            'ing',
            'ed',
            'er',
            'or',
            'ion',
            'ment',
            'ity',
            'ness',
            'ance',
            'ence',
            'able',
            'ible',
            'ive',
            'ous',
            'ful',
            'less'
        ];

        foreach ($suffixes as $suffix) {
            if (str_ends_with($word, $suffix) && strlen($word) > strlen($suffix) + 2) {
                $base = substr($word, 0, -strlen($suffix));
                if (strlen($base) > 2 && !preg_match('/[aeiou]{3,}$/', $base)) {
                    return $base;
                }
            }
        }

        return $word;
    }

    /**
     * Método público para verificar se uma palavra está no dicionário
     */
    public function isInDictionary(string $word): bool
    {
        if (isset($this->dictionary[$word])) {
            return true;
        }

        // Apenas para inglês: tenta singular
        $englishSingular = $this->tryEnglishSingular($word);
        if ($englishSingular !== $word && isset($this->dictionary[$englishSingular])) {
            return true;
        }

        return false;
    }

    /**
     * Método público para debugging
     */
    public function debugWord(string $word): array
    {
        return [
            'word' => $word,
            'in_dictionary' => isset($this->dictionary[$word]),
            'english_singular' => $this->tryEnglishSingular($word),
            'without_s' => str_ends_with($word, 's') ? substr($word, 0, -1) : null,
            'final_abbreviation' => $this->abbreviateWord($word),
        ];
    }
}