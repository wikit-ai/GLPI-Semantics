<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2025 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

use Glpi\Plugin\Hooks;

define('PLUGIN_WIKITSEMANTICS_VERSION', '1.1.0');
// Minimal GLPI version, inclusive
define("PLUGIN_WIKITSEMANTICS_MIN_GLPI_VERSION", "10.0.0");
// Maximum GLPI version, exclusive
define("PLUGIN_WIKITSEMANTICS_MAX_GLPI_VERSION", "11.9.99");

define("PLUGIN_WIKITSEMANTICS_DIR", Plugin::getPhpDir("wikitsemantics"));

define("PLUGIN_WIKITSEMANTICS_WEBDIR", Plugin::getWebDir("wikitsemantics"));

/**
 * Init hooks of the plugin.
 * REQUIRED
 * @return void
 */
function plugin_init_wikitsemantics()
{
    global $PLUGIN_HOOKS;
    $PLUGIN_HOOKS['csrf_compliant']['wikitsemantics'] = true;

    if (Plugin::isPluginActive('wikitsemantics')) {
        if (Session::getLoginUserID()) {
            Plugin::registerClass('PluginWikitsemanticsConfig');
            Plugin::registerClass(
                'PluginWikitsemanticsProfile',
                ['addtabon' => 'Profile']
            );

            if (Session::haveRight("config", UPDATE)) {
                $PLUGIN_HOOKS['config_page']['wikitsemantics'] = 'front/config.form.php';
            }
        }
        $PLUGIN_HOOKS['post_item_form']['wikitsemantics'] = 'plugin_wikitsemantics_post_item_form';
    }
}


/**
 * Get the name and the version of the plugin
 * REQUIRED
 * @return array
 */
function plugin_version_wikitsemantics()
{
    return [
        'name' => 'Wikit Semantics',
        'version' => PLUGIN_WIKITSEMANTICS_VERSION,
        'author' => 'Wikit',
        'license' => 'Apache2',
        'homepage' => 'https://github.com/wikit-ai/GLPI-Semantics',
        'logo' => 'logo-wikit-blue.png',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_WIKITSEMANTICS_MIN_GLPI_VERSION,
                'max' => PLUGIN_WIKITSEMANTICS_MAX_GLPI_VERSION,
            ]
        ]
    ];
}
