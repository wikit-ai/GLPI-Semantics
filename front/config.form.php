<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

global $DB;

$config = new PluginWikitsemanticsConfig();
Session::checkRightsOr(PluginWikitsemanticsConfig::$rightname, [READ, UPDATE]);

// READ is the right used by the answer suggestion feature: writing the configuration requires UPDATE
if (isset($_POST["add"]) || isset($_POST["update"]) || isset($_POST['TestConnection'])) {
    Session::checkRight(PluginWikitsemanticsConfig::$rightname, UPDATE);
}

if (isset($_POST["add"])) {
    $config->add($_POST);
    Html::back();
} else if (isset($_POST["update"])) {
    $config->update($_POST);
    Html::back();
} else if (isset($_POST['TestConnection'])) {
    $config->update($_POST);
    $config->testConnection();
    Html::back();
} else {
    Html::header(__('Wikitsemantics', 'wikitsemantics'), $_SERVER['PHP_SELF'], 'config', 'wikitsemantics');

    $config->showForm(1);

    Html::footer();
}
