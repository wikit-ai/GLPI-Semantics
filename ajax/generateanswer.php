<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */


Session::checkRight("plugin_wikitsemantics_configs", READ);

if (isset($_POST['ticketId'])) {
    $ticketId = filter_var($_POST['ticketId'], FILTER_VALIDATE_INT);
   if ($ticketId === false || $ticketId <= 0) {
       throw new \Glpi\Exception\Http\BadRequestHttpException(__('Invalid ticket ID', 'wikitsemantics'));
   }

    // Validate function identifiers (used only for identifying the context)
    $allowedCloseFunctions = ['closeFollowup', 'closeSolution', 'closeTask'];
    $allowedAnswerFunctions = ['addAnswerFollowup', 'addAnswerSolution', 'addAnswerTask'];

    $closeFunction = $_POST['close'] ?? '';
    $answerFunction = $_POST['answer'] ?? '';

   if (!in_array($closeFunction, $allowedCloseFunctions, true)) {
       throw new \Glpi\Exception\Http\BadRequestHttpException(__('Invalid close function', 'wikitsemantics'));
   }

   if (!in_array($answerFunction, $allowedAnswerFunctions, true)) {
       throw new \Glpi\Exception\Http\BadRequestHttpException(__('Invalid answer function', 'wikitsemantics'));
   }

    // Map function names to item types for data attributes
    $itemTypeMap = [
        'closeFollowup' => 'followup',
        'closeSolution' => 'solution',
        'closeTask' => 'task',
        'addAnswerFollowup' => 'followup',
        'addAnswerSolution' => 'solution',
        'addAnswerTask' => 'task'
    ];

    $itemType = $itemTypeMap[$answerFunction] ?? 'followup';

    // Verify user has access to this specific ticket (horizontal access control)
    $ticket = new Ticket();
    if (!$ticket->getFromDB($ticketId)) {
        throw new \Glpi\Exception\Http\NotFoundHttpException(__('Ticket not found', 'wikitsemantics'));
    }

    if (!$ticket->canViewItem()) {
        throw new \Glpi\Exception\Http\AccessDeniedHttpException(__('Access denied', 'wikitsemantics'));
    }

    $generateAnswer = new PluginWikitsemanticsGenerateAnswer();
    $result = $generateAnswer->prepareToGenerateAnswer($ticketId);

    if ($result) {
        $result = Glpi\RichText\RichText::getSafeHtml(nl2br($result));
        echo "<div id='divanswer'>";
        echo $result;
        echo "</div>";

        $resultData = json_encode(['content' => $result]);
        $encodedResult = base64_encode($resultData);

        echo Html::submit(
            __('Add to ticket', 'wikitsemantics'),
            [
                'id' => 'btnAddAnswer',
                'data-answer-content' => $encodedResult,
                'data-item-type' => $itemType,
                'data-bs-dismiss' => 'modal',
                'class' => 'btn btn-primary btn-wikitsemantics-add',
            ]
        );
        echo Html::submit(
            __('Close', 'wikitsemantics'),
            [
                'id' => 'btnClose',
                'class' => 'btn btn-secondary',
                'data-bs-dismiss' => 'modal',
            ]
        );
    } else {
        echo "<div id='divanswer'>";
        echo "<p>" . __(
            'GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.',
            'wikitsemantics'
        ) . "</p>";
        echo "</div>";
        echo Html::submit(
            __('Close', 'wikitsemantics'),
            [
                'id' => 'btnClose',
                'class' => 'btn btn-secondary',
                'data-bs-dismiss' => 'modal',
            ]
        );
    }
}
