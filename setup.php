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

use Glpi\Config\ProxyExclusion;
use Glpi\Config\ProxyExclusions;
use Glpi\Plugin\Hooks;
use GlpiPlugin\Wikitsemantics\Config;
use GlpiPlugin\Wikitsemantics\EditorAI;
use GlpiPlugin\Wikitsemantics\Knowledgebase;
use GlpiPlugin\Wikitsemantics\Profile;

/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define('PLUGIN_WIKITSEMANTICS_VERSION', '3.0.0');
// Minimal GLPI version, inclusive
/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define("PLUGIN_WIKITSEMANTICS_MIN_GLPI_VERSION", "12.0.0");
// Maximum GLPI version, exclusive
/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define("PLUGIN_WIKITSEMANTICS_MAX_GLPI_VERSION", "12.0.99");

/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define("PLUGIN_WIKITSEMANTICS_DIR", Plugin::getPhpDir("wikitsemantics"));

/**
 * Init hooks of the plugin.
 * REQUIRED
 * @return void
 */
function plugin_init_wikitsemantics()
{
    global $PLUGIN_HOOKS, $CFG_GLPI;

    if (Plugin::isPluginActive('wikitsemantics')) {

        // Let administrators exclude the calls to Wikit Semantics from the GLPI proxy (Setup > General > System)
        /** @var ProxyExclusions $proxy_exclusions */
        $proxy_exclusions = $CFG_GLPI['possible_proxy_exclusions'];
        $proxy_exclusions->addExclusion(
            new ProxyExclusion(
                Config::class,
                'Wikit Semantics',
                __('Calls to the Wikit Semantics API', 'wikitsemantics'),
            ),
        );

        if (Session::getLoginUserID()) {
            $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT]['wikitsemantics'] = ['js/wikitsemantics.js'];

            Plugin::registerClass(Config::class);
            Plugin::registerClass(
                Profile::class,
                ['addtabon' => 'Profile'],
            );
            Plugin::registerClass(
                Knowledgebase::class,
                ['addtabon' => ['Ticket']],
            );

            if (isset($_SESSION['glpiactiveprofile']['id'])
                && (!isset($_SESSION['glpiactiveprofile'][Config::$rightname])
                    || !isset($_SESSION['glpiactiveprofile'][Knowledgebase::$rightname])
                    || !isset($_SESSION['glpiactiveprofile'][EditorAI::$rightname]))
            ) {
                Profile::initProfile();
            }

            if (Session::haveRight(\Config::$rightname, UPDATE)) {
                $PLUGIN_HOOKS['config_page']['wikitsemantics'] = 'front/config.form.php';
            }

            $PLUGIN_HOOKS[Hooks::POST_ITEM_FORM]['wikitsemantics'] = 'plugin_wikitsemantics_post_item_form';
        }
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
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_WIKITSEMANTICS_MIN_GLPI_VERSION,
                'max' => PLUGIN_WIKITSEMANTICS_MAX_GLPI_VERSION,
            ],
            'php' => [
                'min' => '8.3',
            ],
        ],
    ];
}
