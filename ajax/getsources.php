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

// AJAX endpoint for fetching quoted sources from Wikit Semantics API

use Glpi\Exception\Http\BadRequestHttpException;
use GlpiPlugin\Wikitsemantics\Config;

use function Safe\json_encode;
use function Safe\preg_match;

Session::checkRight(Config::$rightname, READ);

if (!isset($_POST['queryId']) || empty($_POST['queryId'])) {
    throw new BadRequestHttpException('Missing queryId');
}

$queryId = $_POST['queryId'];

// Validate queryId format (alphanumeric, hyphens, underscores)
if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $queryId)) {
    throw new BadRequestHttpException('Invalid queryId format');
}

// Validate appIdField against whitelist
$allowedFields = ['app_id_answer'];
$appIdField = $_POST['appIdField'] ?? 'app_id_answer';

if (!in_array($appIdField, $allowedFields, true)) {
    throw new BadRequestHttpException('Invalid appIdField');
}

$config = Config::getConfig();

$sources = $config->getQuotedSources($queryId, $appIdField);

if ($sources !== false) {
    echo json_encode(['success' => true, 'sources' => $sources]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to fetch sources']);
}
