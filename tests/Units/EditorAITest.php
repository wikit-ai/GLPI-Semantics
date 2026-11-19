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

namespace GlpiPlugin\Wikitsemantics\Tests\Units;

use Glpi\Tests\DbTestCase;
use GlpiPlugin\Wikitsemantics\Config;
use GlpiPlugin\Wikitsemantics\EditorAI;

final class EditorAITest extends DbTestCase
{
    public function testClientConfigFollowsTheConfiguration(): void
    {
        $this->login();
        $config = Config::getConfig(true);

        $this->updateItem(Config::class, $config->getID(), ['is_editor_ai_enabled' => 0, 'app_id_editor' => 'app-editor']);
        $this->assertSame(['enabled' => false], EditorAI::getClientConfig());

        // Saving the configuration clears the settings kept in the GLPI cache
        $this->updateItem(Config::class, $config->getID(), ['is_editor_ai_enabled' => 1, 'is_streaming_enabled' => 1]);
        $clientConfig = EditorAI::getClientConfig();
        $this->assertTrue($clientConfig['enabled']);
        $this->assertSame(1, $clientConfig['isStreamingEnabled']);
        $this->assertIsString($clientConfig['ajaxStreamUrl']);
        $this->assertStringEndsWith('/plugins/wikitsemantics/ajax/editorai_stream.php', $clientConfig['ajaxStreamUrl']);
        $this->assertIsArray($clientConfig['labels']);
        $this->assertSame('Correction', $clientConfig['labels']['correction']);

        // No application: disabled
        $this->updateItem(Config::class, $config->getID(), ['app_id_editor' => '']);
        $this->assertSame(['enabled' => false], EditorAI::getClientConfig());
    }

    public function testClientConfigNeedsTheRight(): void
    {
        $this->logOut();
        $this->assertSame(['enabled' => false], EditorAI::getClientConfig());
    }
}
