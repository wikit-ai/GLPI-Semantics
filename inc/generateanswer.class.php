<?php
/**
 * -------------------------------------------------------------------------
 * Wikit Semantics plugin for GLPI
 * Copyright (C) 2025 by the Wikit Development Team.
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
        $ajaxStreamUrl = json_encode(PLUGIN_WIKITSEMANTICS_WEBDIR . "/ajax/generateanswer_stream.php");

        // Get config to check if streaming is enabled
        $config = PluginWikitsemanticsConfig::getConfig();
        $isStreamingEnabled = isset($config->fields['is_streaming_enabled']) ? (int)$config->fields['is_streaming_enabled'] : 0;

        echo Html::scriptBlock(
            "
            const isStreamingEnabledFollowup = " . $isStreamingEnabled . ";
            let eventSourceFollowup = null;
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
                if (eventSourceFollowup) {
                    eventSourceFollowup.close();
                    eventSourceFollowup = null;
                }
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
            }

            function addAnswerFollowup(result) {
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
                let data_decode = result
                document.querySelector('.itilfollowup form[name=asset_form] div.row div.tox-editor-container iframe').contentWindow.document.body.querySelector('#tinymce p').innerHTML = data_decode.content;
            }

           function powerActionFollowup() {
                const modalBody = document.querySelector('#popupAnswer div.modal-body');
                modalBody.innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';

                if (isStreamingEnabledFollowup) {
                    let accumulatedText = '';
                    let spinnerTimeout = null;
                    let hasReceivedData = false;
                    let spinnerVisible = true;

                    function textToHtml(text) {
                        let content = text.replace(/\\\\n/g, '\\n');
                        content = content.replace(/\\n/g, '<br>');
                        content = content.replace(/\\*\\*(.+?)\\*\\*/g, '<strong>$1</strong>');
                        content = content.replace(/__(.+?)__/g, '<strong>$1</strong>');
                        content = content.replace(/\\*(.+?)\\*/g, '<em>$1</em>');
                        content = content.replace(/_(.+?)_/g, '<em>$1</em>');
                        content = content.replace(/`(.+?)`/g, '<code>$1</code>');
                        return content;
                    }

                    spinnerTimeout = setTimeout(() => {
                        spinnerVisible = false;
                        modalBody.innerHTML = '<div id=\"divanswer\" style=\"padding: 20px;\">' + textToHtml(accumulatedText) + '</div>';
                    }, 1000);

                    const csrfToken = document.querySelector('meta[property=\"glpi:csrf_token\"]')?.getAttribute('content');
                    const formData = new FormData();
                    formData.append('ticketId', " . $ticketIdJson . ");
                    if (csrfToken) {
                        formData.append('_glpi_csrf_token', csrfToken);
                    }

                    fetch(" . $ajaxStreamUrl . ", {
                        method: 'POST',
                        body: formData
                    }).then(response => {

                        if (response.status !== 200) {
                            console.error('[Streaming] Non-200 response:', response.status);
                            // Try to read the response body to see what error message we got
                            return response.text().then(errorText => {
                                console.error('[Streaming] Error response body:', errorText.substring(0, 500));
                                throw new Error('HTTP ' + response.status);
                            });
                        }

                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();

                        function read() {
                            reader.read().then(({done, value}) => {
                                if (done) {
                                    clearTimeout(spinnerTimeout);
                                    const htmlContent = textToHtml(accumulatedText);
                                    modalBody.innerHTML = '<div id=\"divanswer\" style=\"padding: 20px;\">' + htmlContent + '</div>';

                                    const btnAdd = document.createElement('button');
                                    btnAdd.id = 'btnAddAnswer';
                                    btnAdd.className = 'btn btn-primary';
                                    btnAdd.textContent = " . json_encode(__('Add to ticket', 'wikitsemantics'), JSON_HEX_APOS | JSON_HEX_QUOT) . ";
                                    btnAdd.setAttribute('data-bs-dismiss', 'modal');
                                    btnAdd.onclick = function() {
                                        addAnswerFollowup({content: htmlContent});
                                    };

                                    const btnClose = document.createElement('button');
                                    btnClose.id = 'btnClose';
                                    btnClose.className = 'btn btn-secondary';
                                    btnClose.textContent = " . json_encode(__('Close', 'wikitsemantics'), JSON_HEX_APOS | JSON_HEX_QUOT) . ";
                                    btnClose.setAttribute('data-bs-dismiss', 'modal');
                                    btnClose.onclick = closeFollowup;

                                    modalBody.appendChild(btnAdd);
                                    modalBody.appendChild(btnClose);
                                    return;
                                }

                                const chunk = decoder.decode(value, {stream: true});
                                const lines = chunk.split('\\n');

                                for (let line of lines) {
                                    line = line.trim();
                                    if (!line) continue;

                                    if (line.startsWith('event: connected')) continue;
                                    if (line.startsWith('event: chunk')) continue;
                                    if (line.startsWith('event: csrf_token')) continue;
                                    if (line.startsWith('event: done')) continue;

                                    if (line.startsWith('data: ')) {
                                        try {
                                            const data = JSON.parse(line.substring(6));
                                            if (data.chunk) {
                                                hasReceivedData = true;
                                                accumulatedText += data.chunk;

                                                if (!spinnerVisible) {
                                                    const answerDiv = document.querySelector('#divanswer');
                                                    if (answerDiv) {
                                                        answerDiv.innerHTML = textToHtml(accumulatedText);
                                                    }
                                                } else {
                                                    clearTimeout(spinnerTimeout);
                                                    spinnerVisible = false;
                                                    modalBody.innerHTML = '<div id=\"divanswer\" style=\"padding: 20px;\">' + textToHtml(accumulatedText) + '</div>';
                                                }
                                            } else if (data.token) {
                                                const metaTag = document.querySelector('meta[property=\"glpi:csrf_token\"]');
                                                if (metaTag) {
                                                    metaTag.setAttribute('content', data.token);
                                                }
                                            }
                                        } catch (e) {
                                            console.error('[Streaming] Parse error:', e, 'Line:', line);
                                        }
                                    } else if (line.startsWith('event: error')) {
                                        console.error('[Streaming] Error event received');
                                        clearTimeout(spinnerTimeout);
                                        modalBody.innerHTML = '<div style=\"padding: 20px; color: red;\">" . addslashes(__('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics')) . "</div>';
                                        modalBody.innerHTML += '<button class=\"btn btn-secondary\" onclick=\"closeFollowup()\" data-bs-dismiss=\"modal\">" . addslashes(__('Close', 'wikitsemantics')) . "</button>';
                                        return;
                                    }
                                }

                                read();
                            }).catch(error => {
                                console.error('[Streaming] Read error:', error);
                                clearTimeout(spinnerTimeout);
                                modalBody.innerHTML = '<div style=\"padding: 20px; color: red;\">" . addslashes(__('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics')) . "</div>';
                                modalBody.innerHTML += '<button class=\"btn btn-secondary\" onclick=\"closeFollowup()\" data-bs-dismiss=\"modal\">" . addslashes(__('Close', 'wikitsemantics')) . "</button>';
                            });
                        }

                        read();
                    }).catch(error => {
                        console.error('[Streaming] Fetch error:', error);
                        clearTimeout(spinnerTimeout);
                        modalBody.innerHTML = '<div style=\"padding: 20px; color: red;\">" . addslashes(__('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics')) . "</div>';
                        modalBody.innerHTML += '<button class=\"btn btn-secondary\" onclick=\"closeFollowup()\" data-bs-dismiss=\"modal\">" . addslashes(__('Close', 'wikitsemantics')) . "</button>';
                    });
                } else {
                    // Normal mode with AJAX
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
                }
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
        $ajaxStreamUrl = json_encode(PLUGIN_WIKITSEMANTICS_WEBDIR . "/ajax/generateanswer_stream.php");

        $config = PluginWikitsemanticsConfig::getConfig();
        $isStreamingEnabled = isset($config->fields['is_streaming_enabled']) ? (int)$config->fields['is_streaming_enabled'] : 0;

        echo Html::scriptBlock(
            "
            const isStreamingEnabledSolution = " . $isStreamingEnabled . ";
            let eventSourceSolution = null;
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
                if (eventSourceSolution) {
                    eventSourceSolution.close();
                    eventSourceSolution = null;
                }
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
            }

            function addAnswerSolution(result) {
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
           		let data_decode = result
                document.querySelector('.itilsolution form[name=asset_form] div.row div.tox-editor-container iframe').contentWindow.document.body.querySelector('#tinymce p').innerHTML = data_decode.content;
            }

           function powerActionSolution() {
                const modalBody = document.querySelector('#popupAnswer div.modal-body');
                modalBody.innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';

                if (isStreamingEnabledSolution) {
                    let accumulatedText = '';
                    let spinnerTimeout = null;
                    let spinnerVisible = true;

                    function textToHtml(text) {
                        let content = text.replace(/\\\\n/g, '\\n');
                        content = content.replace(/\\n/g, '<br>');
                        content = content.replace(/\\*\\*(.+?)\\*\\*/g, '<strong>$1</strong>');
                        content = content.replace(/__(.+?)__/g, '<strong>$1</strong>');
                        content = content.replace(/\\*(.+?)\\*/g, '<em>$1</em>');
                        content = content.replace(/_(.+?)_/g, '<em>$1</em>');
                        content = content.replace(/`(.+?)`/g, '<code>$1</code>');
                        return content;
                    }

                    spinnerTimeout = setTimeout(() => {
                        spinnerVisible = false;
                        modalBody.innerHTML = '<div id=\"divanswer\" style=\"padding: 20px;\">' + textToHtml(accumulatedText) + '</div>';
                    }, 1000);

                    const csrfToken = document.querySelector('meta[property=\"glpi:csrf_token\"]')?.getAttribute('content');
                    const formData = new FormData();
                    formData.append('ticketId', " . $ticketIdJson . ");
                    if (csrfToken) {
                        formData.append('_glpi_csrf_token', csrfToken);
                    }

                    fetch(" . $ajaxStreamUrl . ", {
                        method: 'POST',
                        body: formData
                    }).then(response => {
                        if (response.status !== 200) {
                            console.error('[Streaming Solution] Non-200 response:', response.status);
                            return response.text().then(errorText => {
                                console.error('[Streaming Solution] Error response body:', errorText.substring(0, 500));
                                throw new Error('HTTP ' + response.status);
                            });
                        }

                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();

                        function read() {
                            reader.read().then(({done, value}) => {
                                if (done) {
                                    clearTimeout(spinnerTimeout);
                                    const htmlContent = textToHtml(accumulatedText);
                                    modalBody.innerHTML = '<div id=\"divanswer\" style=\"padding: 20px;\">' + htmlContent + '</div>';

                                    // Create buttons
                                    const btnAdd = document.createElement('button');
                                    btnAdd.id = 'btnAddAnswer';
                                    btnAdd.className = 'btn btn-primary';
                                    btnAdd.textContent = " . json_encode(__('Add to ticket', 'wikitsemantics'), JSON_HEX_APOS | JSON_HEX_QUOT) . ";
                                    btnAdd.setAttribute('data-bs-dismiss', 'modal');
                                    btnAdd.onclick = function() {
                                        addAnswerSolution({content: htmlContent});
                                    };

                                    const btnClose = document.createElement('button');
                                    btnClose.id = 'btnClose';
                                    btnClose.className = 'btn btn-secondary';
                                    btnClose.textContent = " . json_encode(__('Close', 'wikitsemantics'), JSON_HEX_APOS | JSON_HEX_QUOT) . ";
                                    btnClose.setAttribute('data-bs-dismiss', 'modal');
                                    btnClose.onclick = closeSolution;

                                    modalBody.appendChild(btnAdd);
                                    modalBody.appendChild(btnClose);
                                    return;
                                }

                                const chunk = decoder.decode(value, {stream: true});
                                const lines = chunk.split('\\n');

                                for (let line of lines) {
                                    line = line.trim();
                                    if (!line) continue;

                                    if (line.startsWith('event: connected')) continue;
                                    if (line.startsWith('event: chunk')) continue;
                                    if (line.startsWith('event: csrf_token')) continue;
                                    if (line.startsWith('event: done')) continue;

                                    if (line.startsWith('data: ')) {
                                        try {
                                            const data = JSON.parse(line.substring(6));
                                            if (data.chunk) {
                                                accumulatedText += data.chunk;

                                                if (!spinnerVisible) {
                                                    const answerDiv = document.querySelector('#divanswer');
                                                    if (answerDiv) {
                                                        answerDiv.innerHTML = textToHtml(accumulatedText);
                                                    }
                                                } else {
                                                    clearTimeout(spinnerTimeout);
                                                    spinnerVisible = false;
                                                    modalBody.innerHTML = '<div id=\"divanswer\" style=\"padding: 20px;\">' + textToHtml(accumulatedText) + '</div>';
                                                }
                                            } else if (data.token) {
                                                const metaTag = document.querySelector('meta[property=\"glpi:csrf_token\"]');
                                                if (metaTag) {
                                                    metaTag.setAttribute('content', data.token);
                                                }
                                            }
                                        } catch (e) {
                                            console.error('[Streaming Solution] Parse error:', e);
                                        }
                                    } else if (line.startsWith('event: error')) {
                                        console.error('[Streaming Solution] Error event received');
                                        clearTimeout(spinnerTimeout);
                                        modalBody.innerHTML = '<div style=\"padding: 20px; color: red;\">" . addslashes(__('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics')) . "</div>';
                                        modalBody.innerHTML += '<button class=\"btn btn-secondary\" onclick=\"closeSolution()\" data-bs-dismiss=\"modal\">" . addslashes(__('Close', 'wikitsemantics')) . "</button>';
                                        return;
                                    }
                                }

                                read();
                            }).catch(error => {
                                clearTimeout(spinnerTimeout);
                                modalBody.innerHTML = '<div style=\"padding: 20px; color: red;\">" . addslashes(__('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics')) . "</div>';
                                modalBody.innerHTML += '<button class=\"btn btn-secondary\" onclick=\"closeSolution()\" data-bs-dismiss=\"modal\">" . addslashes(__('Close', 'wikitsemantics')) . "</button>';
                            });
                        }

                        read();
                    }).catch(error => {
                        clearTimeout(spinnerTimeout);
                        modalBody.innerHTML = '<div style=\"padding: 20px; color: red;\">" . addslashes(__('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics')) . "</div>';
                        modalBody.innerHTML += '<button class=\"btn btn-secondary\" onclick=\"closeSolution()\" data-bs-dismiss=\"modal\">" . addslashes(__('Close', 'wikitsemantics')) . "</button>';
                    });
                } else {
                    // Normal mode with AJAX
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
                }
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
        $ajaxStreamUrl = json_encode(PLUGIN_WIKITSEMANTICS_WEBDIR . "/ajax/generateanswer_stream.php");

        // Get config to check if streaming is enabled
        $config = PluginWikitsemanticsConfig::getConfig();
        $isStreamingEnabled = isset($config->fields['is_streaming_enabled']) ? (int)$config->fields['is_streaming_enabled'] : 0;

        echo Html::scriptBlock(
            "
            const isStreamingEnabledTask = " . $isStreamingEnabled . ";
            let eventSourceTask = null;
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
                if (eventSourceTask) {
                    eventSourceTask.close();
                    eventSourceTask = null;
                }
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
            }

            function addAnswerTask(result) {
                document.querySelector('#popupAnswer div.modal-body').innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';
                let data_decode = result
                document.querySelector('.itiltask form[name=asset_form] div.row div.tox-editor-container iframe').contentWindow.document.body.querySelector('#tinymce p').innerHTML = data_decode.content;
            }

           function powerActionTask() {
                const modalBody = document.querySelector('#popupAnswer div.modal-body');
                modalBody.innerHTML = '<div style=\"display: block; height: 200px;padding: 20px\"><i class=\"fas fa-4x fa-spinner fa-pulse m-5 start-50\" style=\"position: relative;margin: auto !important;\"></i></div>';

                if (isStreamingEnabledTask) {
                    let accumulatedText = '';
                    let spinnerTimeout = null;
                    let spinnerVisible = true;

                    function textToHtml(text) {
                        let content = text.replace(/\\\\n/g, '\\n');
                        content = content.replace(/\\n/g, '<br>');
                        content = content.replace(/\\*\\*(.+?)\\*\\*/g, '<strong>$1</strong>');
                        content = content.replace(/__(.+?)__/g, '<strong>$1</strong>');
                        content = content.replace(/\\*(.+?)\\*/g, '<em>$1</em>');
                        content = content.replace(/_(.+?)_/g, '<em>$1</em>');
                        content = content.replace(/`(.+?)`/g, '<code>$1</code>');
                        return content;
                    }

                    spinnerTimeout = setTimeout(() => {
                        spinnerVisible = false;
                        modalBody.innerHTML = '<div id=\"divanswer\" style=\"padding: 20px;\">' + textToHtml(accumulatedText) + '</div>';
                    }, 1000);

                    const csrfToken = document.querySelector('meta[property=\"glpi:csrf_token\"]')?.getAttribute('content');
                    const formData = new FormData();
                    formData.append('ticketId', " . $ticketIdJson . ");
                    if (csrfToken) {
                        formData.append('_glpi_csrf_token', csrfToken);
                    }

                    fetch(" . $ajaxStreamUrl . ", {
                        method: 'POST',
                        body: formData
                    }).then(response => {
                        if (response.status !== 200) {
                            console.error('[Streaming Task] Non-200 response:', response.status);
                            return response.text().then(errorText => {
                                console.error('[Streaming Task] Error response body:', errorText.substring(0, 500));
                                throw new Error('HTTP ' + response.status);
                            });
                        }

                        const reader = response.body.getReader();
                        const decoder = new TextDecoder();

                        function read() {
                            reader.read().then(({done, value}) => {
                                if (done) {
                                    clearTimeout(spinnerTimeout);
                                    const htmlContent = textToHtml(accumulatedText);
                                    modalBody.innerHTML = '<div id=\"divanswer\" style=\"padding: 20px;\">' + htmlContent + '</div>';

                                    // Create buttons
                                    const btnAdd = document.createElement('button');
                                    btnAdd.id = 'btnAddAnswer';
                                    btnAdd.className = 'btn btn-primary';
                                    btnAdd.textContent = " . json_encode(__('Add to ticket', 'wikitsemantics'), JSON_HEX_APOS | JSON_HEX_QUOT) . ";
                                    btnAdd.setAttribute('data-bs-dismiss', 'modal');
                                    btnAdd.onclick = function() {
                                        addAnswerTask({content: htmlContent});
                                    };

                                    const btnClose = document.createElement('button');
                                    btnClose.id = 'btnClose';
                                    btnClose.className = 'btn btn-secondary';
                                    btnClose.textContent = " . json_encode(__('Close', 'wikitsemantics'), JSON_HEX_APOS | JSON_HEX_QUOT) . ";
                                    btnClose.setAttribute('data-bs-dismiss', 'modal');
                                    btnClose.onclick = closeTask;

                                    modalBody.appendChild(btnAdd);
                                    modalBody.appendChild(btnClose);
                                    return;
                                }

                                const chunk = decoder.decode(value, {stream: true});
                                const lines = chunk.split('\\n');

                                for (let line of lines) {
                                    line = line.trim();
                                    if (!line) continue;

                                    if (line.startsWith('event: connected')) continue;
                                    if (line.startsWith('event: chunk')) continue;
                                    if (line.startsWith('event: csrf_token')) continue;
                                    if (line.startsWith('event: done')) continue;

                                    if (line.startsWith('data: ')) {
                                        try {
                                            const data = JSON.parse(line.substring(6));
                                            if (data.chunk) {
                                                accumulatedText += data.chunk;

                                                if (!spinnerVisible) {
                                                    const answerDiv = document.querySelector('#divanswer');
                                                    if (answerDiv) {
                                                        answerDiv.innerHTML = textToHtml(accumulatedText);
                                                    }
                                                } else {
                                                    clearTimeout(spinnerTimeout);
                                                    spinnerVisible = false;
                                                    modalBody.innerHTML = '<div id=\"divanswer\" style=\"padding: 20px;\">' + textToHtml(accumulatedText) + '</div>';
                                                }
                                            } else if (data.token) {
                                                const metaTag = document.querySelector('meta[property=\"glpi:csrf_token\"]');
                                                if (metaTag) {
                                                    metaTag.setAttribute('content', data.token);
                                                }
                                            }
                                        } catch (e) {
                                            console.error('[Streaming Task] Parse error:', e);
                                        }
                                    } else if (line.startsWith('event: error')) {
                                        console.error('[Streaming Task] Error event received');
                                        clearTimeout(spinnerTimeout);
                                        modalBody.innerHTML = '<div style=\"padding: 20px; color: red;\">" . addslashes(__('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics')) . "</div>';
                                        modalBody.innerHTML += '<button class=\"btn btn-secondary\" onclick=\"closeTask()\" data-bs-dismiss=\"modal\">" . addslashes(__('Close', 'wikitsemantics')) . "</button>';
                                        return;
                                    }
                                }

                                read();
                            }).catch(error => {
                                clearTimeout(spinnerTimeout);
                                modalBody.innerHTML = '<div style=\"padding: 20px; color: red;\">" . addslashes(__('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics')) . "</div>';
                                modalBody.innerHTML += '<button class=\"btn btn-secondary\" onclick=\"closeTask()\" data-bs-dismiss=\"modal\">" . addslashes(__('Close', 'wikitsemantics')) . "</button>';
                            });
                        }

                        read();
                    }).catch(error => {
                        clearTimeout(spinnerTimeout);
                        modalBody.innerHTML = '<div style=\"padding: 20px; color: red;\">" . addslashes(__('GLPI encountered a problem connecting to the Wikit Semantics application. Please try again later.', 'wikitsemantics')) . "</div>';
                        modalBody.innerHTML += '<button class=\"btn btn-secondary\" onclick=\"closeTask()\" data-bs-dismiss=\"modal\">" . addslashes(__('Close', 'wikitsemantics')) . "</button>';
                    });
                } else {
                    // Normal mode with AJAX
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
                }
           }"
        );
    }
}
