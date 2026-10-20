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

// Server-Sent Events (SSE) endpoint for streaming AI responses

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Wikitsemantics\Config;
use GlpiPlugin\Wikitsemantics\GenerateAnswer;
use Symfony\Component\HttpFoundation\StreamedResponse;

use function Safe\json_encode;
use function Safe\ob_end_flush;

// Check rights BEFORE streaming (so we can throw proper HTTP exceptions)
if (!Session::haveRight(Config::$rightname, READ)) {
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

    // Get ticket content
    $generateAnswer = new GenerateAnswer();
    $ticketContent = $generateAnswer->getTicketContent($ticketId);

    if (!$ticketContent) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => 'Unable to retrieve ticket content']) . "\n\n";
        flush();
        return;
    }

    // Get configuration
    $config = Config::getConfig();

    // Call streaming API
    try {
        $config->streamAPIAnswer(['query' => $ticketContent]);
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
