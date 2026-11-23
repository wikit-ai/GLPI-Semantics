<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 *
 * Server-Sent Events (SSE) endpoint for streaming AI responses
 */

// Disable all remaining output buffers BEFORE checking rights
while (ob_get_level()) {
    ob_end_clean();
}

// Check rights BEFORE setting SSE headers (so we can throw proper HTTP exceptions)
if (!Session::haveRight("plugin_wikitsemantics_configs", READ)) {
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

// Verify user has access to this specific ticket (horizontal access control)
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

// Whole public conversation of the ticket, as context for the application
$generateAnswer = new PluginWikitsemanticsGenerateAnswer();
$ticketContent = $generateAnswer->getTicketConversation($ticketId);

if (!$ticketContent) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => 'Unable to retrieve ticket content']) . "\n\n";
    flush();
    return;
}

// New CSRF token for the next request of the client, created while the session can still be written
$newToken = Session::getNewCSRFToken();

// Release the session lock: the other requests of the user must not wait for the end of the stream
Session::writeClose();

// Get configuration
$config = PluginWikitsemanticsConfig::getConfig();

// Call streaming API
try {
    $config->streamAPIAnswer(['query' => $ticketContent]);
} catch (\Exception $e) {
    echo "event: error\n";
    echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
    flush();
}

// Send the new CSRF token
echo "event: csrf_token\n";
echo "data: " . json_encode(['token' => $newToken]) . "\n\n";
flush();

// Close connection
echo "event: done\n";
echo "data: {}\n\n";
flush();
