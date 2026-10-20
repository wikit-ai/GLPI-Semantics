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

// AJAX endpoint that returns Editor AI configuration as JSON
// Called by wikitsemantics.js on page load to determine if Editor AI is enabled

use GlpiPlugin\Wikitsemantics\Config;
use GlpiPlugin\Wikitsemantics\EditorAI;

use function Safe\json_encode;

header('Content-Type: application/json');

if (!Session::getLoginUserID()) {
    echo json_encode(['enabled' => false]);
    return;
}

if (!Session::haveRight(EditorAI::$rightname, READ)) {
    echo json_encode(['enabled' => false]);
    return;
}

$config = Config::getConfig();

if (empty($config->fields['is_editor_ai_enabled']) || empty($config->fields['app_id_editor'])) {
    echo json_encode(['enabled' => false]);
    return;
}

$pluginWebPath = Config::getPluginWebPath();
$isStreamingEnabled = isset($config->fields['is_streaming_enabled']) ? (int) $config->fields['is_streaming_enabled'] : 0;

echo json_encode([
    'enabled'            => true,
    'isStreamingEnabled' => $isStreamingEnabled,
    'ajaxUrl'            => $pluginWebPath . '/ajax/editorai.php',
    'ajaxStreamUrl'      => $pluginWebPath . '/ajax/editorai_stream.php',
    'labels'             => [
        'editorAI'    => __('Editor AI assistant', 'wikitsemantics'),
        'correction'  => __('Correction', 'wikitsemantics'),
        'formatting'  => __('Formatting', 'wikitsemantics'),
        'translation' => __('Translation', 'wikitsemantics'),
        'apply'       => __('Apply', 'wikitsemantics'),
        'close'       => __('Close', 'wikitsemantics'),
        'error'       => __('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics'),
    ],
]);
