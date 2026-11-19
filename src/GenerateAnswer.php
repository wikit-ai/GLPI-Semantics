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
use CommonITILActor;
use CommonITILValidation;
use Glpi\Application\View\TemplateRenderer;
use ITILFollowup;
use ITILSolution;
use Ticket;
use TicketTask;

/**
 * Class GenerateAnswer
 */
class GenerateAnswer extends CommonDBTM
{
    /**
     * Maximum length of the ticket conversation sent as context, in characters
     */
    public const CONVERSATION_MAX_LENGTH = 20000;

    /**
     * Get ticket content by ID
     *
     * @param int $ticketId Ticket ID
     * @return string|bool Ticket content or false on error
     */
    public function getTicketContent($ticketId)
    {
        $ticket = new Ticket();
        $ticketitems = $ticket->find(['id' => (int) $ticketId]);

        if (empty($ticketitems)) {
            Logger::warning("Ticket not found", ['ticket_id' => $ticketId]);
            return false;
        }

        $ticketitem = reset($ticketitems);
        if (empty($ticketitem['content'])) {
            Logger::warning("Ticket has no content", ['ticket_id' => $ticketId]);
            return false;
        }

        return htmlspecialchars_decode($ticketitem['content']);
    }

    /**
     * Whole public conversation of a ticket, sent to the answer suggestion application as context:
     * title, description, then the followups, tasks and solutions in chronological order, with
     * their author and date.
     *
     * Private followups and tasks are never included, so that internal notes cannot end up in an
     * answer sent to the requester. When the conversation is too long, the oldest messages are
     * omitted (the title and the description are always kept).
     *
     * @param int $ticketId  Ticket ID
     * @param int $maxLength Maximum length of the conversation, in characters
     * @return string|false Conversation as plain text/Markdown, false when the ticket is not found or empty
     */
    public function getTicketConversation(int $ticketId, int $maxLength = self::CONVERSATION_MAX_LENGTH)
    {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) {
            Logger::warning("Ticket not found", ['ticket_id' => $ticketId]);
            return false;
        }

        $header = '';
        $title = $this->toString($ticket->fields['name'] ?? null);
        if ($title !== '') {
            $header .= 'Ticket title: ' . $title . "\n\n";
        }

        $description = Config::htmlToMarkdown($this->toString($ticket->fields['content'] ?? null));
        if ($description !== '') {
            $header .= sprintf(
                "Ticket description (%s):\n%s\n\n",
                $this->describeAuthor(
                    $ticket,
                    $this->toInt($ticket->fields['users_id_recipient'] ?? null),
                    $this->toString($ticket->fields['date'] ?? null),
                ),
                $description,
            );
        }

        $messages = $this->getConversationMessages($ticket);

        if ($header === '' && $messages === []) {
            Logger::warning("Ticket has no content", ['ticket_id' => $ticketId]);
            return false;
        }

        // Most recent messages first, until the maximum length is reached
        $kept = [];
        $length = mb_strlen($header);
        foreach (array_reverse($messages) as $message) {
            $length += mb_strlen($message);
            if ($length > $maxLength && $kept !== []) {
                break;
            }

            $kept[] = $message;
        }

        $kept = array_reverse($kept);

        if ($kept === []) {
            return trim($header);
        }

        $conversation = $header . "Conversation (oldest first):\n\n";
        $omitted = count($messages) - count($kept);
        if ($omitted > 0) {
            $conversation .= sprintf("[%d older messages omitted]\n\n", $omitted);
        }

        return trim($conversation . implode("\n\n", $kept));
    }

    /**
     * Public followups, tasks and solutions of a ticket that the current user can see, formatted
     * and in chronological order
     *
     * @return list<string>
     */
    private function getConversationMessages(Ticket $ticket): array
    {
        $ticketId = $ticket->getID();

        /** @var list<array{type: string, date: string, id: int, users_id: int, content: string}> $items */
        $items = [];

        $followup = new ITILFollowup();
        foreach ($followup->find(['itemtype' => Ticket::class, 'items_id' => $ticketId, 'is_private' => 0]) as $row) {
            if (!is_array($row)) {
                continue;
            }

            // Same as getFromDB() without reading again the row already loaded by find()
            $followup->getFromResultSet($row);
            $followup->post_getFromDB();
            if ($followup->canViewItem()) {
                $items[] = $this->toConversationItem('Followup', $row, 'date');
            }
        }

        $task = new TicketTask();
        foreach ($task->find(['tickets_id' => $ticketId, 'is_private' => 0]) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $task->getFromResultSet($row);
            $task->post_getFromDB();
            if ($task->canViewItem()) {
                $items[] = $this->toConversationItem('Task', $row, 'date');
            }
        }

        $solution = new ITILSolution();
        foreach ($solution->find(['itemtype' => Ticket::class, 'items_id' => $ticketId]) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = match ($this->toInt($row['status'] ?? null)) {
                CommonITILValidation::ACCEPTED => 'Solution (accepted)',
                CommonITILValidation::REFUSED  => 'Solution (refused)',
                CommonITILValidation::WAITING  => 'Solution (waiting for approval)',
                default                        => 'Solution',
            };
            $items[] = $this->toConversationItem($type, $row, 'date_creation');
        }

        usort($items, static fn(array $a, array $b): int => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);

        $messages = [];
        foreach ($items as $item) {
            $content = Config::htmlToMarkdown($item['content']);
            if ($content === '') {
                continue;
            }

            $messages[] = sprintf(
                "%s — %s:\n%s",
                $item['type'],
                $this->describeAuthor($ticket, $item['users_id'], $item['date']),
                $content,
            );
        }

        return $messages;
    }

    /**
     * Message of the conversation built from a database row
     *
     * @param array<mixed> $row       Followup, task or solution row
     * @param string       $dateField Field holding the date of the message
     * @return array{type: string, date: string, id: int, users_id: int, content: string}
     */
    private function toConversationItem(string $type, array $row, string $dateField): array
    {
        $date = $this->toString($row[$dateField] ?? null);

        return [
            'type'     => $type,
            'date'     => $date !== '' ? $date : $this->toString($row['date_creation'] ?? null),
            'id'       => $this->toInt($row['id'] ?? null),
            'users_id' => $this->toInt($row['users_id'] ?? null),
            'content'  => $this->toString($row['content'] ?? null),
        ];
    }

    private function toString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Author of a message: name, role in the ticket and date
     */
    private function describeAuthor(Ticket $ticket, int $userId, string $date): string
    {
        $parts = [];
        if ($userId > 0) {
            $role = match (true) {
                $ticket->isUser(CommonITILActor::REQUESTER, $userId) => 'requester',
                $ticket->isUser(CommonITILActor::ASSIGN, $userId)    => 'technician',
                default                                              => null,
            };
            $name = getUserName($userId);
            $name = is_string($name) ? $name : (string) $userId;
            $parts[] = $role !== null ? sprintf('%s, %s', $name, $role) : $name;
        }

        if ($date !== '') {
            $parts[] = substr($date, 0, 16);
        }

        return $parts === [] ? 'unknown author' : implode(', ', $parts);
    }

    /**
     * Generic method to show AI suggestion button for any ITIL item type
     * Uses data-attributes for clean separation between PHP and JavaScript
     *
     * @param int $ticketId Ticket ID
     * @param string $itemType Item type: 'followup', 'solution', or 'task'
     * @param string $containerSelector CSS selector for the container where button should be inserted
     * @param string $buttonLabel Translated button label
     * @return void
     */
    private function showWikitSemanticsButton($ticketId, $itemType, $containerSelector, $buttonLabel)
    {
        // The modal markup is only needed once per page
        static $modalRendered = false;

        $config = Config::getConfig();

        TemplateRenderer::getInstance()->display('@wikitsemantics/answer_button.html.twig', [
            'with_modal'         => !$modalRendered,
            'ticket_id'          => (int) $ticketId,
            'item_type'          => $itemType,
            'container_selector' => $containerSelector,
            'button_label'       => $buttonLabel,
            'streaming_enabled'  => (int) ($config->fields['is_streaming_enabled'] ?? 0),
            'plugin_path'        => Config::getPluginWebPath(),
        ]);
        $modalRendered = true;
    }

    /**
     * Show AI suggestion button for ITIL Followup form
     *
     * @param int $ticketId Ticket ID
     * @return void
     */
    public function showWikitSemanticsButtonITILFollowup($ticketId)
    {
        $this->showWikitSemanticsButton(
            $ticketId,
            'followup',
            '.itilfollowup form[name=asset_form] div.row .order-first .row',
            __('Suggest an answer with AI', 'wikitsemantics'),
        );
    }

    /**
     * Show AI suggestion button for ITIL Solution form
     *
     * @param int $ticketId Ticket ID
     * @return void
     */
    public function showWikitSemanticsButtonITILSolution($ticketId)
    {
        $this->showWikitSemanticsButton(
            $ticketId,
            'solution',
            '.itilsolution form[name=asset_form] div.row .order-first .row',
            __('Suggest a solution with AI', 'wikitsemantics'),
        );
    }

    /**
     * Show AI suggestion button for Ticket Task form
     *
     * @param int $ticketId Ticket ID
     * @return void
     */
    public function showWikitSemanticsButtonTicketTask($ticketId)
    {
        $this->showWikitSemanticsButton(
            $ticketId,
            'task',
            '.itiltask form[name=asset_form] div.row .order-first .row',
            __('Suggest a solution with AI', 'wikitsemantics'),
        );
    }
}
