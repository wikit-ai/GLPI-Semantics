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

use GlpiPlugin\Wikitsemantics\Config;

global $DB;

$config = new Config();
Session::checkRightsOr(Config::$rightname, [READ, UPDATE]);

// READ is the right used by the answer suggestion feature: writing the configuration requires UPDATE
if (isset($_POST["add"]) || isset($_POST["update"]) || isset($_POST['TestConnection'])) {
    Session::checkRight(Config::$rightname, UPDATE);
}

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

    $config->showForm(1);

    Html::footer();
}
