<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 *
 * AJAX endpoint for fetching quoted sources from Wikit Semantics API
 */

Session::checkRight("plugin_wikitsemantics_configs", READ);

if (!isset($_POST['queryId']) || empty($_POST['queryId'])) {
    throw new \Glpi\Exception\Http\BadRequestHttpException('Missing queryId');
}

$queryId = $_POST['queryId'];

// Validate queryId format (alphanumeric, hyphens, underscores)
if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $queryId)) {
    throw new \Glpi\Exception\Http\BadRequestHttpException('Invalid queryId format');
}

// Validate appIdField against whitelist
$allowedFields = ['app_id_answer'];
$appIdField = $_POST['appIdField'] ?? 'app_id_answer';

if (!in_array($appIdField, $allowedFields, true)) {
    throw new \Glpi\Exception\Http\BadRequestHttpException('Invalid appIdField');
}

$config = PluginWikitsemanticsConfig::getConfig();

$sources = $config->getQuotedSources($queryId, $appIdField);

if ($sources !== false) {
    echo json_encode(['success' => true, 'sources' => $sources]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch sources']);
}
