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

// Server-Sent Events (SSE) endpoint for streaming Editor AI responses

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Wikitsemantics\Config;
use GlpiPlugin\Wikitsemantics\EditorAI;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Safe\json_encode;
use function Safe\ob_end_flush;

// Check rights BEFORE streaming (so we can throw proper HTTP exceptions)
if (!Session::haveRight(EditorAI::$rightname, READ)) {
    throw new AccessDeniedHttpException('Insufficient rights');
}

// Stream the events through a Symfony response instead of writing directly to the output,
// otherwise GLPI reports closed output buffers and unexpected output.
return new StreamedResponse(static function () {
    // Disable all remaining output buffers so that each event is sent immediately
    while (ob_get_level()) {
        ob_end_flush();
    }

    // Send initial connection event
    echo "event: connected\n";
    echo "data: " . json_encode(['status' => 'connected']) . "\n\n";
    flush();

    if (!isset($_POST['content']) || !isset($_POST['action'])) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Missing content or action']) . "\n\n";
        flush();
        return;
    }

    $content = $_POST['content'];
    $action = $_POST['action'];

    $allowedActions = ['correction', 'formatting', 'translation', 'translation_fr', 'translation_en', 'translation_es'];
    if (!in_array($action, $allowedActions, true)) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Invalid action']) . "\n\n";
        flush();
        return;
    }

    if (empty(trim($content))) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Editor content is empty']) . "\n\n";
        flush();
        return;
    }

    // Map translation actions to explicit language instructions
    $languageMap = [
        'translation_fr' => 'Translate to French',
        'translation_en' => 'Translate to English',
        'translation_es' => 'Translate to Spanish',
    ];

    // Build the query with action context
    if (isset($languageMap[$action])) {
        $query = $languageMap[$action] . ': ' . htmlspecialchars_decode($content);
    } else {
        $query = $action . ': ' . htmlspecialchars_decode($content);
    }

    // Get configuration and call streaming API with editor app
    $config = Config::getConfig();

    try {
        $config->streamAPIAnswer(['query' => $query], 'app_id_editor');
    } catch (Exception $exception) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => $exception->getMessage()]) . "\n\n";
        flush();
    }

    // Close connection
    echo "event: done\n";
    echo "data: {}\n\n";
    flush();
}, 200, [
    'Content-Type'      => 'text/event-stream',
    'Cache-Control'     => 'no-cache',
    'Connection'        => 'keep-alive',
    'X-Accel-Buffering' => 'no',
]);
