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
use Glpi\Application\View\TemplateRenderer;
use Ticket;

/**
 * Class GenerateAnswer
 */
class GenerateAnswer extends CommonDBTM
{
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
