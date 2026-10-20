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

namespace GlpiPlugin\Wikitsemantics;

use CommonDBTM;
use CommonGLPI;
use DbUtils;
use Html;
use Profile as GlpiProfile;
use ProfileRight;
use Session;

/**
 * Class Profile
 */
class Profile extends CommonDBTM
{
    public static string $rightname = 'profile';

    /**
     * Get the tab name for an item
     *
     * @param CommonGLPI $item         Item instance
     * @param int        $withtemplate Template flag
     * @return string Tab name or empty string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof GlpiProfile) {
            return __('Wikit Semantics', 'wikitsemantics');
        }

        return '';
    }

    /**
     * Display the tab content for an item
     *
     * @param CommonGLPI $item         Item instance
     * @param int        $tabnum       Tab number
     * @param int        $withtemplate Template flag
     * @return bool True if content was displayed
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof GlpiProfile) {
            $ID = $item->getID();
            $prof = new self();

            self::addDefaultProfileInfos($ID, [
                Config::$rightname => 0,
                Knowledgebase::$rightname => 0,
                EditorAI::$rightname => 0,
            ]);
            $prof->showForm($ID);
        }

        return true;
    }

    /**
     * Create first access rights for a profile
     *
     * @param int $ID Profile ID
     * @return void
     */
    public static function createFirstAccess($ID)
    {
        self::addDefaultProfileInfos(
            $ID,
            [
                Config::$rightname => READ + UPDATE,
                Knowledgebase::$rightname => READ + UPDATE,
                EditorAI::$rightname => READ,
            ],
            true,
        );
    }

    /**
     * Add default profile rights
     *
     * @param int   $profiles_id   Profile ID
     * @param array $rights        Rights to add (name => value)
     * @param bool  $drop_existing Whether to drop existing rights before adding
     * @return void
     */
    public static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false)
    {
        $dbu = new DbUtils();
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if ($dbu->countElementsInTable(
                'glpi_profilerights',
                ["profiles_id" => $profiles_id, "name" => $right],
            ) && $drop_existing) {
                $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
            }

            if (!$dbu->countElementsInTable(
                'glpi_profilerights',
                ["profiles_id" => $profiles_id, "name" => $right],
            )) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name'] = $right;
                $myright['rights'] = $value;
                $profileRight->add($myright);

                //Add right to the current session
                if (isset($_SESSION['glpiactiveprofile'])) {
                    $_SESSION['glpiactiveprofile'][$right] = $value;
                }
            }
        }
    }

    /**
     * Show profile form
     *
     * @param int                  $ID      Profile ID
     * @param array<string, mixed> $options `openform` and `closeform` (both true by default)
     * @return bool
     */
    public function showForm($ID, array $options = [])
    {
        $profiles_id = (int) $ID;
        $openform = $options['openform'] ?? true;
        $closeform = $options['closeform'] ?? true;

        echo "<div class='firstbloc'>";
        if (($canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]))
             && $openform) {
            $profile = new GlpiProfile();
            echo "<form method='post' action='" . htmlescape($profile->getFormURL()) . "'>";
        }

        $profile = new GlpiProfile();
        $profile->getFromDB($profiles_id);

        $rights = static::getAllRights();
        $profile->displayRightsChoiceMatrix($rights, [
            'canedit' => $canedit,
            'default_class' => 'tab_bg_2',
            'title' => __('General'),
        ]);

        if ($canedit
             && $closeform) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>\n";
            Html::closeForm();
        }

        echo "</div>";

        return true;
    }

    /**
     * Get all plugin rights
     *
     * @param bool $all Get all rights or only visible ones
     * @return array Array of rights definitions
     */
    public static function getAllRights($all = false)
    {
        return [
            [
                'rights' => [READ => __('Read'), UPDATE => __('Update')],
                'label' => __('Wikit Semantics - Configuration', 'wikitsemantics'),
                'field' => Config::$rightname,
            ],
            [
                'rights' => [READ => __('Read'), UPDATE => __('Update')],
                'label' => __('Wikit Semantics - Knowledge Base', 'wikitsemantics'),
                'field' => Knowledgebase::$rightname,
            ],
            [
                'rights' => [READ => __('Read')],
                'label' => __('Wikit Semantics - Editor AI', 'wikitsemantics'),
                'field' => EditorAI::$rightname,
            ],
        ];
    }

    /**
     * Translate an old right format to new format
     *
     * @param string|int $old_right Old right value
     * @return int Translated right value
     */
    public static function translateARight($old_right)
    {
        return match ($old_right) {
            '' => 0,
            'r' => READ,
            'w' => ALLSTANDARDRIGHT + READNOTE + UPDATENOTE,
            '0', '1' => (int) $old_right,
            default => 0,
        };
    }

    /**
     * Initialize profiles and migrate if necessary
     * Adds plugin rights to profiles and updates current session
     *
     * @return void
     */
    public static function initProfile()
    {
        global $DB;
        $profile = new self();
        $dbu = new DbUtils();

        //Add new rights in glpi_profilerights table
        foreach ($profile->getAllRights(true) as $data) {
            if ($dbu->countElementsInTable(
                "glpi_profilerights",
                ["name" => $data['field']],
            ) === 0) {
                ProfileRight::addProfileRights([$data['field']]);
            }
        }

        // Only update session if it exists and has active profile
        if (!isset($_SESSION['glpiactiveprofile']['id'])) {
            return;
        }

        $profileId = (int) $_SESSION['glpiactiveprofile']['id'];

        $it = $DB->request([
            'FROM' => 'glpi_profilerights',
            'WHERE' => [
                'profiles_id' => $profileId,
                'name' => ['LIKE', $DB->escape('%plugin_wikitsemantics%')],
            ],
        ]);
        foreach ($it as $prof) {
            $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
        }
    }

    /**
     * Remove plugin rights from current session
     *
     * @return void
     */
    public static function removeRightsFromSession()
    {
        foreach (self::getAllRights(true) as $right) {
            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }
    }
}
