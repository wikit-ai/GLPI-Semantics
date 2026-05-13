<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

use Glpi\Application\View\TemplateRenderer;

/**
 * Class PluginWikitsemanticsKnowledgebase
 * Adds a tab on Tickets to generate Knowledge Base articles from ticket content
 */
class PluginWikitsemanticsKnowledgebase extends CommonDBTM
{
   public static $rightname = 'plugin_wikitsemantics_knowledgebase';

    /**
     * Get the tab name for an item
     *
     * @param CommonGLPI $item         Item instance
     * @param int        $withtemplate Template flag
     * @return string Tab name or empty string
     */
   public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {
      if ($item->getType() !== 'Ticket') {
          return '';
      }

      if (!Session::haveRight(self::$rightname, READ)) {
          return '';
      }

       // Check if KB feature is enabled in config
       $config = PluginWikitsemanticsConfig::getConfig();
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
   public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool {
      if ($item->getType() !== 'Ticket') {
          return false;
      }

       $ticketId = $item->getID();
       $config = PluginWikitsemanticsConfig::getConfig();
       $isStreamingEnabled = isset($config->fields['is_streaming_enabled']) ? (int)$config->fields['is_streaming_enabled'] : 0;
       $pluginWebPath = '../plugins/wikitsemantics';

       $twig = TemplateRenderer::getInstance();
       $twig->display('@wikitsemantics/knowledgebase_tab.html.twig', [
           'ticket_id'           => $ticketId,
           'can_create_kb'       => Session::haveRight(self::$rightname, UPDATE)
                                    && KnowbaseItem::canCreate(),
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
   public static function getFullTicketContent($ticketId) {
       $ticket = new Ticket();
      if (!$ticket->getFromDB((int)$ticketId)) {
          PluginWikitsemanticsLogger::warning("Ticket not found", ['ticket_id' => $ticketId, 'context' => 'KB']);
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
           'items_id' => (int)$ticketId,
           'itemtype' => 'Ticket',
       ], ['date_creation ASC']);

      if (!empty($followups)) {
          $content .= "Followups:\n";
         foreach ($followups as $fu) {
            if (!empty($fu['content'])) {
                $content .= "- " . htmlspecialchars_decode($fu['content']) . "\n";
            }
         }
          $content .= "\n";
      }

       // Solutions
       $solution = new ITILSolution();
       $solutions = $solution->find([
           'items_id' => (int)$ticketId,
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
           'tickets_id' => (int)$ticketId,
       ], ['date_creation ASC']);

      if (!empty($tasks)) {
          $content .= "Tasks:\n";
         foreach ($tasks as $t) {
            if (!empty($t['content'])) {
                $content .= "- " . htmlspecialchars_decode($t['content']) . "\n";
            }
         }
          $content .= "\n";
      }

      if (empty(trim($content))) {
          PluginWikitsemanticsLogger::warning("Ticket has no content", ['ticket_id' => $ticketId, 'context' => 'KB']);
          return false;
      }

       return $content;
   }

    /**
     * Create a KnowbaseItem from generated content
     *
     * @param string $name    Article title
     * @param string $answer  Article content (HTML)
     * @return int|false New KnowbaseItem ID or false on failure
     */
   public static function createKnowbaseItem($name, $answer) {
      if (!KnowbaseItem::canCreate()) {
          PluginWikitsemanticsLogger::warning("User cannot create KnowbaseItem", ['context' => 'KB']);
          return false;
      }

       $kb = new KnowbaseItem();
       $id = $kb->add([
           'name'     => $name,
           'answer'   => $answer,
           'is_faq'   => 0,
           'users_id' => Session::getLoginUserID(),
       ]);

      if ($id === false) {
          PluginWikitsemanticsLogger::error("Failed to create KnowbaseItem", ['context' => 'KB']);
          return false;
      }

       return $id;
   }
}
