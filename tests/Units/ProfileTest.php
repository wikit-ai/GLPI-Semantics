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
use GlpiPlugin\Wikitsemantics\Knowledgebase;
use GlpiPlugin\Wikitsemantics\Profile;
use PHPUnit\Framework\Attributes\DataProvider;
use Profile as GlpiProfile;
use ProfileRight;

final class ProfileTest extends DbTestCase
{
    /**
     * @return array<string, int>
     */
    private function getPluginRights(int $profiles_id): array
    {
        $rights = [];
        $names = array_column(Profile::getAllRights(), 'field');
        foreach ((new ProfileRight())->find(['profiles_id' => $profiles_id, 'name' => $names]) as $row) {
            $this->assertIsArray($row);
            $this->assertIsString($row['name']);
            $this->assertIsInt($row['rights']);
            $rights[$row['name']] = $row['rights'];
        }

        ksort($rights);

        return $rights;
    }

    public function testGetAllRights(): void
    {
        $this->assertSame(
            [Config::$rightname, Knowledgebase::$rightname, EditorAI::$rightname],
            array_column(Profile::getAllRights(), 'field'),
        );
    }

    public function testCreateFirstAccess(): void
    {
        $profile = $this->createItem(GlpiProfile::class, ['name' => 'WS profile']);

        Profile::createFirstAccess($profile->getID());

        $this->assertSame([
            Config::$rightname        => READ + UPDATE,
            EditorAI::$rightname      => READ,
            Knowledgebase::$rightname => READ + UPDATE,
        ], $this->getPluginRights($profile->getID()));
    }

    public function testAddDefaultProfileInfosKeepsExistingRights(): void
    {
        $profile = $this->createItem(GlpiProfile::class, ['name' => 'WS profile']);
        Profile::createFirstAccess($profile->getID());

        Profile::addDefaultProfileInfos($profile->getID(), [Config::$rightname => 0]);
        $this->assertSame(READ + UPDATE, $this->getPluginRights($profile->getID())[Config::$rightname]);

        Profile::addDefaultProfileInfos($profile->getID(), [Config::$rightname => READ], true);
        $this->assertSame(READ, $this->getPluginRights($profile->getID())[Config::$rightname]);
    }

    /**
     * @return iterable<array{string|int, int}>
     */
    public static function rightsProvider(): iterable
    {
        yield ['', 0];
        yield ['r', READ];
        yield ['w', ALLSTANDARDRIGHT + READNOTE + UPDATENOTE];
        yield ['0', 0];
        yield ['1', 1];
        yield ['x', 0];
    }

    #[DataProvider('rightsProvider')]
    public function testTranslateARight(string|int $old_right, int $expected): void
    {
        $this->assertSame($expected, Profile::translateARight($old_right));
    }
}
