<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2025 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

include('../../../inc/includes.php');

Session::checkRight("plugin_wikitsemantics_configs", READ);

if (isset($_POST['ticketId'])) {
    $ticketId = filter_var($_POST['ticketId'], FILTER_VALIDATE_INT);
    if ($ticketId === false || $ticketId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => __('Invalid ticket ID', 'wikitsemantics')]);
        exit;
    }

    // Validate function identifiers (used only for identifying the context)
    $allowedCloseFunctions = ['closeFollowup', 'closeSolution', 'closeTask'];
    $allowedAnswerFunctions = ['addAnswerFollowup', 'addAnswerSolution', 'addAnswerTask'];

    $closeFunction = $_POST['close'] ?? '';
    $answerFunction = $_POST['answer'] ?? '';

    if (!in_array($closeFunction, $allowedCloseFunctions, true)) {
        http_response_code(400);
        echo json_encode(['error' => __('Invalid close function', 'wikitsemantics')]);
        exit;
    }

    if (!in_array($answerFunction, $allowedAnswerFunctions, true)) {
        http_response_code(400);
        echo json_encode(['error' => __('Invalid answer function', 'wikitsemantics')]);
        exit;
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
        http_response_code(404);
        echo json_encode(['error' => __('Ticket not found', 'wikitsemantics')]);
        exit;
    }

    if (!$ticket->canViewItem()) {
        http_response_code(403);
        echo json_encode(['error' => __('Access denied', 'wikitsemantics')]);
        exit;
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

    echo Html::scriptBlock("
        // Attach event listener to the Add button using delegation
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('popupAnswer');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target && e.target.classList.contains('btn-wikitsemantics-add')) {
                        const encodedContent = e.target.getAttribute('data-answer-content');
                        const itemType = e.target.getAttribute('data-item-type');

                        if (encodedContent && itemType) {
                            try {
                                // Decode and parse the content safely
                                const resultData = JSON.parse(atob(encodedContent));

                                // Call the appropriate function based on item type
                                switch(itemType) {
                                    case 'followup':
                                        if (typeof addAnswerFollowup === 'function') {
                                            addAnswerFollowup(resultData);
                                        }
                                        break;
                                    case 'solution':
                                        if (typeof addAnswerSolution === 'function') {
                                            addAnswerSolution(resultData);
                                        }
                                        break;
                                    case 'task':
                                        if (typeof addAnswerTask === 'function') {
                                            addAnswerTask(resultData);
                                        }
                                        break;
                                }
                            } catch (error) {
                                console.error('Error processing answer content:', error);
                            }
                        }
                    }
                });
            }
        });
    ");
} else {
    exit;
}
