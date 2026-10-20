<?php

/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Wikit Semantics.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2026 by Wikit.
 * @license   Apache-2.0 https://www.apache.org/licenses/LICENSE-2.0
 * @link      https://github.com/wikit-ai/GLPI-Semantics
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Wikitsemantics;

use Safe\Exceptions\FilesystemException;

use function Safe\file_put_contents;
use function Safe\json_encode;

/**
 * Centralized logger for the Wikit Semantics plugin.
 * Writes to GLPI_LOG_DIR/wikitsemantics.log
 */
class Logger
{
    private static ?Logger $instance = null;

    /** @var string Path to the log file */
    private readonly string $logFile;

    private function __construct()
    {
        $this->logFile = GLPI_LOG_DIR . '/wikitsemantics.log';
    }

    public static function getInstance(): self
    {
        self::$instance ??= new self();

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

        if ($context !== []) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line .= PHP_EOL;

        try {
            file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
        } catch (FilesystemException) {
            // Logging must never break the request
        }
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
