<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2025 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 *
 * Server-Sent Events (SSE) endpoint for streaming AI responses
 */

// Include GLPI - exact same pattern as generateanswer.php
include('../../../inc/includes.php');

// Disable all remaining output buffers BEFORE checking rights
while (ob_get_level()) {
    ob_end_clean();
}

// Set SSE headers FIRST
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Check rights and send error if needed
if (!Session::haveRight("plugin_wikitsemantics_configs", READ)) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Insufficient rights']) . "\n\n";
    flush();
    exit;
}

// Send initial connection event
echo "event: connected\n";
echo "data: " . json_encode(['status' => 'connected']) . "\n\n";
flush();

// Get ticket ID from POST
if (!isset($_POST['ticketId'])) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Missing ticket ID']) . "\n\n";
    flush();
    exit;
}

$ticketId = filter_var($_POST['ticketId'], FILTER_VALIDATE_INT);
if ($ticketId === false || $ticketId <= 0) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Invalid ticket ID']) . "\n\n";
    flush();
    exit;
}

// Get ticket content
$generateAnswer = new PluginWikitsemanticsGenerateAnswer();
$ticketContent = $generateAnswer->getTicketContent($ticketId);

if (!$ticketContent) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Unable to retrieve ticket content']) . "\n\n";
    flush();
    exit;
}

// Get configuration
$config = PluginWikitsemanticsConfig::getConfig();

// Call streaming API
try {
    $config->streamAPIAnswer(['query' => $ticketContent]);
} catch (Exception $e) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
    flush();
}

// Send new CSRF token to client so it can be used for next request
$newToken = Session::getNewCSRFToken();
echo "event: csrf_token\n";
echo "data: " . json_encode(['token' => $newToken]) . "\n\n";
flush();

// Close connection
echo "event: done\n";
echo "data: {}\n\n";
flush();
