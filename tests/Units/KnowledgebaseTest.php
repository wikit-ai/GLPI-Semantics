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

use Entity_KnowbaseItem;
use Glpi\Tests\DbTestCase;
use GlpiPlugin\Wikitsemantics\Knowledgebase;
use ITILCategory;
use ITILFollowup;
use ITILSolution;
use KnowbaseItem;
use KnowbaseItem_Item;
use KnowbaseItem_Profile;
use Profile;
use Ticket;

final class KnowledgebaseTest extends DbTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->login();
    }

    /**
     * @param array<int> $parents
     */
    private function createArticle(string $name, array $parents = []): KnowbaseItem
    {
        $input = [
            'name'         => $name,
            'answer'       => $name,
            'entities_id'  => $this->getTestRootEntity(true),
            'is_recursive' => 1,
        ];
        if ($parents !== []) {
            $input['_parents'] = $parents;
        }

        $article = $this->createItem(KnowbaseItem::class, $input, ['_parents']);
        $this->assertInstanceOf(KnowbaseItem::class, $article);

        return $article;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createTicket(array $input = []): Ticket
    {
        $ticket = $this->createItem(Ticket::class, $input + [
            'name'        => 'Printer is down',
            'content'     => 'The printer of the second floor does not print anymore',
            'entities_id' => $this->getTestRootEntity(true),
        ]);
        $this->assertInstanceOf(Ticket::class, $ticket);

        return $ticket;
    }

    public function testGetParentCandidatesShowsTheTreePaths(): void
    {
        $parent = $this->createArticle('WS parent');
        $child = $this->createArticle('WS child', [$parent->getID()]);

        $candidates = Knowledgebase::getParentCandidates();

        $root = new KnowbaseItem();
        $this->assertTrue($root->getFromDB(KnowbaseItem::getRootId()));
        $root_name = $root->fields['name'];
        $this->assertIsString($root_name);
        $this->assertSame($root_name, $candidates[KnowbaseItem::getRootId()]);
        $this->assertSame($root_name . ' > WS parent', $candidates[$parent->getID()]);
        $this->assertSame($root_name . ' > WS parent > WS child', $candidates[$child->getID()]);
    }

    public function testGetSuggestedParentWithoutCandidates(): void
    {
        $this->assertNull(Knowledgebase::getSuggestedParent($this->createTicket(), []));
    }

    public function testGetSuggestedParentUsesTheCategoryArticle(): void
    {
        $article = $this->createArticle('WS printers');
        $category = $this->createItem(ITILCategory::class, ['name' => 'WS printing', 'knowbaseitems_id' => $article->getID()]);
        $ticket = $this->createTicket(['itilcategories_id' => $category->getID()]);

        $suggestion = Knowledgebase::getSuggestedParent($ticket, Knowledgebase::getParentCandidates());

        $this->assertNotNull($suggestion);
        $this->assertSame($article->getID(), $suggestion['id']);
        $this->assertStringContainsString('WS printing', $suggestion['reason']);
    }

    public function testGetSuggestedParentUsesTheArticlesOfTheCategoryTickets(): void
    {
        $parent = $this->createArticle('WS hardware');
        $article = $this->createArticle('WS printer jam', [$parent->getID()]);
        $category = $this->createItem(ITILCategory::class, ['name' => 'WS hardware issues']);

        // An article written for another ticket of the same category
        $other_ticket = $this->createTicket(['itilcategories_id' => $category->getID()]);
        $this->createItem(KnowbaseItem_Item::class, [
            'knowbaseitems_id' => $article->getID(),
            'itemtype'         => Ticket::class,
            'items_id'         => $other_ticket->getID(),
        ]);

        $ticket = $this->createTicket(['itilcategories_id' => $category->getID()]);
        $suggestion = Knowledgebase::getSuggestedParent($ticket, Knowledgebase::getParentCandidates());

        $this->assertNotNull($suggestion);
        $this->assertSame($parent->getID(), $suggestion['id']);
        $this->assertStringContainsString('WS hardware issues', $suggestion['reason']);
    }

    public function testGetSuggestedParentFallsBackToTheRoot(): void
    {
        $suggestion = Knowledgebase::getSuggestedParent($this->createTicket(), Knowledgebase::getParentCandidates());

        $this->assertNotNull($suggestion);
        $this->assertSame(KnowbaseItem::getRootId(), $suggestion['id']);
    }

    public function testRenderParentVisibilityUnderTheRoot(): void
    {
        // The root article never grants access to its descendants
        $this->assertStringContainsString(
            'only be visible to you and to knowledge base administrators',
            Knowledgebase::renderParentVisibility(KnowbaseItem::getRootId()),
        );
    }

    public function testRenderParentVisibilityIsInherited(): void
    {
        $grand_parent = $this->createArticle('WS network');
        $parent = $this->createArticle('WS wifi', [$grand_parent->getID()]);
        $this->createItem(Entity_KnowbaseItem::class, [
            'knowbaseitems_id' => $grand_parent->getID(),
            'entities_id'      => $this->getTestRootEntity(true),
            'is_recursive'     => 1,
        ]);
        $this->createItem(KnowbaseItem_Profile::class, [
            'knowbaseitems_id' => $parent->getID(),
            'profiles_id'      => getItemByTypeName(Profile::class, 'Technician', true),
            'entities_id'      => $this->getTestRootEntity(true),
        ]);

        $html = Knowledgebase::renderParentVisibility($parent->getID());

        $this->assertStringContainsString('_test_root_entity (and sub-entities)', $html);
        $this->assertStringContainsString('Technician', $html);
        $this->assertStringNotContainsString('only be visible to you', $html);
    }

    public function testCreateKnowbaseItemUnderTheParentAndLinkedToTheTicket(): void
    {
        $parent = $this->createArticle('WS printers');
        $ticket = $this->createTicket();

        $id = Knowledgebase::createKnowbaseItem('Printer jam', '<p>Open the tray</p>', $parent->getID(), $ticket->getID());

        $this->assertIsInt($id);
        $article = new KnowbaseItem();
        $this->assertTrue($article->getFromDB($id));
        $this->assertEquals([$parent->getID()], $article->fields['_parents']);
        $this->assertTrue((new KnowbaseItem_Item())->getFromDBByCrit([
            'knowbaseitems_id' => $id,
            'itemtype'         => Ticket::class,
            'items_id'         => $ticket->getID(),
        ]));
    }

    public function testCreateKnowbaseItemWithAnUnknownParent(): void
    {
        $id = Knowledgebase::createKnowbaseItem('Printer jam', '<p>Open the tray</p>', 999999);

        $this->assertIsInt($id);
        $article = new KnowbaseItem();
        $this->assertTrue($article->getFromDB($id));
        $this->assertEquals([KnowbaseItem::getRootId()], $article->fields['_parents']);
    }

    public function testGetFullTicketContent(): void
    {
        $ticket = $this->createTicket();
        $this->createItem(ITILFollowup::class, [
            'itemtype' => Ticket::class,
            'items_id' => $ticket->getID(),
            'content'  => 'The paper tray is empty',
        ]);
        $this->createItem(ITILSolution::class, [
            'itemtype' => Ticket::class,
            'items_id' => $ticket->getID(),
            'content'  => 'Paper added in the tray',
        ]);

        $content = Knowledgebase::getFullTicketContent($ticket->getID());

        $this->assertIsString($content);
        $this->assertStringStartsWith('Ticket Title: Printer is down', $content);
        $this->assertStringContainsString('The printer of the second floor does not print anymore', $content);
        $this->assertStringContainsString("Followups:\n- The paper tray is empty", $content);
        $this->assertStringContainsString("Solutions:\n- Paper added in the tray", $content);
    }

    public function testGetFullTicketContentOfAnUnknownTicket(): void
    {
        $this->assertFalse(Knowledgebase::getFullTicketContent(999999));
    }
}
