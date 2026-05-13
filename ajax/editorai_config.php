<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 *
 * AJAX endpoint that returns Editor AI configuration as JSON
 * Called by wikitsemantics.js on page load to determine if Editor AI is enabled
 */

header('Content-Type: application/json');

if (!Session::getLoginUserID()) {
    echo json_encode(['enabled' => false]);
    return;
}

if (!Session::haveRight("plugin_wikitsemantics_editorai", READ)) {
    echo json_encode(['enabled' => false]);
    return;
}

$config = PluginWikitsemanticsConfig::getConfig();

if (empty($config->fields['is_editor_ai_enabled']) || empty($config->fields['app_id_editor'])) {
    echo json_encode(['enabled' => false]);
    return;
}

$pluginWebPath = '../plugins/wikitsemantics';
$isStreamingEnabled = isset($config->fields['is_streaming_enabled']) ? (int)$config->fields['is_streaming_enabled'] : 0;

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
