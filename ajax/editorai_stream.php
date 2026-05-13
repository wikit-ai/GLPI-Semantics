<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 *
 * Server-Sent Events (SSE) endpoint for streaming Editor AI responses
 */

// Disable all remaining output buffers BEFORE checking rights
while (ob_get_level()) {
    ob_end_clean();
}

// Check rights BEFORE setting SSE headers
if (!Session::haveRight("plugin_wikitsemantics_editorai", READ)) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException('Insufficient rights');
}

// Set SSE headers AFTER rights check
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

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
$config = PluginWikitsemanticsConfig::getConfig();

try {
    $config->streamAPIAnswer(['query' => $query], 'app_id_editor');
} catch (\Exception $e) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
    flush();
}

// Send new CSRF token
$newToken = Session::getNewCSRFToken();
echo "event: csrf_token\n";
echo "data: " . json_encode(['token' => $newToken]) . "\n\n";
flush();

// Close connection
echo "event: done\n";
echo "data: {}\n\n";
flush();
