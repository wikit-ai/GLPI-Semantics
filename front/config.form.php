<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2025 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

include("../../../inc/includes.php");

global $DB;

$config = new PluginWikitsemanticsConfig();
Session::checkRightsOr(PluginWikitsemanticsConfig::$rightname, [READ, UPDATE]);

if (isset($_POST["add"])) {
    $config->add($_POST);
    Html::back();
} elseif (isset($_POST["update"])) {
    $config->update($_POST);
    Html::back();
} elseif (isset($_POST['TestConnection'])) {
    $config->update($_POST);
    $config->testConnection();
    Html::back();
} else {
    Html::header(__('Wikitsemantics', 'wikitsemantics'), $_SERVER['PHP_SELF'], 'config', 'wikitsemantics');

    /* showForm() only displays the form */
    $config->showForm(1);

    Html::footer();
}
