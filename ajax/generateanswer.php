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

use Glpi\Application\View\TemplateRenderer;
use Glpi\Exception\Http\AccessDeniedHttpException;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Exception\Http\NotFoundHttpException;
use Glpi\RichText\RichText;
use GlpiPlugin\Wikitsemantics\Config;
use GlpiPlugin\Wikitsemantics\GenerateAnswer;

use function Safe\json_encode;

Session::checkRight(Config::$rightname, READ);

if (isset($_POST['ticketId'])) {
    $ticketId = filter_var($_POST['ticketId'], FILTER_VALIDATE_INT);
    if ($ticketId === false || $ticketId <= 0) {
        throw new BadRequestHttpException(__('Invalid ticket ID', 'wikitsemantics'));
    }

    // Validate function identifiers (used only for identifying the context)
    $allowedCloseFunctions = ['closeFollowup', 'closeSolution', 'closeTask'];
    $allowedAnswerFunctions = ['addAnswerFollowup', 'addAnswerSolution', 'addAnswerTask'];

    $closeFunction = $_POST['close'] ?? '';
    $answerFunction = $_POST['answer'] ?? '';

    if (!in_array($closeFunction, $allowedCloseFunctions, true)) {
        throw new BadRequestHttpException(__('Invalid close function', 'wikitsemantics'));
    }

    if (!in_array($answerFunction, $allowedAnswerFunctions, true)) {
        throw new BadRequestHttpException(__('Invalid answer function', 'wikitsemantics'));
    }

    // Map function names to item types for data attributes
    $itemTypeMap = [
        'closeFollowup' => 'followup',
        'closeSolution' => 'solution',
        'closeTask' => 'task',
        'addAnswerFollowup' => 'followup',
        'addAnswerSolution' => 'solution',
        'addAnswerTask' => 'task',
    ];

    $itemType = $itemTypeMap[$answerFunction];

    // Verify user has access to this specific ticket (horizontal access control)
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        throw new NotFoundHttpException(__('Ticket not found', 'wikitsemantics'));
    }

    if (!$ticket->canViewItem()) {
        throw new AccessDeniedHttpException(__('Access denied', 'wikitsemantics'));
    }

    $generateAnswer = new GenerateAnswer();
    $ticketContent = $generateAnswer->getTicketContent($ticketId);
    $apiResult = $ticketContent ? Config::getConfig()->getAPIAnswer(['query' => $ticketContent]) : false;

    $answer = null;
    $encodedResult = '';
    if ($apiResult) {
        $answer = RichText::getSafeHtml(nl2br($apiResult['answer']));
        $encodedResult = base64_encode(json_encode(['content' => $answer]));
    }

    TemplateRenderer::getInstance()->display('@wikitsemantics/answer_result.html.twig', [
        'answer'         => $answer,
        'query_id'       => $apiResult['queryId'] ?? '',
        'encoded_result' => $encodedResult,
        'item_type'      => $itemType,
    ]);
}
