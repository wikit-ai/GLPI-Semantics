<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Centralized logger for the Wikit Semantics plugin.
 * Writes to GLPI_LOG_DIR/wikitsemantics.log
 */
class PluginWikitsemanticsLogger
{
    private static ?PluginWikitsemanticsLogger $instance = null;

    /** @var string Path to the log file */
    private string $logFile;

    private function __construct()
    {
        $this->logFile = GLPI_LOG_DIR . '/wikitsemantics.log';
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Write a log entry
     *
     * @param string $level   Log level: DEBUG, INFO, WARNING, ERROR
     * @param string $message Log message
     * @param array  $context Optional context data (will be JSON-encoded)
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $date = date('Y-m-d H:i:s');
        $line = "[$date] [$level] $message";

        if (!empty($context)) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line .= PHP_EOL;

        file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->log('DEBUG', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->log('ERROR', $message, $context);
    }
}
