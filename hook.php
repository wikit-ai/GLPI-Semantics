<?php

/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2025 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_wikitsemantics_install()
{
    global $DB;

    if (!$DB->tableExists("glpi_plugin_wikitsemantics_configs")) {
        $DB->runFile(PLUGIN_WIKITSEMANTICS_DIR . "/install/sql/empty-1.0.0.sql");
    }

    include_once(PLUGIN_WIKITSEMANTICS_DIR . "/inc/profile.class.php");
    PluginWikitsemanticsProfile::initProfile();
    PluginWikitsemanticsProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);

    return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_wikitsemantics_uninstall()
{
    global $DB;

    $tables = ['glpi_plugin_wikitsemantics_configs'];

    foreach ($tables as $table) {
        $DB->dropTable($table);
    }

    //Delete rights associated with the plugin
    $profileRight = new ProfileRight();
    foreach (PluginWikitsemanticsProfile::getAllRights() as $right) {
        $profileRight->deleteByCriteria(['name' => $right['field']]);
    }

    PluginWikitsemanticsProfile::removeRightsFromSession();

    return true;
}

function plugin_wikitsemantics_post_item_form($params)
{
    $item = $params['item'];
    if (Session::haveRight("plugin_wikitsemantics_configs", READ)) {
        switch ($item->getType()) {
            case 'ITILFollowup':
                $generateAnswer = new PluginWikitsemanticsGenerateAnswer();
                $generateAnswer->showWikitSemanticsButtonITILFollowup($params['options']['id']);
                break;
            case 'ITILSolution':
                $generateAnswer = new PluginWikitsemanticsGenerateAnswer();
                $generateAnswer->showWikitSemanticsButtonITILSolution($params['options']['id']);
                break;
        }
    }
}
