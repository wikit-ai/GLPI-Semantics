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

use Glpi\DBAL\QueryFunction;
use GlpiPlugin\Wikitsemantics\Config;
use GlpiPlugin\Wikitsemantics\GenerateAnswer;
use GlpiPlugin\Wikitsemantics\Profile;

/**
 * Check plugin configuration after installation
 *
 * @param bool $verbose Whether to display messages
 * @return bool
 */
function plugin_wikitsemantics_check_config($verbose = false)
{
    return true;
}

/**
 * Plugin install and upgrade process
 */
function plugin_wikitsemantics_install(): bool
{
    /** @var DBmysql $DB */
    global $DB;

    $migration = new Migration(PLUGIN_WIKITSEMANTICS_VERSION);
    $table = Config::getTable();

    if (!$DB->tableExists($table)) {
        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

        $migration->displayMessage(sprintf('Creating table %s', $table));
        $DB->doQuery(
            "CREATE TABLE `$table` (
                `id` int $default_key_sign NOT NULL AUTO_INCREMENT,
                `url_api` varchar(500) NULL DEFAULT 'https://apis.wikit.ai',
                `app_id_answer` varchar(255) NULL DEFAULT NULL,
                `is_sources_enabled_answer` tinyint NOT NULL DEFAULT 0,
                `api_key` varchar(500) NULL DEFAULT NULL COMMENT 'Encrypted with GLPIKey',
                `organization_id` varchar(255) NULL DEFAULT NULL,
                `is_streaming_enabled` tinyint NOT NULL DEFAULT 0,
                `is_kb_enabled` tinyint NOT NULL DEFAULT 0,
                `app_id_kb` varchar(255) NULL DEFAULT NULL,
                `is_editor_ai_enabled` tinyint NOT NULL DEFAULT 0,
                `app_id_editor` varchar(255) NULL DEFAULT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `date_mod` (`date_mod`),
                KEY `date_creation` (`date_creation`)
            ) ENGINE=InnoDB DEFAULT CHARSET=$default_charset COLLATE=$default_collation ROW_FORMAT=DYNAMIC",
        );
        $DB->insert($table, ['id' => 1, 'date_creation' => QueryFunction::now()]);
    }

    // 2.1.0: `app_id` renamed, Knowledge Base and Editor AI settings added
    if ($DB->fieldExists($table, 'app_id')) {
        $migration->changeField($table, 'app_id', 'app_id_answer', 'string');
    }

    $fields = [
        'is_sources_enabled_answer' => ['bool', ['after' => 'app_id_answer', 'value' => 0]],
        'is_kb_enabled'             => ['bool', ['after' => 'is_streaming_enabled', 'value' => 0]],
        'app_id_kb'                 => ['string', ['after' => 'is_kb_enabled']],
        'is_editor_ai_enabled'      => ['bool', ['after' => 'app_id_kb', 'value' => 0]],
        'app_id_editor'             => ['string', ['after' => 'is_editor_ai_enabled']],
        'date_creation'             => ['timestamp', ['after' => 'app_id_editor']],
        'date_mod'                  => ['timestamp', ['after' => 'date_creation']],
    ];
    foreach ($fields as $field => [$type, $options]) {
        $migration->addField($table, $field, $type, $options);
    }

    $migration->addKey($table, 'date_creation');
    $migration->addKey($table, 'date_mod');

    // 3.0.0: classes moved from `inc/` to the `GlpiPlugin\Wikitsemantics` namespace
    foreach (['Config', 'Profile', 'Knowledgebase', 'GenerateAnswer'] as $class) {
        $migration->renameItemtype('PluginWikitsemantics' . $class, 'GlpiPlugin\\Wikitsemantics\\' . $class, false);
    }

    $migration->executeMigration();

    Profile::initProfile();

    // Only create first access if session is available (not in CLI mode)
    if (isset($_SESSION['glpiactiveprofile']['id'])) {
        Profile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
    }

    return true;
}

/**
 * Plugin uninstall process
 * @return bool
 */
function plugin_wikitsemantics_uninstall()
{
    global $DB;

    $tables = [Config::getTable()];

    foreach ($tables as $table) {
        $DB->dropTable($table);
    }

    //Delete rights associated with the plugin
    $profileRight = new ProfileRight();
    foreach (Profile::getAllRights() as $right) {
        $profileRight->deleteByCriteria(['name' => $right['field']]);
    }

    Profile::removeRightsFromSession();

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
    $item = $params['item'] ?? null;
    if (!$item instanceof CommonDBTM) {
        return;
    }

    // Check rights
    if (!Session::haveRight(Config::$rightname, READ)) {
        return;
    }

    if (!in_array($item::class, [ITILFollowup::class, ITILSolution::class, TicketTask::class], true)) {
        return;
    }

    $generateAnswer = new GenerateAnswer();
    $options = $params['options'] ?? [];
    $ticketId = 0;

    // Parent ticket: the answer forms are lazy-loaded (ajax/timeline.php) and only receive 'item' or 'parent'
    if (isset($options['item']) && $options['item'] instanceof Ticket) {
        $ticketId = $options['item']->getID();
    } elseif (isset($options['parent']) && $options['parent'] instanceof Ticket) {
        $ticketId = $options['parent']->getID();
    }

    if ($ticketId <= 0) {
        return;
    }

    // Call appropriate method based on item type
    switch ($item::class) {
        case ITILFollowup::class:
            $generateAnswer->showWikitSemanticsButtonITILFollowup($ticketId);
            break;

        case ITILSolution::class:
            $generateAnswer->showWikitSemanticsButtonITILSolution($ticketId);
            break;

        case TicketTask::class:
            $generateAnswer->showWikitSemanticsButtonTicketTask($ticketId);
            break;
    }
}
