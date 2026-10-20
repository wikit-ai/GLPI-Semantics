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
use Ticket;

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
}
