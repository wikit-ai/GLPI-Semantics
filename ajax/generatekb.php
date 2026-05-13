<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 *
 * AJAX endpoint for Knowledge Base article generation from tickets
 */

Session::checkRight("plugin_wikitsemantics_knowledgebase", READ);

if (!isset($_POST['ticketId'])) {
    throw new \Glpi\Exception\Http\BadRequestHttpException(__('Missing ticket ID', 'wikitsemantics'));
}

$ticketId = filter_var($_POST['ticketId'], FILTER_VALIDATE_INT);
if ($ticketId === false || $ticketId <= 0) {
    throw new \Glpi\Exception\Http\BadRequestHttpException(__('Invalid ticket ID', 'wikitsemantics'));
}

// Verify user has access to this specific ticket
$ticket = new Ticket();
if (!$ticket->getFromDB($ticketId)) {
    throw new \Glpi\Exception\Http\NotFoundHttpException(__('Ticket not found', 'wikitsemantics'));
}

if (!$ticket->canViewItem()) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException(__('Access denied', 'wikitsemantics'));
}

$action = $_POST['action'] ?? 'generate';

if ($action === 'create_kb') {
    // Create Knowledge Base article from previously generated content
    Session::checkRight("plugin_wikitsemantics_knowledgebase", UPDATE);

    $name = $_POST['kb_name'] ?? '';
    $answer = $_POST['kb_content'] ?? '';

    if (empty($name) || empty($answer)) {
        throw new \Glpi\Exception\Http\BadRequestHttpException(__('Missing article title or content', 'wikitsemantics'));
    }

    $kbId = PluginWikitsemanticsKnowledgebase::createKnowbaseItem($name, $answer);

    if ($kbId === false) {
        echo json_encode(['success' => false, 'error' => __('Failed to create Knowledge Base article', 'wikitsemantics')]);
    } else {
        $redirectUrl = KnowbaseItem::getFormURLWithID($kbId);
        echo json_encode(['success' => true, 'kb_id' => $kbId, 'redirect_url' => $redirectUrl]);
    }
} else {
    // Generate KB article content from ticket
    $ticketContent = PluginWikitsemanticsKnowledgebase::getFullTicketContent($ticketId);

    if (!$ticketContent) {
        echo json_encode(['success' => false, 'error' => __('Unable to retrieve ticket content', 'wikitsemantics')]);
        return;
    }

    $config = PluginWikitsemanticsConfig::getConfig();
    $apiResult = $config->getAPIAnswer(['query' => $ticketContent], 'app_id_kb');

    if ($apiResult) {
        $answer = Glpi\RichText\RichText::getSafeHtml(nl2br($apiResult['answer']));
        echo json_encode([
            'success' => true,
            'content' => $answer,
            'suggested_title' => $ticket->fields['name'],
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => __('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics'),
        ]);
    }
}
