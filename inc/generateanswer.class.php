<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */

use Glpi\Application\View\TemplateRenderer;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginWikitsemanticsGenerateAnswer
 */
class PluginWikitsemanticsGenerateAnswer extends CommonDBTM
{
    /**
     * Prepare and generate AI answer for a ticket
     *
     * @param int $ticketId The ticket ID to process
     * @return string|bool The generated answer or false on error
     */
   public function prepareToGenerateAnswer($ticketId) {
       $ticket = new Ticket();
       $ticketitems = $ticket->find(['id' => (int)$ticketId]);

      if (empty($ticketitems)) {
          Toolbox::logDebug("Wikit Semantics: Ticket $ticketId not found");
          return false;
      }

       $config = new PluginWikitsemanticsConfig();

      foreach ($ticketitems as $ticketitem) {
         if (empty($ticketitem['content'])) {
             Toolbox::logDebug("Wikit Semantics: Ticket $ticketId has no content");
             return false;
         }
          return $config->testConnection(['query' => htmlspecialchars_decode($ticketitem['content'])]);
      }
       return false;
   }

    /**
     * Get ticket content by ID
     *
     * @param int $ticketId Ticket ID
     * @return string|bool Ticket content or false on error
     */
   public function getTicketContent($ticketId) {
       $ticket = new Ticket();
       $ticketitems = $ticket->find(['id' => (int)$ticketId]);

      if (empty($ticketitems)) {
          Toolbox::logDebug("Wikit Semantics: Ticket $ticketId not found");
          return false;
      }

      foreach ($ticketitems as $ticketitem) {
         if (empty($ticketitem['content'])) {
             Toolbox::logDebug("Wikit Semantics: Ticket $ticketId has no content");
             return false;
         }
          return htmlspecialchars_decode($ticketitem['content']);
      }
       return false;
   }

    /**
     * Display the AJAX modal for AI answer generation
     * @return void
     */
   public function showAjaxModal() {
       static $modalCreated = false;
      if ($modalCreated) {
          return;
      }
       $modalCreated = true;

       // Render Twig template for modal
       $twig = TemplateRenderer::getInstance();
       $modalHtml = $twig->render('@wikitsemantics/answer_modal.html.twig', []);

       // Store the modal HTML in a global variable for lazy injection on button click
       echo '<script type="text/javascript">';
       echo 'if (typeof window.wikitSemanticsModalHTML === "undefined") {';
       echo '    window.wikitSemanticsModalHTML = ' . json_encode($modalHtml, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) . ';';
       echo '}';
       echo '</script>';
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
   private function showWikitSemanticsButton($ticketId, $itemType, $containerSelector, $buttonLabel) {
       $this->showAjaxModal();

       // Get config to check if streaming is enabled
       $config = PluginWikitsemanticsConfig::getConfig();
       $isStreamingEnabled = isset($config->fields['is_streaming_enabled']) ? (int)$config->fields['is_streaming_enabled'] : 0;

       $pluginWebPath = '../plugins/wikitsemantics';

       // Generate a unique ID for this button container
       $containerId = 'wikitsemantics-button-' . $itemType . '-' . $ticketId;

       // Render the button container with data attributes
       // JavaScript in wikitsemantics.js will auto-initialize this
      echo '<div id="' . $containerId . '"
                 class="wikitsemantics-button-container"
                 data-ticket-id="' . (int)$ticketId . '"
                 data-item-type="' . htmlspecialchars($itemType, ENT_QUOTES, 'UTF-8') . '"
                 data-container-selector="' . htmlspecialchars($containerSelector, ENT_QUOTES, 'UTF-8') . '"
                 data-button-label="' . htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') . '"
                 data-streaming-enabled="' . $isStreamingEnabled . '"
                 data-ajax-url="' . htmlspecialchars($pluginWebPath . '/ajax/generateanswer.php', ENT_QUOTES, 'UTF-8') . '"
                 data-ajax-stream-url="' . htmlspecialchars($pluginWebPath . '/ajax/generateanswer_stream.php', ENT_QUOTES, 'UTF-8') . '"
                 data-label-add="' . htmlspecialchars(__('Add to ticket', 'wikitsemantics'), ENT_QUOTES, 'UTF-8') . '"
                 data-label-close="' . htmlspecialchars(__('Close', 'wikitsemantics'), ENT_QUOTES, 'UTF-8') . '"
                 data-label-error="' . htmlspecialchars(__('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics'), ENT_QUOTES, 'UTF-8') . '">
            </div>';
   }

    /**
     * Show AI suggestion button for ITIL Followup form
     *
     * @param int $ticketId Ticket ID
     * @return void
     */
   public function showWikitSemanticsButtonITILFollowup($ticketId) {
       $this->showWikitSemanticsButton(
           $ticketId,
           'followup',
           '.itilfollowup form[name=asset_form] div.row .order-first .row',
           __('Suggest an answer with AI', 'wikitsemantics')
       );
   }

    /**
     * Show AI suggestion button for ITIL Solution form
     *
     * @param int $ticketId Ticket ID
     * @return void
     */
   public function showWikitSemanticsButtonITILSolution($ticketId) {
       $this->showWikitSemanticsButton(
           $ticketId,
           'solution',
           '.itilsolution form[name=asset_form] div.row .order-first .row',
           __('Suggest a solution with AI', 'wikitsemantics')
       );
   }

    /**
     * Show AI suggestion button for Ticket Task form
     *
     * @param int $ticketId Ticket ID
     * @return void
     */
   public function showWikitSemanticsButtonTicketTask($ticketId) {
       $this->showWikitSemanticsButton(
           $ticketId,
           'task',
           '.itiltask form[name=asset_form] div.row .order-first .row',
           __('Suggest a solution with AI', 'wikitsemantics')
       );
   }
}
