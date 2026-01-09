<?php

/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

/**
 * Check plugin prerequisites before installation
 * @return boolean
 */
function plugin_wikitsemantics_check_prerequisites()
{
    if (version_compare(GLPI_VERSION, PLUGIN_WIKITSEMANTICS_MIN_GLPI_VERSION, 'lt')
        || version_compare(GLPI_VERSION, PLUGIN_WIKITSEMANTICS_MAX_GLPI_VERSION, 'gt')) {
        echo "This plugin requires GLPI >= " . PLUGIN_WIKITSEMANTICS_MIN_GLPI_VERSION
            . " and < " . PLUGIN_WIKITSEMANTICS_MAX_GLPI_VERSION;
        return false;
    }

    if (version_compare(PHP_VERSION, '7.4.0', 'lt')) {
        echo "This plugin requires PHP >= 7.4.0";
        return false;
    }

    if (!extension_loaded('curl')) {
        echo "This plugin requires the curl PHP extension";
        return false;
    }

    return true;
}

/**
 * Check plugin configuration after installation
 *
 * @param boolean $verbose Whether to display messages
 * @return boolean
 */
function plugin_wikitsemantics_check_config($verbose = false)
{
    if ($verbose) {
        echo 'Installed / not configured';
    }
    return true;
}

/**
 * Plugin install process
 * @return boolean
 */
function plugin_wikitsemantics_install()
{
    global $DB;

    $migration = new Migration(PLUGIN_WIKITSEMANTICS_VERSION);

    // Create table if not exists
    if (!$DB->tableExists("glpi_plugin_wikitsemantics_configs")) {
        $DB->runFile(PLUGIN_WIKITSEMANTICS_DIR . "/install/sql/empty-1.0.0.sql");
    }

    // Remove streaming field if it exists (migration)
    if ($DB->fieldExists('glpi_plugin_wikitsemantics_configs', 'is_streaming_enabled')) {
        $migration->dropField('glpi_plugin_wikitsemantics_configs', 'is_streaming_enabled');
    }

    // Add fields if they don't exist (for upgrades)
    if (!$DB->fieldExists('glpi_plugin_wikitsemantics_configs', 'date_creation')) {
        $migration->addField(
            'glpi_plugin_wikitsemantics_configs',
            'date_creation',
            'timestamp',
            ['after' => 'organization_id']
        );
        $migration->addKey('glpi_plugin_wikitsemantics_configs', 'date_creation');
    }

    if (!$DB->fieldExists('glpi_plugin_wikitsemantics_configs', 'date_mod')) {
        $migration->addField(
            'glpi_plugin_wikitsemantics_configs',
            'date_mod',
            'timestamp',
            ['after' => 'date_creation']
        );
        $migration->addKey('glpi_plugin_wikitsemantics_configs', 'date_mod');
    }

    $migration->executeMigration();

    include_once(PLUGIN_WIKITSEMANTICS_DIR . "/inc/profile.class.php");
    PluginWikitsemanticsProfile::initProfile();

    // Only create first access if session is available (not in CLI mode)
    if (isset($_SESSION['glpiactiveprofile']['id'])) {
        PluginWikitsemanticsProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
    }

    return true;
}

/**
 * Plugin uninstall process
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

/**
 * Hook called after item form display
 *
 * @param array $params Hook parameters containing item and options
 * @return void
 */
function plugin_wikitsemantics_post_item_form($params)
{
    // Validate params
    if (!isset($params['item']) || !is_object($params['item'])) {
        return;
    }

    $item = $params['item'];

    // Check rights
    if (!Session::haveRight("plugin_wikitsemantics_configs", READ)) {
        return;
    }

    $generateAnswer = new PluginWikitsemanticsGenerateAnswer();
    $ticketId = null;

    // Determine ticket ID based on item type
    switch ($item->getType()) {
        case 'ITILFollowup':
        case 'ITILSolution':
            if (!isset($params['options']['id'])) {
                return;
            }
            $ticketId = (int)$params['options']['id'];
            break;

        case 'TicketTask':
            if (!isset($params['options']['parent'])
                || !is_object($params['options']['parent'])
                || !isset($params['options']['parent']->fields['id'])) {
                return;
            }
            $ticketId = (int)$params['options']['parent']->fields['id'];
            break;

        default:
            return;
    }

    // Call appropriate method based on item type
    switch ($item->getType()) {
        case 'ITILFollowup':
            $generateAnswer->showWikitSemanticsButtonITILFollowup($ticketId);
            break;

        case 'ITILSolution':
            $generateAnswer->showWikitSemanticsButtonITILSolution($ticketId);
            break;

        case 'TicketTask':
            $generateAnswer->showWikitSemanticsButtonTicketTask($ticketId);
            break;
    }
}
