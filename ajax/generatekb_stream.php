<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 *
 * Server-Sent Events (SSE) endpoint for streaming KB article generation
 */

// Disable all remaining output buffers BEFORE checking rights
while (ob_get_level()) {
    ob_end_clean();
}

// Check rights BEFORE setting SSE headers
if (!Session::haveRight("plugin_wikitsemantics_knowledgebase", READ)) {
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

// Get ticket ID from POST
if (!isset($_POST['ticketId'])) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Missing ticket ID']) . "\n\n";
    flush();
    return;
}

$ticketId = filter_var($_POST['ticketId'], FILTER_VALIDATE_INT);
if ($ticketId === false || $ticketId <= 0) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Invalid ticket ID']) . "\n\n";
    flush();
    return;
}

// Verify user has access to this specific ticket
$ticket = new Ticket();
if (!$ticket->getFromDB($ticketId)) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Ticket not found']) . "\n\n";
    flush();
    return;
}

if (!$ticket->canViewItem()) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Access denied']) . "\n\n";
    flush();
    return;
}

// Get full ticket content (description + followups + solutions + tasks)
$ticketContent = PluginWikitsemanticsKnowledgebase::getFullTicketContent($ticketId);

if (!$ticketContent) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Unable to retrieve ticket content']) . "\n\n";
    flush();
    return;
}

// Send suggested title
echo "event: title\n";
echo "data: " . json_encode(['title' => $ticket->fields['name']]) . "\n\n";
flush();

// Get configuration and call streaming API with KB app
$config = PluginWikitsemanticsConfig::getConfig();

try {
    $config->streamAPIAnswer(['query' => $ticketContent], 'app_id_kb');
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
