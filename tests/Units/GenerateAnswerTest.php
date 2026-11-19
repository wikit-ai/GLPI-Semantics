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
use GlpiPlugin\Wikitsemantics\GenerateAnswer;
use ITILFollowup;
use ITILSolution;
use Ticket;
use TicketTask;

final class GenerateAnswerTest extends DbTestCase
{
    public function testGetTicketContent(): void
    {
        $this->login();
        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Printer is down',
            'content'     => 'The printer does not print &amp; blinks',
            'entities_id' => $this->getTestRootEntity(true),
        ]);

        $this->assertSame('The printer does not print & blinks', (new GenerateAnswer())->getTicketContent($ticket->getID()));
    }

    public function testGetTicketContentOfAnUnknownTicket(): void
    {
        $this->assertFalse((new GenerateAnswer())->getTicketContent(999999));
    }

    /**
     * Ticket with a public task, a public followup, a private followup and a solution
     */
    private function createTicketWithConversation(): Ticket
    {
        $this->login();
        $ticket = $this->createItem(Ticket::class, [
            'name'        => 'Printer is down',
            'content'     => '<p>The printer <strong>blinks</strong></p>',
            'entities_id' => $this->getTestRootEntity(true),
        ], ['content']);
        $ticketId = $ticket->getID();

        $this->createItem(ITILFollowup::class, [
            'itemtype' => Ticket::class,
            'items_id' => $ticketId,
            'content'  => 'Did you restart it?',
            'date'     => '2026-11-10 10:00:00',
        ]);
        $this->createItem(TicketTask::class, [
            'tickets_id' => $ticketId,
            'content'    => 'Replaced the toner',
            'date'       => '2026-11-10 09:00:00',
            'is_private' => 0,
        ]);
        $this->createItem(ITILFollowup::class, [
            'itemtype'   => Ticket::class,
            'items_id'   => $ticketId,
            'content'    => 'Internal note: vendor contract expired',
            'is_private' => 1,
            'date'       => '2026-11-10 11:00:00',
        ]);
        $this->createItem(ITILSolution::class, [
            'itemtype' => Ticket::class,
            'items_id' => $ticketId,
            'content'  => 'Toner replaced and printer restarted',
        ]);

        return $ticket;
    }

    public function testGetTicketConversation(): void
    {
        $ticket = $this->createTicketWithConversation();

        $conversation = (new GenerateAnswer())->getTicketConversation($ticket->getID());

        $this->assertIsString($conversation);
        $this->assertStringStartsWith('Ticket title: Printer is down', $conversation);
        $this->assertStringContainsString('The printer **blinks**', $conversation);
        $this->assertStringContainsString('Toner replaced and printer restarted', $conversation);
        // Private messages must never end up in an answer sent to the requester
        $this->assertStringNotContainsString('Internal note', $conversation);
        // Chronological order, whatever the type of the message
        $taskPos = strpos($conversation, 'Replaced the toner');
        $followupPos = strpos($conversation, 'Did you restart it?');
        $this->assertIsInt($taskPos);
        $this->assertIsInt($followupPos);
        $this->assertLessThan($followupPos, $taskPos);
        $this->assertStringContainsString('Task — ', $conversation);
        $this->assertStringContainsString('Followup — ', $conversation);
    }

    public function testGetTicketConversationKeepsTheMostRecentMessages(): void
    {
        $ticket = $this->createTicketWithConversation();

        $full = (new GenerateAnswer())->getTicketConversation($ticket->getID());
        $this->assertIsString($full);
        $headerLength = mb_strpos($full, 'Conversation (oldest first)');
        $lastMessagePos = mb_strrpos($full, 'Solution');
        $this->assertIsInt($headerLength);
        $this->assertIsInt($lastMessagePos);

        // Room for the title, the description and the last message only
        $maxLength = $headerLength + mb_strlen(mb_substr($full, $lastMessagePos)) + 5;
        $conversation = (new GenerateAnswer())->getTicketConversation($ticket->getID(), $maxLength);

        $this->assertIsString($conversation);
        $this->assertStringContainsString('The printer **blinks**', $conversation);
        $this->assertStringContainsString('Toner replaced and printer restarted', $conversation);
        $this->assertStringNotContainsString('Replaced the toner', $conversation);
        $this->assertStringContainsString('[2 older messages omitted]', $conversation);
    }

    public function testGetTicketConversationOfAnUnknownTicket(): void
    {
        $this->assertFalse((new GenerateAnswer())->getTicketConversation(999999));
    }
}
