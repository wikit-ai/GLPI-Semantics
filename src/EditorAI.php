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

use CommonGLPI;
use Session;

/**
 * Editor AI assistant: correction, formatting and translation in the rich text editors
 */
class EditorAI extends CommonGLPI
{
    public static string $rightname = 'plugin_wikitsemantics_editorai';

    /**
     * Name of the meta tag holding the client configuration in the GLPI pages
     */
    public const CLIENT_CONFIG_META = 'wikitsemantics-editor-ai';

    public static function getTypeName($nb = 0)
    {
        return __('Editor AI assistant', 'wikitsemantics');
    }

    /**
     * Configuration of the Editor AI assistant for the JavaScript of the GLPI pages.
     *
     * It needs no database query (settings kept in the GLPI cache): it is added to every page.
     *
     * Depends on the session and the configuration.
     *
     * @phpstan-impure
     * @return array<string, mixed>
     */
    public static function getClientConfig(): array
    {
        if (!Session::getLoginUserID() || !Session::haveRight(self::$rightname, READ)) {
            return ['enabled' => false];
        }

        $settings = Config::getPageSettings();
        if (!$settings['is_editor_ai_enabled']) {
            return ['enabled' => false];
        }

        $pluginWebPath = Config::getPluginWebPath();

        return [
            'enabled'            => true,
            'isStreamingEnabled' => (int) $settings['is_streaming_enabled'],
            'ajaxUrl'            => $pluginWebPath . '/ajax/editorai.php',
            'ajaxStreamUrl'      => $pluginWebPath . '/ajax/editorai_stream.php',
            'labels'             => [
                'editorAI'    => __('Editor AI assistant', 'wikitsemantics'),
                'correction'  => __('Correction', 'wikitsemantics'),
                'formatting'  => __('Formatting', 'wikitsemantics'),
                'translation' => __('Translation', 'wikitsemantics'),
                'apply'       => __('Apply', 'wikitsemantics'),
                'close'       => __('Close', 'wikitsemantics'),
                'error'       => __('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics'),
            ],
        ];
    }
}
