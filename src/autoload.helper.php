<?php

// Definir apenas cores que serão usadas
define('COLOR_RESET', "\033[0m");
define('COLOR_RED', "\033[91m");      // Erros
define('COLOR_GREEN', "\033[92m");    // Sucesso
define('COLOR_YELLOW', "\033[93m");   // Avisos/Dicas
define('COLOR_BLUE', "\033[94m");     // Informações
define('COLOR_CYAN', "\033[96m");     // Destaques
define('COLOR_WHITE', "\033[97m");    // Texto normal


// Autoload
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
    dirname(__DIR__, 3) . '/autoload.php',
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!class_exists('Eril\\TblClass\\Config')) {
    die(COLOR_RED . "✖ Autoload not found. Run 'composer install' first." . COLOR_RESET . "\n");
}
