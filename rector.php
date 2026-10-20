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

use Rector\Configuration\RectorConfigBuilder;

require_once __DIR__ . '/../../src/Plugin.php';

$baseline_file = __DIR__ . '/../../PluginsRector.php';
if (!file_exists($baseline_file)) {
    throw new RuntimeException(
        sprintf(
            'Unable to find "%s". Running rector on a plugin requires a GLPI development checkout that ships PluginsRector.php.',
            $baseline_file,
        ),
    );
}

$baseline = require $baseline_file;

/** @var RectorConfigBuilder $config */
$config = $baseline([
    __DIR__ . '/src',
    __DIR__ . '/ajax',
    __DIR__ . '/front',
    __DIR__ . '/hook.php',
    __DIR__ . '/setup.php',
    __DIR__ . '/tests',
]);

return $config;
