<?php
// src/Logger.php

namespace Eril\TblClass;

class Logger
{
    private const LOG_DIR = '.tblclass/';
    private const LOG_FILE = 'tbl-class.log';
    
    private string $logPath;
    
    public function __construct()
    {
        $this->logPath = getcwd() . '/' . self::LOG_DIR . self::LOG_FILE;
        $this->ensureLogDir();
    }
    
    private function ensureLogDir(): void
    {
        $dir = dirname($this->logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    public function log(string $action, string $hash, string $result): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $line = "[$timestamp] $action $hash $result\n";
        
        file_put_contents($this->logPath, $line, FILE_APPEND);
    }
    
    public function getLogPath(): string
    {
        return $this->logPath;
    }
}