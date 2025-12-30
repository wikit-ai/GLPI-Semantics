/**
 * Wikit Semantics - AI Answer Generation
 * Copyright (C) 2026 by the Wikit Development Team.
 */

class WikitSemanticsAnswerGenerator {
   constructor(config) {
       this.config = config;
       this.eventSource = null;
       this.initModal();
   }

    /**
     * Initialize the modal if not already created
     */
   initModal() {
      if (!document.getElementById('popupAnswer')) {
          // Modal will be injected by Twig template
          return;
      }
   }

    /**
     * Convert markdown-like text to HTML
     * @param {string} text - Text to convert
     * @returns {string} HTML formatted text
     */
   textToHtml(text) {
       let content = text.replace(/\\n/g, '\n');
       content = content.replace(/\n/g, '<br>');
       content = content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
       content = content.replace(/__(.+?)__/g, '<strong>$1</strong>');
       content = content.replace(/\*(.+?)\*/g, '<em>$1</em>');
       content = content.replace(/_(.+?)_/g, '<em>$1</em>');
       content = content.replace(/`(.+?)`/g, '<code>$1</code>');
       return content;
   }

    /**
     * Show the modal - injects it lazily if needed
     */
   showModal() {
       console.log('[WikitSemantics] showModal() called');
       let modalElement = document.getElementById('popupAnswer');

       // If modal doesn't exist, inject it now from global variable
      if (!modalElement && window.wikitSemanticsModalHTML) {
          console.log('[WikitSemantics] Injecting modal into DOM now');
          document.body.insertAdjacentHTML('beforeend', window.wikitSemanticsModalHTML);
          modalElement = document.getElementById('popupAnswer');
      }

       console.log('[WikitSemantics] Modal element:', modalElement);
      if (modalElement) {
          const modal = new bootstrap.Modal(modalElement);
          modal.show();
          console.log('[WikitSemantics] Modal shown');
      } else {
          console.error('[WikitSemantics] Modal element #popupAnswer not found in DOM and no global HTML available');
      }
   }

    /**
     * Close the modal and clean up
     */
   close() {
      if (this.eventSource) {
          this.eventSource.close();
          this.eventSource = null;
      }
       const modalBody = document.querySelector('#popupAnswer div.modal-body');
      if (modalBody) {
          modalBody.innerHTML = '<div style="display: block; height: 200px;padding: 20px"><i class="fas fa-4x fa-spinner fa-pulse m-5 start-50" style="position: relative;margin: auto !important;"></i></div>';
      }
   }

    /**
     * Add generated answer to ticket form
     * @param {Object} result - Result object with content
     * @param {string} itemType - Type of item (followup, solution, task)
     */
   addAnswerToTicket(result, itemType) {
       const modalBody = document.querySelector('#popupAnswer div.modal-body');
      if (modalBody) {
          modalBody.innerHTML = '<div style="display: block; height: 200px;padding: 20px"><i class="fas fa-4x fa-spinner fa-pulse m-5 start-50" style="position: relative;margin: auto !important;"></i></div>';
      }

       const classMap = {
            'followup': '.itilfollowup',
            'solution': '.itilsolution',
            'task': '.itiltask'
      };

       const selector = `${classMap[itemType]} form[name=asset_form] div.row div.tox-editor-container iframe`;
       const iframe = document.querySelector(selector);

      if (iframe && iframe.contentWindow && iframe.contentWindow.document.body) {
          const tinymce = iframe.contentWindow.document.body.querySelector('#tinymce p');
         if (tinymce) {
            tinymce.innerHTML = result.content;
         }
      }
   }

    /**
     * Generate answer using streaming mode
     * @param {number} ticketId - Ticket ID
     * @param {string} itemType - Type of item (followup, solution, task)
     */
   generateAnswerStreaming(ticketId, itemType) {
       const modalBody = document.querySelector('#popupAnswer div.modal-body');
      if (!modalBody) {
          console.error('[WikitSemantics] Modal body not found in DOM');
          return;
      }
       modalBody.innerHTML = '<div style="display: block; height: 200px;padding: 20px"><i class="fas fa-4x fa-spinner fa-pulse m-5 start-50" style="position: relative;margin: auto !important;"></i></div>';

       let accumulatedText = '';
       let spinnerTimeout = null;
       let spinnerVisible = true;

       spinnerTimeout = setTimeout(() => {
            spinnerVisible = false;
            modalBody.innerHTML = '<div id="divanswer" style="padding: 20px;">' + this.textToHtml(accumulatedText) + '</div>';
         }, 1000);

       const csrfToken = document.querySelector('meta[property="glpi:csrf_token"]')?.getAttribute('content');
       const formData = new FormData();
       formData.append('ticketId', ticketId);
      if (csrfToken) {
         formData.append('_glpi_csrf_token', csrfToken);
      }

       fetch(this.config.ajaxStreamUrl, {
            method: 'POST',
            body: formData
         }).then(response => {
            if (response.status !== 200) {
                console.error('[Streaming] Non-200 response:', response.status);
                return response.text().then(errorText => {
                     console.error('[Streaming] Error response body:', errorText.substring(0, 500));
                     throw new Error('HTTP ' + response.status);
                  });
            }

            const reader = response.body.getReader();
            const decoder = new TextDecoder();

            const read = () => {
                reader.read().then(({done, value}) => {
                     if (done) {
                        clearTimeout(spinnerTimeout);
                        const htmlContent = this.textToHtml(accumulatedText);
                        modalBody.innerHTML = '<div id="divanswer" style="padding: 20px;">' + htmlContent + '</div>';

                        const btnAdd = document.createElement('button');
                        btnAdd.id = 'btnAddAnswer';
                        btnAdd.className = 'btn btn-primary';
                        btnAdd.textContent = this.config.labels.addToTicket;
                        btnAdd.setAttribute('data-bs-dismiss', 'modal');
                        btnAdd.onclick = () => {
                            this.addAnswerToTicket({content: htmlContent}, itemType);
                        };

                        const btnClose = document.createElement('button');
                        btnClose.id = 'btnClose';
                        btnClose.className = 'btn btn-secondary';
                        btnClose.textContent = this.config.labels.close;
                        btnClose.setAttribute('data-bs-dismiss', 'modal');
                        btnClose.onclick = () => this.close();

                        modalBody.appendChild(btnAdd);
                        modalBody.appendChild(btnClose);
                        return;
                     }

                     const chunk = decoder.decode(value, {stream: true});
                     const lines = chunk.split('\n');

                     for (let line of lines) {
                        line = line.trim();
                        if (!line) {
                           continue;
                        }

                        if (line.startsWith('event: connected')) {
                           continue;
                        }
                        if (line.startsWith('event: chunk')) {
                           continue;
                        }
                        if (line.startsWith('event: csrf_token')) {
                           continue;
                        }
                        if (line.startsWith('event: done')) {
                           continue;
                        }

                        if (line.startsWith('data: ')) {
                           try {
                               const data = JSON.parse(line.substring(6));
                              if (data.chunk) {
                                  accumulatedText += data.chunk;

                                 if (!spinnerVisible) {
                                    const answerDiv = document.querySelector('#divanswer');
                                    if (answerDiv) {
                                        answerDiv.innerHTML = this.textToHtml(accumulatedText);
                                    }
                                 } else {
                                     clearTimeout(spinnerTimeout);
                                     spinnerVisible = false;
                                     modalBody.innerHTML = '<div id="divanswer" style="padding: 20px;">' + this.textToHtml(accumulatedText) + '</div>';
                                 }
                              } else if (data.token) {
                                  const metaTag = document.querySelector('meta[property="glpi:csrf_token"]');
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
                           modalBody.innerHTML = '<div style="padding: 20px; color: red;">' + this.config.labels.error + '</div>';
                           modalBody.innerHTML += '<button class="btn btn-secondary" data-bs-dismiss="modal">' + this.config.labels.close + '</button>';
                           return;
                        }
                     }

                     read();
                  }).catch(error => {
                      console.error('[Streaming] Read error:', error);
                      clearTimeout(spinnerTimeout);
                      modalBody.innerHTML = '<div style="padding: 20px; color: red;">' + this.config.labels.error + '</div>';
                      modalBody.innerHTML += '<button class="btn btn-secondary" data-bs-dismiss="modal">' + this.config.labels.close + '</button>';
                  });
            };

            read();
         }).catch(error => {
            console.error('[Streaming] Fetch error:', error);
            clearTimeout(spinnerTimeout);
            modalBody.innerHTML = '<div style="padding: 20px; color: red;">' + this.config.labels.error + '</div>';
            modalBody.innerHTML += '<button class="btn btn-secondary" data-bs-dismiss="modal">' + this.config.labels.close + '</button>';
         });
   }

    /**
     * Generate answer using AJAX mode (fallback)
     * @param {number} ticketId - Ticket ID
     * @param {string} itemType - Type of item (followup, solution, task)
     * @param {string} answerFunction - Answer function name
     * @param {string} closeFunction - Close function name
     */
   generateAnswerAjax(ticketId, itemType, answerFunction, closeFunction) {
       const modalBody = document.querySelector('#popupAnswer div.modal-body');
      if (!modalBody) {
          console.error('[WikitSemantics] Modal body not found in DOM');
          return;
      }
       $.ajax({
            type: 'POST',
            url: this.config.ajaxUrl,
            data: {
               'ticketId': ticketId,
               'answer': answerFunction,
               'close': closeFunction
            },
            success: (html) => {
               if (modalBody) {
                   // Server already returns buttons, just insert the HTML
                   modalBody.innerHTML = html;

                   // Attach click handler to the "Add to ticket" button returned by server
                   const btnAdd = document.getElementById('btnAddAnswer');
                  if (btnAdd) {
                      btnAdd.onclick = () => {
                           // Get content from data attribute or extract from div
                           const encodedData = btnAdd.getAttribute('data-answer-content');
                           if (encodedData) {
                              try {
                                  const resultData = JSON.parse(atob(encodedData));
                                  this.addAnswerToTicket(resultData, itemType);
                              } catch (e) {
                                  console.error('[AJAX] Failed to decode answer content:', e);
                              }
                           }
                     };
                  }
               }
            },
         });
   }

    /**
     * Main generation method
     * @param {number} ticketId - Ticket ID
     * @param {string} itemType - Type of item (followup, solution, task)
     * @param {string} answerFunction - Answer function name
     * @param {string} closeFunction - Close function name
     */
   generate(ticketId, itemType, answerFunction, closeFunction) {
      if (this.config.isStreamingEnabled) {
          this.generateAnswerStreaming(ticketId, itemType);
      } else {
          this.generateAnswerAjax(ticketId, itemType, answerFunction, closeFunction);
      }
   }
}

// Export for use in other scripts
window.WikitSemanticsAnswerGenerator = WikitSemanticsAnswerGenerator;

/**
 * Auto-initialize all Wikit Semantics button containers
 * This function scans for .wikitsemantics-button-container elements
 * and creates AI suggestion buttons with proper event handlers
 */
function initializeWikitSemanticsButtons() {
    document.querySelectorAll('.wikitsemantics-button-container').forEach(container => {
         // Skip if already initialized
         if (container.dataset.initialized === 'true') {
            return;
         }
         container.dataset.initialized = 'true';

         // Extract configuration from data attributes
         const config = {
            ticketId: parseInt(container.dataset.ticketId, 10),
            itemType: container.dataset.itemType,
            containerSelector: container.dataset.containerSelector,
            buttonLabel: container.dataset.buttonLabel,
            isStreamingEnabled: parseInt(container.dataset.streamingEnabled, 10),
            ajaxUrl: container.dataset.ajaxUrl,
            ajaxStreamUrl: container.dataset.ajaxStreamUrl,
            labels: {
               addToTicket: container.dataset.labelAdd,
               close: container.dataset.labelClose,
               error: container.dataset.labelError
            }
         };

         // Create the generator instance
         const generator = new WikitSemanticsAnswerGenerator(config);

         // Find the target container in the DOM
         const targetContainer = document.querySelector(config.containerSelector);
         if (!targetContainer) {
            console.warn('[WikitSemantics] Target container not found:', config.containerSelector);
            return;
         }

         // Create the button wrapper structure
         const wrapper = document.createElement('div');
         wrapper.className = 'form-field row col-12 mb-2';

         const label = document.createElement('label');
         label.className = 'col-form-label col-2 text-xxl-end';
         label.textContent = ' ';

         const fieldContainer = document.createElement('div');
         fieldContainer.className = 'col-10 field-container';

         const button = document.createElement('a');
         button.className = 'btn btn-secondary overflow-hidden text-nowrap';
         button.setAttribute('title', config.buttonLabel);
         button.setAttribute('data-bs-toggle', 'tooltip');
         button.setAttribute('data-bs-placement', 'top');
         button.style.cursor = 'pointer';
         button.innerHTML = '<i class="fas fa-wand-magic-sparkles"></i>';

         // Attach click event handler
         button.onclick = function(e) {
            e.preventDefault();
            generator.showModal();
            // Generate function names based on item type for backward compatibility
            const answerFunction = 'addAnswer' + config.itemType.charAt(0).toUpperCase() + config.itemType.slice(1);
            const closeFunction = 'close' + config.itemType.charAt(0).toUpperCase() + config.itemType.slice(1);
            generator.generate(config.ticketId, config.itemType, answerFunction, closeFunction);
         };

         // Assemble the DOM structure
         fieldContainer.appendChild(button);
         wrapper.appendChild(label);
         wrapper.appendChild(fieldContainer);
         targetContainer.insertBefore(wrapper, targetContainer.firstChild);

         console.log('[WikitSemantics] Button initialized for', config.itemType, 'ticket', config.ticketId);
      });
}

// Auto-initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeWikitSemanticsButtons);
} else {
    // DOM already loaded, initialize immediately
    initializeWikitSemanticsButtons();
}

// Re-initialize when GLPI dynamically loads content (AJAX forms)
// Use MutationObserver to detect new button containers added to the DOM
const observer = new MutationObserver(function(mutations) {
    let shouldReinitialize = false;
    mutations.forEach(function(mutation) {
        mutation.addedNodes.forEach(function(node) {
         if (node.nodeType === 1) { // Element node
            if (node.classList && node.classList.contains('wikitsemantics-button-container')) {
                  shouldReinitialize = true;
            } else if (node.querySelector && node.querySelector('.wikitsemantics-button-container')) {
                   shouldReinitialize = true;
            }
         }
        });
    });

   if (shouldReinitialize) {
       console.log('[WikitSemantics] New button containers detected, reinitializing...');
       initializeWikitSemanticsButtons();
   }
});

// Start observing the document for DOM changes
observer.observe(document.body, {
   childList: true,
   subtree: true
});
