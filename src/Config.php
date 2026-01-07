<?php
// src/Config.php

namespace Eril\TblClass;

use Symfony\Component\Yaml\Yaml;
use Exception;

class Config
{
    private array $config = [];
    private string $configFile;
    private bool $isNew = false;
    private ?string $customOutputFile = null;

    public function __construct(?string $configFile = null)
    {
        $this->configFile = $configFile ?: getcwd() . '/tblclass.yaml';
        $this->load();
    }

    private function load(): void
    {
        // Se arquivo não existe, cria com template limpo
        if (!file_exists($this->configFile)) {
            $this->isNew = true;
            $this->createCleanTemplate();
            return;
        }

        // Carrega do YAML existente
        try {
            $yamlConfig = Yaml::parseFile($this->configFile);

            // Configuração mínima para funcionar
            $defaults = [
                'database' => [
                    'driver' => 'mysql',
                    'connection' => null,
                    'host' => 'localhost',        // DEFAULT literal
                    'port' => 3306,               // DEFAULT literal  
                    'name' => '',                  // VAZIO - requerido
                    'user' => 'root',              // DEFAULT literal
                    'password' => '',              // DEFAULT vazio
                    'path' => 'database.sqlite'   // DEFAULT literal
                ],
                'output' => [
                    'path' => './',
                    'namespace' => ''
                ]
            ];

            // Merge mantendo valores do usuário
            $this->config = array_replace_recursive($defaults, $yamlConfig);

            $this->isNew = false;
        } catch (Exception $e) {
            throw new Exception("Error parsing YAML config: " . $e->getMessage());
        }
        $this->runAutoloaders();
    }

    private function createCleanTemplate(): void
    {
        $template = <<<YAML
# Autoload a file
include: ""

# Database configuration
database:
  ## Optional custom connection:
  # connection: 'App\\Database::getConnection'

  driver: mysql           # mysql or sqlite
  
  # For MySQL (use environment variables):
  host: env(DB_HOST)      # or 'localhost'
  port: env(DB_PORT)      # or 3306
  name: env(DB_NAME)      # required for MySQL
  user: env(DB_USER)      # or 'root'
  password: env(DB_PASS)  # or ''
  
  ## For SQLite (driver: sqlite)
  # path: env(DB_PATH)    # or 'database.sqlite'

# Output configuration
output:
mode: "schema"          # global | schema | legacy
  path: "./"              # Where to save Tbl.php
  namespace: ""           # PHP namespace (optional for legacy mode)
  
  # Naming strategies for constants (fullname or abbreviated)
  naming:
    strategy: "full"      # full, short
    table: "full"         # full, abbr 
    column: "full"        # full, abbr
    foreign_key: "abbr"   # full, abbr 
    
    # Abbreviation settings
    abbreviation:
      dictionary_lang: "en"    # 'en', 'pt', ou 'all'
      dictionary_path: null    # custom dictionary path (relative to project)
      max_length: 15           # max abbreviation length

YAML;

        // Garante que o diretório existe
        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($this->configFile, $template) === false) {
            throw new Exception("Cannot create config file: " . $this->configFile);
        }

        // Carrega o template para a memória
        $this->config = Yaml::parse($template);
        $this->isNew = true;
    }

    public function isNew(): bool
    {
        return $this->isNew;
    }

    public function save(): void
    {
        // Se for novo template, não sobrescreve comentários
        if ($this->isNew) {
            return;
        }

        $yaml = Yaml::dump($this->config, 4, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($this->configFile, $yaml) === false) {
            throw new Exception("Cannot write config file: " . $this->configFile);
        }
    }

    public function exposeYaml()
    {
        $yaml = Yaml::dump($this->config, 4, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        return $yaml;
    }

    /**
     * Resolve environment variable placeholders
     * Supports: DB_NAME, ${DB_NAME}, env(DB_NAME)
     */
    public function resolveEnvVars($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        // Pattern para: env(DB_NAME)
        if (preg_match('/^env\(\s*([A-Z_][A-Z0-9_]*)\s*\)$/', $value, $matches)) {
            $envValue = getenv($matches[1]);
            return $envValue !== false ? $envValue : $value;
        }

        // Pattern para: ${DB_NAME}
        if (preg_match('/^\${\s*([A-Z_][A-Z0-9_]*)\s*}$/', $value, $matches)) {
            $envValue = getenv($matches[1]);
            return $envValue !== false ? $envValue : $value;
        }

        if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $value)) {
            // Formato: DB_NAME (apenas letras maiúsculas e underscore)
            $envValue = getenv($value);
            return $envValue !== false ? $envValue : $value;
        }

        return $value;
    }

    public function get(string $key, $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        // Resolve environment variables before returning
        return $this->resolveEnvVars($value);
    }

    public function set(string $key, $value): self
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
        return $this;
    }

    public function runAutoloaders(){
        $file =  $this->get('include');

        if(empty($file)){
            if(is_file($file)){
                @include_once($file);
            }
        }
    }

    public function getNamingConfig(): array
    {
        return [
            'table' => $this->get('output.naming.table', 'full'),
            'column' => $this->get('output.naming.column', 'abbr'),
            'foreign_key' => $this->get('output.naming.foreign_key', 'smart'),
            'abbreviation' => [
                'dictionary_path' => $this->get('output.naming.abbreviation.dictionary_path'),
                'dictionary_lang' => $this->get('output.naming.abbreviation.dictionary_lang', 'en'),
                'max_length' => $this->get('output.naming.abbreviation.max_length', 20),
            ],
        ];
    }

    public function getDatabaseName(): string
    {
        return $this->get('database.name', '');
    }

    public function getOutputPath(): string
    {
        return rtrim($this->get('output.path', './'), '/') . '/';
    }

    public function getOutputFile(string $mode = 'classes'): string
    {
        // Se foi definido um arquivo personalizado, usa ele
        if ($this->customOutputFile !== null) {
            return $this->getOutputPath() . $this->customOutputFile;
        }

        switch ($mode) {
            case 'global':
                return $this->getOutputPath() . 'tbl_constants.php';
            case 'legacy':
                return $this->getOutputPath() . 'Tbl.php';
                default:
                // classes mode
                return $this->getOutputPath() . 'TblClasses.php';
        }
    }

    /**
     * Define um nome de arquivo personalizado para a saída
     * Útil para GlobalGenerator que precisa de arquivo diferente
     */
    public function setOutputFile(string $filename): self
    {
        $this->customOutputFile = $filename;
        return $this;
    }

    /**
     * Reseta o nome do arquivo de saída para o padrão
     */
    public function resetOutputFile(): self
    {
        $this->customOutputFile = null;
        return $this;
    }

    public function hasConnectionCallback(): bool
    {
        return !empty($this->get('database.connection'));
    }

    public function getConnectionCallback(): ?callable
    {
        $callback = $this->get('database.connection');
        if (!$callback) return null;

        if (is_string($callback)) {
            if (str_contains($callback, '::')) {
                list($class, $method) = explode('::', $callback, 2);
                return function () use ($class, $method) {
                    if(!method_exists($class, $method)){
                        throw new Exception("Undefined class or method for connection callback: " . $class.'::'.$method . '()');
                    }
                    return $class::$method();
                };
            } elseif (function_exists($callback)) {
                return $callback;
            }
        }

        throw new Exception("Invalid connection callback");
    }

    public function getDriver(): string
    {
        return (string) $this->get('database.driver', 'mysql');
    }

    public function getConfigFile(): string
    {
        return $this->configFile;
    }

    public function getConfigFileName(): string
    {
        return $this->configFile ? basename($this->configFile) : '';
    }
}
