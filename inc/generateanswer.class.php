<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2026 by the Wikit Development Team.
 * -------------------------------------------------------------------------
 */


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
    public function prepareToGenerateAnswer($ticketId)
    {
        $ticket = new Ticket();
        $ticketitems = $ticket->find(['id' => (int)$ticketId]);

        if (empty($ticketitems)) {
            Toolbox::logError("Wikit Semantics: Ticket $ticketId not found");
            return false;
        }

        $config = new PluginWikitsemanticsConfig();

        foreach ($ticketitems as $ticketitem) {
            if (empty($ticketitem['content'])) {
                Toolbox::logWarning("Wikit Semantics: Ticket $ticketId has no content");
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
    public function getTicketContent($ticketId)
    {
        $ticket = new Ticket();
        $ticketitems = $ticket->find(['id' => (int)$ticketId]);

        if (empty($ticketitems)) {
            Toolbox::logError("Wikit Semantics: Ticket $ticketId not found");
            return false;
        }

        foreach ($ticketitems as $ticketitem) {
            if (empty($ticketitem['content'])) {
                Toolbox::logWarning("Wikit Semantics: Ticket $ticketId has no content");
                return false;
            }
            return htmlspecialchars_decode($ticketitem['content']);
        }
        return false;
    }

    /**
     * Display the AJAX modal for AI answer generation
     * Creates a Bootstrap 5 modal if not already created
     * @return void
     */
    public function showAjaxModal()
    {
        static $modalCreated = false;
        if ($modalCreated) {
            return;
        }
        $modalCreated = true;
        echo Html::scriptBlock(
            "
            // Create modal and append it to body to avoid z-index issues
            if (!document.getElementById('popupAnswer')) {
                const modalHTML = `
                <div class=\"modal fade\" id=\"popupAnswer\" tabindex=\"-1\" aria-labelledby=\"popupAnswerLabel\" aria-hidden=\"true\">
                    <div class=\"modal-dialog modal-xl\" style=\"max-width: 1180px;\">
                        <div class=\"modal-content\">
                            <div class=\"modal-header\">
                                <h5 class=\"modal-title\" id=\"popupAnswerLabel\">" . __('Wikit Semantics Application Response', 'wikitsemantics') . "</h5>
                                <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"modal\" aria-label=\"Close\"></button>
                            </div>
                            <div class=\"modal-body\" style=\"min-height: 500px;\">
                                <div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHTML);
            }
            "
        );
    }

    /**
     * Show AI suggestion button for ITIL Followup form
     *
     * @param int $ticketId Ticket ID
     * @return void
     */
    public function showWikitSemanticsButtonITILFollowup($ticketId)
    {
        $this->showAjaxModal();
        $suggestAnswerText = htmlspecialchars(__('Suggest an answer with AI', 'wikitsemantics'), ENT_QUOTES, 'UTF-8');
        $ticketIdJson = json_encode((int)$ticketId);
        $ajaxUrl = json_encode(PLUGIN_WIKITSEMANTICS_WEBDIR . "/ajax/generateanswer.php");

        echo Html::scriptBlock(
            "
            const suggestAnswerTextFollowup = " . json_encode(__('Suggest an answer with AI', 'wikitsemantics'), JSON_HEX_APOS | JSON_HEX_QUOT) . ";

            const containerFollowup = document.querySelector('.itilfollowup form[name=asset_form] div.row .order-first .row');
            if (containerFollowup) {
                const wrapperFollowup = document.createElement('div');
                wrapperFollowup.className = 'form-field row col-12 mb-2';
                const labelFollowup = document.createElement('label');
                labelFollowup.className = 'col-form-label col-2 text-xxl-end';
                labelFollowup.textContent = ' ';
                const fieldContainerFollowup = document.createElement('div');
                fieldContainerFollowup.className = 'col-10 field-container';
                const buttonFollowup = document.createElement('a');
                buttonFollowup.className = 'btn btn-secondary overflow-hidden text-nowrap';
                buttonFollowup.setAttribute('title', suggestAnswerTextFollowup);
                buttonFollowup.setAttribute('data-bs-toggle', 'tooltip');
                buttonFollowup.setAttribute('data-bs-placement', 'top');
                buttonFollowup.setAttribute('data-bs-original-title', suggestAnswerTextFollowup);
                buttonFollowup.style.cursor = 'pointer';
                buttonFollowup.onclick = function(e) {
                    e.preventDefault();
                    // Open modal using Bootstrap 5 API
                    const modalElement = document.getElementById('popupAnswer');
                    if (modalElement) {
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();
                    }
                    // Then trigger the power action
                    powerActionFollowup();
                };
                buttonFollowup.innerHTML = '<i class=\"fas fa-wand-magic-sparkles\"></i>';
                fieldContainerFollowup.appendChild(buttonFollowup);
                wrapperFollowup.appendChild(labelFollowup);
                wrapperFollowup.appendChild(fieldContainerFollowup);
                containerFollowup.insertBefore(wrapperFollowup, containerFollowup.firstChild);
            }

            function closeFollowup(){
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
            }

            function addAnswerFollowup(result) {
                try {
                    let data_decode = result;

                    // Find TinyMCE iframe
                    const iframe = document.querySelector('.itilfollowup form[name=asset_form] div.row div.tox-editor-container iframe');
                    if (!iframe) {
                        alert('Erreur: Impossible de trouver l\\'éditeur de texte. Veuillez réessayer.');
                        return;
                    }

                    // Access iframe body directly
                    const iframeBody = iframe.contentWindow.document.body;
                    if (!iframeBody) {
                        alert('Erreur: Impossible d\\'accéder à l\\'éditeur de texte.');
                        return;
                    }

                    // Insert content directly into the body
                    iframeBody.innerHTML = data_decode.content;
                } catch (error) {
                    console.error('[Wikit Semantics] Error:', error);
                    alert('Erreur lors de l\\'ajout du contenu: ' + error.message);
                }
            }

           function powerActionFollowup() {
                const modalBody = document.querySelector('#popupAnswer div.modal-body');
                modalBody.innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';

                $.ajax({
                    type: 'POST',
                    url: " . $ajaxUrl . ",
                    data:{
                        'ticketId' : " . $ticketIdJson . ",
                        'answer' : 'addAnswerFollowup',
                        'close' : 'closeFollowup'
                    },
                    success: function(html){
                        modalBody.innerHTML = html;
                    },
                });
           }"
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
        $this->showAjaxModal();
        $ticketIdJson = json_encode((int)$ticketId);
        $ajaxUrl = json_encode(PLUGIN_WIKITSEMANTICS_WEBDIR . "/ajax/generateanswer.php");

        echo Html::scriptBlock(
            "
            const suggestSolutionTextSolution = " . json_encode(__('Suggest a solution with AI', 'wikitsemantics'), JSON_HEX_APOS | JSON_HEX_QUOT) . ";

            // Use the same structure as Followup for consistency
            const containerSolution = document.querySelector('.itilsolution form[name=asset_form] div.row .order-first .row');
            if (containerSolution) {
                const wrapperSolution = document.createElement('div');
                wrapperSolution.className = 'form-field row col-12 mb-2';
                const labelSolution = document.createElement('label');
                labelSolution.className = 'col-form-label col-2 text-xxl-end';
                labelSolution.textContent = ' ';
                const fieldContainerSolution = document.createElement('div');
                fieldContainerSolution.className = 'col-10 field-container';
                const buttonSolution = document.createElement('a');
                buttonSolution.className = 'btn btn-secondary overflow-hidden text-nowrap';
                buttonSolution.setAttribute('title', suggestSolutionTextSolution);
                buttonSolution.setAttribute('data-bs-toggle', 'tooltip');
                buttonSolution.setAttribute('data-bs-placement', 'top');
                buttonSolution.setAttribute('data-bs-original-title', suggestSolutionTextSolution);
                buttonSolution.style.cursor = 'pointer';
                buttonSolution.onclick = function(e) {
                    e.preventDefault();
                    // Open modal using Bootstrap 5 API
                    const modalElement = document.getElementById('popupAnswer');
                    if (modalElement) {
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();
                    }
                    // Then trigger the power action
                    powerActionSolution();
                };
                buttonSolution.innerHTML = '<i class=\"fas fa-wand-magic-sparkles\"></i>';
                fieldContainerSolution.appendChild(buttonSolution);
                wrapperSolution.appendChild(labelSolution);
                wrapperSolution.appendChild(fieldContainerSolution);
                containerSolution.insertBefore(wrapperSolution, containerSolution.firstChild);
            }

            function closeSolution(){
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
            }

            function addAnswerSolution(result) {
                try {
                    let data_decode = result;

                    // Find TinyMCE iframe
                    const iframe = document.querySelector('.itilsolution form[name=asset_form] div.row div.tox-editor-container iframe');
                    if (!iframe) {
                        alert('Erreur: Impossible de trouver l\\'éditeur de texte. Veuillez réessayer.');
                        return;
                    }

                    // Access iframe body directly
                    const iframeBody = iframe.contentWindow.document.body;
                    if (!iframeBody) {
                        alert('Erreur: Impossible d\\'accéder à l\\'éditeur de texte.');
                        return;
                    }

                    // Insert content directly into the body
                    iframeBody.innerHTML = data_decode.content;
                } catch (error) {
                    console.error('[Wikit Semantics] Error:', error);
                    alert('Erreur lors de l\\'ajout du contenu: ' + error.message);
                }
            }

           function powerActionSolution() {
                const modalBody = document.querySelector('#popupAnswer div.modal-body');
                modalBody.innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';

                $.ajax({
                    type: 'POST',
                    url: " . $ajaxUrl . ",
                    data:{
                        'ticketId' : " . $ticketIdJson . ",
                        'answer' : 'addAnswerSolution',
                        'close' : 'closeSolution'
                    },
                    success: function(html){
                        modalBody.innerHTML = html;
                    },
                });
           }"
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
        $this->showAjaxModal();
        $ticketIdJson = json_encode((int)$ticketId);
        $ajaxUrl = json_encode(PLUGIN_WIKITSEMANTICS_WEBDIR . "/ajax/generateanswer.php");

        echo Html::scriptBlock(
            "
            const suggestTaskTextTask = " . json_encode(__('Suggest a solution with AI', 'wikitsemantics'), JSON_HEX_APOS | JSON_HEX_QUOT) . ";

            const containerTask = document.querySelector('.itiltask form[name=asset_form] div.row .order-first .row');
            if (containerTask) {
                const wrapperTask = document.createElement('div');
                wrapperTask.className = 'form-field row col-12 mb-2';
                const labelTask = document.createElement('label');
                labelTask.className = 'col-form-label col-2 text-xxl-end';
                labelTask.textContent = ' ';
                const fieldContainerTask = document.createElement('div');
                fieldContainerTask.className = 'col-10 field-container';
                const buttonTask = document.createElement('a');
                buttonTask.className = 'btn btn-secondary overflow-hidden text-nowrap';
                buttonTask.setAttribute('title', suggestTaskTextTask);
                buttonTask.setAttribute('data-bs-toggle', 'tooltip');
                buttonTask.setAttribute('data-bs-placement', 'top');
                buttonTask.setAttribute('data-bs-original-title', suggestTaskTextTask);
                buttonTask.style.cursor = 'pointer';
                buttonTask.onclick = function(e) {
                    e.preventDefault();
                    // Open modal using Bootstrap 5 API
                    const modalElement = document.getElementById('popupAnswer');
                    if (modalElement) {
                        const modal = new bootstrap.Modal(modalElement);
                        modal.show();
                    }
                    // Then trigger the power action
                    powerActionTask();
                };
                buttonTask.innerHTML = '<i class=\"fas fa-wand-magic-sparkles\"></i>';
                fieldContainerTask.appendChild(buttonTask);
                wrapperTask.appendChild(labelTask);
                wrapperTask.appendChild(fieldContainerTask);
                containerTask.insertBefore(wrapperTask, containerTask.firstChild);
            }

            function closeTask(){
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
            }

            function addAnswerTask(result) {
                try {
                    let data_decode = result;

                    // Find TinyMCE iframe
                    const iframe = document.querySelector('.itiltask form[name=asset_form] div.row div.tox-editor-container iframe');
                    if (!iframe) {
                        alert('Erreur: Impossible de trouver l\\'éditeur de texte. Veuillez réessayer.');
                        return;
                    }

                    // Access iframe body directly
                    const iframeBody = iframe.contentWindow.document.body;
                    if (!iframeBody) {
                        alert('Erreur: Impossible d\\'accéder à l\\'éditeur de texte.');
                        return;
                    }

                    // Insert content directly into the body
                    iframeBody.innerHTML = data_decode.content;
                } catch (error) {
                    console.error('[Wikit Semantics] Error:', error);
                    alert('Erreur lors de l\\'ajout du contenu: ' + error.message);
                }
            }

           function powerActionTask() {
                const modalBody = document.querySelector('#popupAnswer div.modal-body');
                modalBody.innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';

                $.ajax({
                    type: 'POST',
                    url: " . $ajaxUrl . ",
                    data:{
                        'ticketId' : " . $ticketIdJson . ",
                        'answer' : 'addAnswerTask',
                        'close' : 'closeTask'
                    },
                    success: function(html){
                        modalBody.innerHTML = html;
                    },
                });
           }"
        );
    }
}
