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

    $generateAnswer = new PluginWikitsemanticsGenerateAnswer();
    $result = $generateAnswer->prepareToGenerateAnswer($ticketId);
    $functionclose = $_POST['close'] . "()";

    if ($result) {
        $result = Glpi\RichText\RichText::getSafeHtml(nl2br($result));
        echo "<div id='divanswer'>";
        echo $result;
        echo "</div>";
        $result = json_encode(['content' => $result]);
        $function = $_POST['answer'] . "($result)";
        echo Html::submit(
            __('Add to ticket', 'wikitsemantics'),
            [
                'id' => 'btnAddAnswer',
                'onclick' => "$function",
                'data-bs-dismiss' => 'modal',
                'class' => 'btn btn-primary',
            ]
        );
        echo Html::submit(
            __('Close', 'wikitsemantics'),
            [
                'id' => 'btnClose',
                'class' => 'btn btn-secondary',
                'onclick' => "$functionclose",
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
                'onclick' => "$functionclose",
                'data-bs-dismiss' => 'modal',
            ]
        );
    }
} else {
    exit;
}
