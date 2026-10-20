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
use CommonGLPI;
use DBmysql;
use Dropdown;
use Entity;
use Entity_KnowbaseItem;
use Glpi\Application\View\TemplateRenderer;
use Glpi\Knowbase\Aside\Builder;
use Group;
use Group_KnowbaseItem;
use ITILCategory;
use ITILFollowup;
use ITILSolution;
use KnowbaseItem;
use KnowbaseItem_Item;
use KnowbaseItem_KnowbaseItem;
use KnowbaseItem_Profile;
use KnowbaseItem_User;
use Profile as GlpiProfile;
use Session;
use Ticket;
use TicketTask;

/**
 * Class Knowledgebase
 * Adds a tab on Tickets to generate Knowledge Base articles from ticket content
 */
class Knowledgebase extends CommonDBTM
{
    public static string $rightname = 'plugin_wikitsemantics_knowledgebase';

    /**
     * Get the tab name for an item
     *
     * @param CommonGLPI $item         Item instance
     * @param int        $withtemplate Template flag
     * @return string Tab name or empty string
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!$item instanceof Ticket) {
            return '';
        }

        if (!Session::haveRight(self::$rightname, READ)) {
            return '';
        }

        // Check if KB feature is enabled in config
        $config = Config::getConfig();
        if (empty($config->fields['is_kb_enabled'])) {
            return '';
        }

        return __('Wikit Semantics KB', 'wikitsemantics');
    }

    /**
     * Display the tab content for an item
     *
     * @param CommonGLPI $item         Item instance
     * @param int        $tabnum       Tab number
     * @param int        $withtemplate Template flag
     * @return bool True if content was displayed
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if (!$item instanceof Ticket) {
            return false;
        }

        $ticketId = $item->getID();
        $config = Config::getConfig();

        $canCreateKb = Session::haveRight(self::$rightname, UPDATE) && KnowbaseItem::canCreate();
        $parentCandidates = $canCreateKb ? self::getParentCandidates() : [];
        $suggestedParent = $canCreateKb ? self::getSuggestedParent($item, $parentCandidates) : null;
        $isStreamingEnabled = isset($config->fields['is_streaming_enabled']) ? (int) $config->fields['is_streaming_enabled'] : 0;
        $pluginWebPath = Config::getPluginWebPath();

        $twig = TemplateRenderer::getInstance();
        $twig->display('@wikitsemantics/knowledgebase_tab.html.twig', [
            'ticket_id'           => $ticketId,
            'can_create_kb'       => $canCreateKb,
            'parent_candidates'   => $parentCandidates,
            'suggested_parent'    => $suggestedParent,
            'parent_visibility'   => $suggestedParent !== null
                                     ? self::renderParentVisibility($suggestedParent['id'])
                                     : '',
            'streaming_enabled'   => $isStreamingEnabled,
            'plugin_path'         => $pluginWebPath,
            'ajax_url'            => $pluginWebPath . '/ajax/generatekb.php',
            'ajax_stream_url'     => $pluginWebPath . '/ajax/generatekb_stream.php',
            'label_generate'      => __('Generate a Knowledge Base article from this ticket', 'wikitsemantics'),
            'label_create'        => __('Create Knowledge Base article', 'wikitsemantics'),
            'label_discard'       => __('Discard', 'wikitsemantics'),
            'label_regenerate'    => __('Regenerate', 'wikitsemantics'),
            'label_error'         => __('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics'),
        ]);

        return true;
    }

    /**
     * Get full ticket content including description, followups, solutions and tasks
     *
     * @param int $ticketId Ticket ID
     * @return string|false Aggregated ticket content or false on error
     */
    public static function getFullTicketContent($ticketId)
    {
        $ticket = new Ticket();
        if (!$ticket->getFromDB((int) $ticketId)) {
            Logger::warning("Ticket not found", ['ticket_id' => $ticketId, 'context' => 'KB']);
            return false;
        }

        $content = '';

        // Ticket description
        if (!empty($ticket->fields['content'])) {
            $content .= "Ticket Description:\n" . htmlspecialchars_decode($ticket->fields['content']) . "\n\n";
        }

        // Ticket title
        if (!empty($ticket->fields['name'])) {
            $content = "Ticket Title: " . $ticket->fields['name'] . "\n\n" . $content;
        }

        // Followups
        $followup = new ITILFollowup();
        $followups = $followup->find([
            'items_id' => (int) $ticketId,
            'itemtype' => 'Ticket',
        ], ['date_creation ASC']);

        if (!empty($followups)) {
            $content .= "Followups:\n";
            foreach ($followups as $fu) {
                // Skip private followups the current user is not allowed to see
                if (!$followup->getFromDB($fu['id']) || !$followup->canViewItem()) {
                    continue;
                }

                if (!empty($fu['content'])) {
                    $content .= "- " . htmlspecialchars_decode($fu['content']) . "\n";
                }
            }

            $content .= "\n";
        }

        // Solutions
        $solution = new ITILSolution();
        $solutions = $solution->find([
            'items_id' => (int) $ticketId,
            'itemtype' => 'Ticket',
        ], ['date_creation ASC']);

        if (!empty($solutions)) {
            $content .= "Solutions:\n";
            foreach ($solutions as $sol) {
                if (!empty($sol['content'])) {
                    $content .= "- " . htmlspecialchars_decode($sol['content']) . "\n";
                }
            }

            $content .= "\n";
        }

        // Tasks
        $task = new TicketTask();
        $tasks = $task->find([
            'tickets_id' => (int) $ticketId,
        ], ['date_creation ASC']);

        if (!empty($tasks)) {
            $content .= "Tasks:\n";
            foreach ($tasks as $t) {
                // Skip private tasks the current user is not allowed to see
                if (!$task->getFromDB($t['id']) || !$task->canViewItem()) {
                    continue;
                }

                if (!empty($t['content'])) {
                    $content .= "- " . htmlspecialchars_decode($t['content']) . "\n";
                }
            }

            $content .= "\n";
        }

        if (empty(trim($content))) {
            Logger::warning("Ticket has no content", ['ticket_id' => $ticketId, 'context' => 'KB']);
            return false;
        }

        return $content;
    }

    /**
     * Articles under which the generated article can be created: the knowledge base tree
     * visible to the current user, with the full path in the labels so that the flat
     * dropdown still reads as a tree.
     *
     * @return array<int, string> Article ID => label
     */
    public static function getParentCandidates(): array
    {
        if (!KnowbaseItem::hasRoot()) {
            return [];
        }

        $tree = (new Builder())->buildUnfoldedTree();

        // Level order, so that an article with several parents keeps its shortest path
        $candidates = [];
        $queue = [];
        foreach ($tree->getArticles() as $article) {
            $queue[] = [$article, ''];
        }

        for ($i = 0; isset($queue[$i]); $i++) {
            [$article, $prefix] = $queue[$i];
            $label = $prefix === '' ? $article->title : $prefix . ' > ' . $article->title;
            $candidates[$article->id] ??= $label;

            foreach ($article->getChildren() as $child) {
                $queue[] = [$child, $label];
            }
        }

        return $candidates;
    }

    /**
     * Suggest a parent article for the article generated from a ticket.
     *
     * In order: the article linked to the ticket category, the parent of the articles
     * already linked to tickets of the same category, the root of the knowledge base.
     *
     * @param Ticket                $ticket     Ticket the article is generated from
     * @param array<int, string>    $candidates See `getParentCandidates()`
     * @return array{id: int, reason: string}|null Null when there is no candidate
     */
    public static function getSuggestedParent(Ticket $ticket, array $candidates): ?array
    {
        /** @var DBmysql $DB */
        global $DB;

        if ($candidates === []) {
            return null;
        }

        $category = new ITILCategory();
        $categoryId = (int) ($ticket->fields['itilcategories_id'] ?? 0);
        if ($categoryId > 0 && $category->getFromDB($categoryId)) {
            $categoryArticleId = (int) ($category->fields['knowbaseitems_id'] ?? 0);
            if (isset($candidates[$categoryArticleId])) {
                return [
                    'id'     => $categoryArticleId,
                    'reason' => sprintf(
                        __('Article linked to the ticket category "%s"', 'wikitsemantics'),
                        $category->fields['completename'],
                    ),
                ];
            }

            // Parents of the articles linked to the other tickets of the category
            $iterator = $DB->request([
                'SELECT'     => [KnowbaseItem_KnowbaseItem::getTable() . '.knowbaseitems_id_parent AS parent_id'],
                'FROM'       => KnowbaseItem_Item::getTable(),
                'INNER JOIN' => [
                    Ticket::getTable() => [
                        'ON' => [
                            KnowbaseItem_Item::getTable() => 'items_id',
                            Ticket::getTable()            => 'id', [
                                'AND' => [KnowbaseItem_Item::getTable() . '.itemtype' => Ticket::class],
                            ],
                        ],
                    ],
                    KnowbaseItem_KnowbaseItem::getTable() => [
                        'ON' => [
                            KnowbaseItem_Item::getTable()         => 'knowbaseitems_id',
                            KnowbaseItem_KnowbaseItem::getTable() => 'knowbaseitems_id',
                        ],
                    ],
                ],
                'WHERE'      => [
                    Ticket::getTable() . '.itilcategories_id' => $categoryId,
                    Ticket::getTable() . '.id'                => ['<>', $ticket->getID()],
                ],
            ]);

            $counts = [];
            foreach ($iterator as $row) {
                $parentId = (int) $row['parent_id'];
                if (isset($candidates[$parentId]) && !KnowbaseItem::isRootId($parentId)) {
                    $counts[$parentId] = ($counts[$parentId] ?? 0) + 1;
                }
            }

            if ($counts !== []) {
                arsort($counts);
                return [
                    'id'     => array_key_first($counts),
                    'reason' => sprintf(
                        __('Parent of the articles already linked to tickets of the category "%s"', 'wikitsemantics'),
                        $category->fields['completename'],
                    ),
                ];
            }
        }

        $rootId = KnowbaseItem::getRootId();
        return [
            'id'     => isset($candidates[$rootId]) ? $rootId : array_key_first($candidates),
            'reason' => __('No better match found: root of the knowledge base', 'wikitsemantics'),
        ];
    }

    /**
     * Describe who will be able to read an article created under the given parent.
     *
     * Since GLPI 12, an article is visible to the users who can see it or any of its
     * ancestors, except the root article which never grants access to its descendants.
     *
     * @param int $parentId Parent article ID
     * @return string HTML
     */
    public static function renderParentVisibility(int $parentId): string
    {
        $targets = [
            'entities' => [],
            'profiles' => [],
            'groups'   => [],
            'users'    => [],
        ];

        // Walk up the tree, the root article excluded
        $queue = [$parentId];
        $visited = [];
        while (($articleId = array_shift($queue)) !== null) {
            if (isset($visited[$articleId]) || KnowbaseItem::isRootId($articleId)) {
                continue;
            }

            $visited[$articleId] = true;

            $article = new KnowbaseItem();
            if (!$article->getFromDB($articleId)) {
                continue;
            }

            foreach (Entity_KnowbaseItem::getEntities($articleId) as $entityId => $rows) {
                foreach ((array) $rows as $row) {
                    $name = Dropdown::getDropdownName(Entity::getTable(), $entityId);
                    $targets['entities'][] = $row['is_recursive']
                        ? sprintf(__('%s (and sub-entities)', 'wikitsemantics'), $name)
                        : $name;
                }
            }

            foreach (array_keys(KnowbaseItem_Profile::getProfiles($articleId)) as $profileId) {
                $targets['profiles'][] = Dropdown::getDropdownName(GlpiProfile::getTable(), $profileId);
            }

            foreach (array_keys(Group_KnowbaseItem::getGroups($articleId)) as $groupId) {
                $targets['groups'][] = Dropdown::getDropdownName(Group::getTable(), $groupId);
            }

            foreach (array_keys(KnowbaseItem_User::getUsers($articleId)) as $userId) {
                $targets['users'][] = getUserName($userId);
            }

            foreach ((array) ($article->fields['_parents'] ?? []) as $ancestorId) {
                $queue[] = (int) $ancestorId;
            }
        }

        $labels = [
            'entities' => __('Entities', 'wikitsemantics'),
            'profiles' => __('Profiles', 'wikitsemantics'),
            'groups'   => __('Groups', 'wikitsemantics'),
            'users'    => __('Users', 'wikitsemantics'),
        ];

        $lines = [];
        foreach ($targets as $type => $names) {
            if ($names !== []) {
                $names = array_unique($names);
                sort($names);
                $lines[] = '<li><strong>' . htmlescape($labels[$type]) . '</strong> : '
                    . htmlescape(implode(', ', $names)) . '</li>';
            }
        }

        if ($lines === []) {
            return '<div class="alert alert-warning mb-0">'
                . htmlescape(__('No visibility is inherited from this parent: the article will only be visible to you and to knowledge base administrators until visibility targets are added.', 'wikitsemantics'))
                . '</div>';
        }

        return '<div class="text-muted">'
            . htmlescape(__('Readers inherited from the parent articles:', 'wikitsemantics'))
            . '<ul class="mb-0">' . implode('', $lines) . '</ul></div>';
    }

    /**
     * Create a KnowbaseItem from generated content
     *
     * @param string $name     Article title
     * @param string $answer   Article content (HTML)
     * @param int    $parentId Parent article ID, the root of the knowledge base when 0 or not readable
     * @param int    $ticketId Ticket the article is generated from, linked to the article when set
     * @return int|false New KnowbaseItem ID or false on failure
     */
    public static function createKnowbaseItem($name, $answer, int $parentId = 0, int $ticketId = 0)
    {
        if (!KnowbaseItem::canCreate()) {
            Logger::warning("User cannot create KnowbaseItem", ['context' => 'KB']);
            return false;
        }

        // Same check as the GLPI article creation: the parent must be readable
        $parentId = KnowbaseItem::getReadablePrefilledParentId($parentId);

        $kb = new KnowbaseItem();
        $id = $kb->add([
            'name'        => $name,
            'answer'      => $answer,
            'is_faq'      => 0,
            'users_id'    => Session::getLoginUserID(),
            'entities_id' => Session::getActiveEntity(),
            '_parents'    => $parentId !== null ? [$parentId] : [],
        ]);

        if ($id === false) {
            Logger::error("Failed to create KnowbaseItem", ['context' => 'KB']);
            return false;
        }

        if ($ticketId > 0) {
            $link = new KnowbaseItem_Item();
            if (!$link->add(['knowbaseitems_id' => $id, 'itemtype' => Ticket::class, 'items_id' => $ticketId])) {
                Logger::warning("Failed to link KnowbaseItem to ticket", ['context' => 'KB', 'ticket_id' => $ticketId]);
            }
        }

        return $id;
    }
}
