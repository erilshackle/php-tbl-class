<?php

namespace Eril\TblClass\Cli;

final class CliPrinter
{
    private static array $colors = [
        'reset'   => "\033[0m",
        'red'     => "\033[31m",
        'green'   => "\033[32m",
        'yellow'  => "\033[33m",
        'blue'    => "\033[34m",
        'magenta' => "\033[35m",
        'cyan'    => "\033[36m",
        'white'   => "\033[37m",
        'bold'    => "\033[1m",
    ];

    /* ---------- Core ---------- */

    private static function out(string $text, ?string $color = null): void
    {
        $code = self::$colors[$color] ?? '';
        echo $code . $text . self::$colors['reset'];
    }

    public static function line(string $text = '', ?string $color = null): void
    {
        self::out($text . PHP_EOL, $color);
    }

    /* ---------- Títulos / ações ---------- */

    public static function title(string $text): void
    {
        self::line("▶ {$text}", 'bold');
    }

    public static function action(string $text): void
    {
        self::out("▶ {$text} ", 'cyan');
    }

    /* ---------- Success ---------- */

    public static function success(string $text): void
    {
        self::line($text, 'green');
    }

    public static function successIcon(string $text): void
    {
        self::line("✓ {$text}", 'green');
    }

    /* ---------- Info ---------- */

    public static function info(string $text): void
    {
        self::line($text, 'cyan');
    }

    public static function infoIcon(string $text): void
    {
        self::line("• {$text}", 'cyan');
    }

    /* ---------- Warning ---------- */

    public static function warn(string $text): void
    {
        self::line($text, 'yellow');
    }

    public static function warnIcon(string $text): void
    {
        self::line("⚠ {$text}", 'yellow');
    }

    /* ---------- Error ---------- */

    public static function error(string $text): void
    {
        self::line($text, 'red');
    }

    public static function errorIcon(string $text): void
    {
        self::line("✖ {$text}", 'red');
    }
}
