<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 *
 * AJAX endpoint for Editor AI assistant (correction, formatting, translation)
 */

Session::checkRight("plugin_wikitsemantics_editorai", READ);

if (!isset($_POST['content']) || !isset($_POST['action'])) {
    throw new \Glpi\Exception\Http\BadRequestHttpException(__('Missing content or action', 'wikitsemantics'));
}

$content = $_POST['content'];
$action = $_POST['action'];

$allowedActions = ['correction', 'formatting', 'translation', 'translation_fr', 'translation_en', 'translation_es'];
if (!in_array($action, $allowedActions, true)) {
    throw new \Glpi\Exception\Http\BadRequestHttpException(__('Invalid action', 'wikitsemantics'));
}

if (empty(trim($content))) {
    throw new \Glpi\Exception\Http\BadRequestHttpException(__('Editor content is empty', 'wikitsemantics'));
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

$config = PluginWikitsemanticsConfig::getConfig();
$result = $config->getAPIAnswer(['query' => $query], 'app_id_editor');

if ($result) {
    $result = Glpi\RichText\RichText::getSafeHtml(nl2br($result));
    echo json_encode([
        'success' => true,
        'content' => $result,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => __('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics'),
    ]);
}
