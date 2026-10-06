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
function plugin_wikitsemantics_check_prerequisites() {
   if (version_compare(GLPI_VERSION, PLUGIN_WIKITSEMANTICS_MIN_GLPI_VERSION, 'lt')
        || version_compare(GLPI_VERSION, PLUGIN_WIKITSEMANTICS_MAX_GLPI_VERSION, 'gt')) {
       echo "This plugin requires GLPI >= " . PLUGIN_WIKITSEMANTICS_MIN_GLPI_VERSION
           . " and < " . PLUGIN_WIKITSEMANTICS_MAX_GLPI_VERSION;
       return false;
   }

   if (version_compare(PHP_VERSION, '8.2.0', 'lt')) {
       echo "This plugin requires PHP >= 8.2.0";
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
function plugin_wikitsemantics_check_config($verbose = false) {
   if ($verbose) {
       echo 'Installed / not configured';
   }
    return true;
}

/**
 * Plugin install process
 * @return boolean
 */
function plugin_wikitsemantics_install() {
    global $DB;

    $migration = new Migration(PLUGIN_WIKITSEMANTICS_VERSION);

    // Create table if not exists
   if (!$DB->tableExists("glpi_plugin_wikitsemantics_configs")) {
       $DB->runFile(PLUGIN_WIKITSEMANTICS_DIR . "/install/sql/empty-1.0.0.sql");
   }

    // Upgrade from 2.0.0: rename app_id to app_id_answer and add new fields
   if ($DB->fieldExists('glpi_plugin_wikitsemantics_configs', 'app_id')) {
       $DB->runFile(PLUGIN_WIKITSEMANTICS_DIR . "/install/sql/update-2.1.0.sql");
   }

    // Add fields if they don't exist (for upgrades)
   if (!$DB->fieldExists('glpi_plugin_wikitsemantics_configs', 'date_creation')) {
       $migration->addField(
           'glpi_plugin_wikitsemantics_configs',
           'date_creation',
           'timestamp',
           ['after' => 'app_id_editor']
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

    // Fallback: add new fields individually if they don't exist
   if (!$DB->fieldExists('glpi_plugin_wikitsemantics_configs', 'is_kb_enabled')) {
       $migration->addField(
           'glpi_plugin_wikitsemantics_configs',
           'is_kb_enabled',
           'bool',
           ['after' => 'is_streaming_enabled', 'value' => 0]
       );
   }

   if (!$DB->fieldExists('glpi_plugin_wikitsemantics_configs', 'app_id_kb')) {
       $migration->addField(
           'glpi_plugin_wikitsemantics_configs',
           'app_id_kb',
           'string',
           ['after' => 'is_kb_enabled']
       );
   }

   if (!$DB->fieldExists('glpi_plugin_wikitsemantics_configs', 'is_editor_ai_enabled')) {
       $migration->addField(
           'glpi_plugin_wikitsemantics_configs',
           'is_editor_ai_enabled',
           'bool',
           ['after' => 'app_id_kb', 'value' => 0]
       );
   }

   if (!$DB->fieldExists('glpi_plugin_wikitsemantics_configs', 'app_id_editor')) {
       $migration->addField(
           'glpi_plugin_wikitsemantics_configs',
           'app_id_editor',
           'string',
           ['after' => 'is_editor_ai_enabled']
       );
   }

   if (!$DB->fieldExists('glpi_plugin_wikitsemantics_configs', 'is_sources_enabled_answer')) {
       $migration->addField(
           'glpi_plugin_wikitsemantics_configs',
           'is_sources_enabled_answer',
           'bool',
           ['after' => 'app_id_answer', 'value' => 0]
       );
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
function plugin_wikitsemantics_uninstall() {
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
function plugin_wikitsemantics_post_item_form($params) {
    // Validate params
   if (!isset($params['item']) || !is_object($params['item'])) {
       return;
   }

    $item = $params['item'];

    // Check rights
   if (!Session::haveRight("plugin_wikitsemantics_configs", READ)) {
       return;
   }

   if (!in_array($item->getType(), ['ITILFollowup', 'ITILSolution', 'TicketTask'], true)) {
       return;
   }

    $generateAnswer = new PluginWikitsemanticsGenerateAnswer();
    $options = $params['options'] ?? [];
    $ticketId = 0;

    // Parent ticket: GLPI >= 11.0.9 lazy-loads the answer forms (ajax/timeline.php) and only passes 'item'
   if (isset($options['item']) && $options['item'] instanceof Ticket) {
       $ticketId = (int)$options['item']->getID();
   } else if (isset($options['parent']) && $options['parent'] instanceof Ticket) {
       $ticketId = (int)$options['parent']->getID();
   } else if ($item->getType() !== 'TicketTask'
       && isset($options['id'])
       && (!isset($options['itemtype']) || $options['itemtype'] === 'Ticket')
       && in_array($item->fields['itemtype'] ?? '', ['', 'Ticket'], true)) {
       // GLPI < 11.0.9: forms rendered inline with the ticket form options
       $ticketId = (int)$options['id'];
   }

   if ($ticketId <= 0) {
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
