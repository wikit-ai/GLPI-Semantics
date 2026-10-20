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

// AJAX endpoint for Editor AI assistant (correction, formatting, translation)

use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\RichText\RichText;
use GlpiPlugin\Wikitsemantics\Config;
use GlpiPlugin\Wikitsemantics\EditorAI;

use function Safe\json_encode;

Session::checkRight(EditorAI::$rightname, READ);

if (!isset($_POST['content']) || !isset($_POST['action'])) {
    throw new BadRequestHttpException(__('Missing content or action', 'wikitsemantics'));
}

$content = $_POST['content'];
$action = $_POST['action'];

$allowedActions = ['correction', 'formatting', 'translation', 'translation_fr', 'translation_en', 'translation_es'];
if (!in_array($action, $allowedActions, true)) {
    throw new BadRequestHttpException(__('Invalid action', 'wikitsemantics'));
}

if (empty(trim($content))) {
    throw new BadRequestHttpException(__('Editor content is empty', 'wikitsemantics'));
}

// Map translation actions to explicit language instructions
$languageMap = [
    'translation_fr' => 'Translate to French',
    'translation_en' => 'Translate to English',
    'translation_es' => 'Translate to Spanish',
];

// Build the query with action context
if (isset($languageMap[$action])) {
    $query = $languageMap[$action] . ': ' . htmlspecialchars_decode($content);
} else {
    $query = $action . ': ' . htmlspecialchars_decode($content);
}

$config = Config::getConfig();
$result = $config->getAPIAnswer(['query' => $query], 'app_id_editor');

if ($result) {
    $result = RichText::getSafeHtml(nl2br($result));
    echo json_encode([
        'success' => true,
        'content' => $result,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => __('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics'),
    ]);
}
