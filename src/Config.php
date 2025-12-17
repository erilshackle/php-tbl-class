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
                    'host' => 'DB_HOST',
                    'port' => 'DB_PORT',
                    'name' => 'DB_NAME',
                    'user' => 'DB_USER',
                    'password' => 'DB_PASS',
                    'path' => 'database.sqlite'
                ],
                'output' => [
                    'path' => './',
                    'namespace' => ''
                ]
            ];

            // Merge mantendo valores do usuário
            $this->config = array_replace_recursive($defaults, $yamlConfig);

            $this->isNew = false;
        } catch (\Exception $e) {
            throw new Exception("Error parsing YAML config: " . $e->getMessage());
        }
    }

    private function createCleanTemplate(): void
    {
        $template = <<<YAML
# Database configuration
database:
  # Optional custom connection:
  # connection: 'App\\Database::getConnection'

  driver: mysql           # mysql or sqlite
  
  # For MySQL:
  host: DB_HOST
  port: DB_PORT
  name: DB_NAME           # required for MySQL
  user: DB_USER
  password: DB_PASS
  
  # For SQLite:
  # driver: sqlite
  # path: database.sqlite   # or DB_PATH env var

# Output configuration  
output:
  path: "./"              # Where to save Tbl.php
  namespace: ""           # PHP namespace (optional)

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
    
    // Pattern para: DB_NAME, ${DB_NAME}, env(DB_NAME)
    if (preg_match('/^\${\s*([A-Z_][A-Z0-9_]*)\s*}$/', $value, $matches)) {
        // Formato: ${DB_NAME}
        $envValue = getenv($matches[1]);
        return $envValue !== false ? $envValue : $value;
    }
    
    if (preg_match('/^env\(\s*([A-Z_][A-Z0-9_]*)\s*\)$/', $value, $matches)) {
        // Formato: env(DB_NAME)
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

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
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

    public function getDatabaseName(): string
    {
        return $this->get('database.name', '');
    }

    public function getOutputPath(): string
    {
        return rtrim($this->get('output.path', './'), '/') . '/';
    }

    public function getOutputFile(): string
    {
        return $this->getOutputPath() . 'Tbl.php';
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
        return $this->get('database.driver', 'mysql');
    }

    public function getConfigFile(): string
    {
        return $this->configFile;
    }
}
