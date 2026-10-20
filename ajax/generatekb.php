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

// AJAX endpoint for Knowledge Base article generation from tickets

use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use Glpi\RichText\RichText;
use GlpiPlugin\Wikitsemantics\Config;
use GlpiPlugin\Wikitsemantics\Knowledgebase;

use function Safe\json_encode;

Session::checkRight(Knowledgebase::$rightname, READ);

if (!isset($_POST['ticketId'])) {
    throw new BadRequestHttpException(__('Missing ticket ID', 'wikitsemantics'));
}

$ticketId = filter_var($_POST['ticketId'], FILTER_VALIDATE_INT);
if ($ticketId === false || $ticketId <= 0) {
    throw new BadRequestHttpException(__('Invalid ticket ID', 'wikitsemantics'));
}

// Verify user has access to this specific ticket
$ticket = new Ticket();
if (!$ticket->getFromDB($ticketId)) {
    throw new NotFoundHttpException(__('Ticket not found', 'wikitsemantics'));
}

if (!$ticket->canViewItem()) {
    throw new AccessDeniedHttpException(__('Access denied', 'wikitsemantics'));
}

$action = $_POST['action'] ?? 'generate';

if ($action === 'create_kb') {
    // Create Knowledge Base article from previously generated content
    Session::checkRight(Knowledgebase::$rightname, UPDATE);

    $name = $_POST['kb_name'] ?? '';
    $answer = $_POST['kb_content'] ?? '';

    if (empty($name) || empty($answer)) {
        throw new BadRequestHttpException(__('Missing article title or content', 'wikitsemantics'));
    }

    $parentId = (int) ($_POST['kb_parent_id'] ?? 0);

    $kbId = Knowledgebase::createKnowbaseItem($name, $answer, $parentId, $ticketId);

    if ($kbId === false) {
        echo json_encode(['success' => false, 'error' => __('Failed to create Knowledge Base article', 'wikitsemantics')]);
    } else {
        $redirectUrl = KnowbaseItem::getFormURLWithID($kbId);
        echo json_encode(['success' => true, 'kb_id' => $kbId, 'redirect_url' => $redirectUrl]);
    }
} elseif ($action === 'parent_visibility') {
    // Describe who will be able to read the article under the selected parent
    Session::checkRight(Knowledgebase::$rightname, UPDATE);

    $parentId = KnowbaseItem::getReadablePrefilledParentId((int) ($_POST['kb_parent_id'] ?? 0));
    if ($parentId === null) {
        throw new BadRequestHttpException(__('Invalid parent article', 'wikitsemantics'));
    }

    echo json_encode([
        'success' => true,
        'html'    => Knowledgebase::renderParentVisibility($parentId),
    ]);
} else {
    // Generate KB article content from ticket
    $ticketContent = Knowledgebase::getFullTicketContent($ticketId);

    if (!$ticketContent) {
        echo json_encode(['success' => false, 'error' => __('Unable to retrieve ticket content', 'wikitsemantics')]);
        return;
    }

    $config = Config::getConfig();
    $apiResult = $config->getAPIAnswer(['query' => $ticketContent], 'app_id_kb');

    if ($apiResult) {
        $answer = RichText::getSafeHtml(nl2br($apiResult['answer']));
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
