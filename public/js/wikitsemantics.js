/**
 * Wikit Semantics - AI Answer Generation
 * Copyright (C) 2026 by the Wikit Development Team.
 */

/**
 * Convert markdown-like text to HTML
 * @param {string} text - Text to convert
 * @returns {string} HTML formatted text
 */
/**
 * Remove citation references like [1], [2], [3] from text
 * @param {string} text - Text to clean
 * @returns {string} Text without citation references
 */
function wikitSemanticsStripCitations(text) {
    return text.replace(/\s*\[\d+\]/g, '');
}

function wikitSemanticsTextToHtml(text) {
    let content = text.replace(/\\n/g, '\n');
    content = wikitSemanticsStripCitations(content);
    content = content.replace(/\n/g, '<br>');
    content = content.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    content = content.replace(/__(.+?)__/g, '<strong>$1</strong>');
    content = content.replace(/\*(.+?)\*/g, '<em>$1</em>');
    content = content.replace(/_(.+?)_/g, '<em>$1</em>');
    content = content.replace(/`(.+?)`/g, '<code>$1</code>');
    return content;
}

/**
 * Remove citation references from HTML content
 * @param {string} html - HTML string to clean
 * @returns {string} HTML without citation references
 */
function wikitSemanticsStripCitationsFromHtml(html) {
    return html.replace(/\s*\[\d+\]/g, '');
}

/**
 * Fetch and display source cards from Wikit Semantics API
 * @param {string} queryId - The query execution ID
 * @param {string} appIdField - 'app_id_answer' or 'app_id_kb'
 * @param {HTMLElement} containerElement - Element to insert source cards into
 * @param {string} pluginPath - Plugin web path
 * @param {HTMLElement|null} insertBefore - Element to insert before (optional)
 * @param {Function|null} onSuccess - Callback called when sources are displayed
 */
function wikitSemanticsFetchAndDisplaySources(queryId, appIdField, containerElement, pluginPath, insertBefore, onSuccess, onEmpty) {
    const csrfToken = document.querySelector('meta[property="glpi:csrf_token"]')?.getAttribute('content');
    const formData = new FormData();
    formData.append('queryId', queryId);
    formData.append('appIdField', appIdField);
    if (csrfToken) {
        formData.append('_glpi_csrf_token', csrfToken);
    }

    fetch(pluginPath + '/ajax/getsources.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success || !data.sources || !data.sources.length) {
            if (typeof onEmpty === 'function') onEmpty();
            return;
        }

        // Deduplicate sources by document ID
        const seenDocIds = new Set();
        const uniqueSources = [];
        for (const item of data.sources) {
            const docId = item.document?.id || null;
            if (docId && seenDocIds.has(docId)) continue;
            if (docId) seenDocIds.add(docId);
            uniqueSources.push(item);
        }

        if (!uniqueSources.length) {
            if (typeof onEmpty === 'function') onEmpty();
            return;
        }

        const sourcesDiv = document.createElement('div');
        sourcesDiv.className = 'wikitsemantics-sources';
        sourcesDiv.style.cssText = 'padding: 0 20px; margin-top: 16px;';

        const title = document.createElement('h6');
        title.style.cssText = 'color:#666; margin-bottom:10px;';
        title.textContent = 'Sources';
        sourcesDiv.appendChild(title);

        const cardsContainer = document.createElement('div');
        cardsContainer.style.cssText = 'display:flex;flex-wrap:wrap;gap:10px;';

        const maxSources = Math.min(uniqueSources.length, 5);
        for (let i = 0; i < maxSources; i++) {
            const source = uniqueSources[i];
            const card = document.createElement('div');
            card.className = 'wikitsemantics-source-card';
            card.style.cssText = 'border:1px solid #e0e0e0;border-radius:8px;padding:12px 16px;min-width:200px;max-width:300px;flex:1;';

            const nameDiv = document.createElement('div');
            nameDiv.style.cssText = 'font-weight:600;margin-bottom:4px;';

            // API returns nested: source.document.name, source.document.url, source.chunk.data
            const doc = source.document || {};
            const chunk = source.chunk || {};
            const docName = doc.name || doc.title || source.name || 'Document';
            const docUrl = doc.url || null;
            const dataSourceName = source.data_source?.name || null;

            if (docUrl) {
                const link = document.createElement('a');
                link.href = docUrl;
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
                link.textContent = docName;
                link.style.cssText = 'color:#1a73e8;text-decoration:none;';
                link.onmouseenter = () => link.style.textDecoration = 'underline';
                link.onmouseleave = () => link.style.textDecoration = 'none';
                nameDiv.appendChild(link);
            } else {
                nameDiv.textContent = docName;
            }

            card.appendChild(nameDiv);

            // Show data source name if different from doc name
            if (dataSourceName && dataSourceName !== docName) {
                const dsDiv = document.createElement('div');
                dsDiv.style.cssText = 'font-size:0.8em;color:#aaa;margin-bottom:4px;';
                dsDiv.textContent = dataSourceName;
                card.appendChild(dsDiv);
            }

            // Show excerpt from chunk data
            const quote = chunk.data || '';
            if (quote) {
                const excerptDiv = document.createElement('div');
                excerptDiv.style.cssText = 'font-size:0.85em;color:#888;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;';
                excerptDiv.textContent = quote.substring(0, 200);
                card.appendChild(excerptDiv);
            }

            cardsContainer.appendChild(card);
        }

        if (uniqueSources.length > 5) {
            const moreDiv = document.createElement('div');
            moreDiv.style.cssText = 'font-size:0.85em;color:#888;padding:8px 0;';
            moreDiv.textContent = '+' + (uniqueSources.length - 5) + ' more sources';
            cardsContainer.appendChild(moreDiv);
        }

        sourcesDiv.appendChild(cardsContainer);

        if (insertBefore) {
            containerElement.insertBefore(sourcesDiv, insertBefore);
        } else {
            containerElement.appendChild(sourcesDiv);
        }

        if (typeof onSuccess === 'function') {
            onSuccess();
        }
    })
    .catch(() => {
        if (typeof onEmpty === 'function') onEmpty();
    });
}

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
     * Show the modal - injects it lazily if needed
     */
   showModal() {
       let modalElement = document.getElementById('popupAnswer');

       // If modal doesn't exist, inject it now from global variable
      if (!modalElement && window.wikitSemanticsModalHTML) {
          document.body.insertAdjacentHTML('beforeend', window.wikitSemanticsModalHTML);
          modalElement = document.getElementById('popupAnswer');
      }

      if (modalElement) {
          const modal = new bootstrap.Modal(modalElement);
          modal.show();
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
            tinymce.innerHTML = wikitSemanticsStripCitationsFromHtml(result.content);
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
       let queryId = null;
       let spinnerTimeout = null;
       let spinnerVisible = true;

       spinnerTimeout = setTimeout(() => {
            spinnerVisible = false;
            modalBody.innerHTML = '<div id="divanswer" style="padding: 20px;">' + wikitSemanticsTextToHtml(accumulatedText) + '</div>';
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
                        const htmlContent = wikitSemanticsTextToHtml(accumulatedText);
                        modalBody.innerHTML = '<div id="divanswer" style="padding: 20px;">' + htmlContent + '</div>';

                        // Sources container (hidden until user clicks Sources button)
                        const sourcesContainer = document.createElement('div');
                        sourcesContainer.id = 'wikitsemantics-sources-container';
                        modalBody.appendChild(sourcesContainer);

                        const btnContainer = document.createElement('div');
                        btnContainer.className = 'mt-3 px-3 pb-3';

                        const btnAdd = document.createElement('button');
                        btnAdd.id = 'btnAddAnswer';
                        btnAdd.className = 'btn btn-primary me-2';
                        btnAdd.textContent = this.config.labels.addToTicket;
                        btnAdd.setAttribute('data-bs-dismiss', 'modal');
                        btnAdd.onclick = () => {
                            this.addAnswerToTicket({content: htmlContent}, itemType);
                        };

                        const btnClose = document.createElement('button');
                        btnClose.id = 'btnClose';
                        btnClose.className = 'btn btn-secondary me-2';
                        btnClose.textContent = this.config.labels.close;
                        btnClose.setAttribute('data-bs-dismiss', 'modal');
                        btnClose.onclick = () => this.close();

                        btnContainer.appendChild(btnAdd);
                        btnContainer.appendChild(btnClose);

                        // Sources button — only if we have a queryId
                        if (queryId) {
                            const btnSources = document.createElement('button');
                            btnSources.className = 'btn btn-outline-info';
                            btnSources.innerHTML = '<i class="ti ti-book me-1"></i>Sources';
                            btnSources.onclick = (e) => {
                                e.preventDefault();
                                btnSources.disabled = true;
                                btnSources.innerHTML = '<i class="fas fa-spinner fa-pulse me-1"></i>Sources';
                                wikitSemanticsFetchAndDisplaySources(queryId, 'app_id_answer', sourcesContainer, this.config.pluginPath, null, () => {
                                    btnSources.style.display = 'none';
                                }, () => {
                                    btnSources.disabled = false;
                                    btnSources.innerHTML = '<i class="ti ti-book me-1"></i>Sources';
                                    sourcesContainer.innerHTML = '<div style="padding: 0 20px; margin-top: 8px; color: #999; font-style: italic; font-size: 0.9em;">' + this.config.labels.noSources + '</div>';
                                });
                            };
                            btnContainer.appendChild(btnSources);
                        }

                        modalBody.appendChild(btnContainer);
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
                              if (data.queryId && !queryId) {
                                  queryId = data.queryId;
                              }
                              if (data.chunk) {
                                  accumulatedText += data.chunk;

                                 if (!spinnerVisible) {
                                    const answerDiv = document.querySelector('#divanswer');
                                    if (answerDiv) {
                                        answerDiv.innerHTML = wikitSemanticsTextToHtml(accumulatedText);
                                    }
                                 } else {
                                     clearTimeout(spinnerTimeout);
                                     spinnerVisible = false;
                                     modalBody.innerHTML = '<div id="divanswer" style="padding: 20px;">' + wikitSemanticsTextToHtml(accumulatedText) + '</div>';
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
                   // Server already returns buttons, just insert the HTML (strip citations)
                   modalBody.innerHTML = wikitSemanticsStripCitationsFromHtml(html);

                   // Add Sources button if queryId is present
                   const queryIdEl = document.getElementById('wikitsemantics-query-id');
                   if (queryIdEl && queryIdEl.value) {
                       const sourcesContainer = document.createElement('div');
                       sourcesContainer.id = 'wikitsemantics-sources-container';
                       const btnClose = document.getElementById('btnClose');
                       if (btnClose) {
                           btnClose.parentNode.insertBefore(sourcesContainer, btnClose.parentNode.firstChild);
                       }

                       const btnSources = document.createElement('button');
                       btnSources.className = 'btn btn-outline-info ms-2';
                       btnSources.innerHTML = '<i class="ti ti-book me-1"></i>Sources';
                       btnSources.onclick = (e) => {
                           e.preventDefault();
                           btnSources.disabled = true;
                           btnSources.innerHTML = '<i class="fas fa-spinner fa-pulse me-1"></i>Sources';
                           wikitSemanticsFetchAndDisplaySources(
                               queryIdEl.value, 'app_id_answer', sourcesContainer, this.config.pluginPath, null, () => {
                                   btnSources.style.display = 'none';
                               }, () => {
                                   btnSources.disabled = false;
                                   btnSources.innerHTML = '<i class="ti ti-book me-1"></i>Sources';
                                   sourcesContainer.innerHTML = '<div style="padding: 0 20px; margin-top: 8px; color: #999; font-style: italic; font-size: 0.9em;">' + this.config.labels.noSources + '</div>';
                               }
                           );
                       };
                       // Insert after the last button
                       const btnCloseEl = document.getElementById('btnClose');
                       if (btnCloseEl && btnCloseEl.parentNode) {
                           btnCloseEl.parentNode.appendChild(btnSources);
                       }
                   }

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
            pluginPath: container.dataset.pluginPath || '../plugins/wikitsemantics',
            ajaxUrl: container.dataset.ajaxUrl,
            ajaxStreamUrl: container.dataset.ajaxStreamUrl,
            labels: {
               addToTicket: container.dataset.labelAdd,
               close: container.dataset.labelClose,
               error: container.dataset.labelError,
               noSources: container.dataset.labelNoSources || 'No sources available for this answer.'
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

         // Create button using the same GLPI form-field structure as the KB search button
         const wrapper = document.createElement('div');
         wrapper.className = 'form-field row align-items-center col-12 glpi-full-width mb-2';

         const label = document.createElement('label');
         label.className = 'col-form-label col-2 text-xxl-end';
         label.innerHTML = ' ';

         const fieldContainer = document.createElement('div');
         fieldContainer.className = 'col-10 field-container';

         const button = document.createElement('button');
         button.setAttribute('type', 'button');
         button.className = 'btn btn-secondary overflow-hidden text-nowrap';
         button.setAttribute('data-bs-toggle', 'tooltip');
         button.setAttribute('data-bs-placement', 'top');
         button.setAttribute('title', config.buttonLabel);
         button.innerHTML = '<i class="ti ti-wand"></i>';

         // Attach click event handler
         button.onclick = function(e) {
            e.preventDefault();
            generator.showModal();
            const answerFunction = 'addAnswer' + config.itemType.charAt(0).toUpperCase() + config.itemType.slice(1);
            const closeFunction = 'close' + config.itemType.charAt(0).toUpperCase() + config.itemType.slice(1);
            generator.generate(config.ticketId, config.itemType, answerFunction, closeFunction);
         };

         fieldContainer.appendChild(button);
         wrapper.appendChild(label);
         wrapper.appendChild(fieldContainer);

         // Find the KB search button and insert right before it for alignment
         const searchBtnRow = targetContainer.querySelector('[name^="search_knowbaseitem_"]');
         const searchBtnWrapper = searchBtnRow ? searchBtnRow.closest('.form-field') : null;
        if (searchBtnWrapper) {
            searchBtnWrapper.parentNode.insertBefore(wrapper, searchBtnWrapper);
        } else {
            targetContainer.insertBefore(wrapper, targetContainer.firstChild);
        }

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
       initializeWikitSemanticsButtons();
   }
});

// Start observing the document for DOM changes
observer.observe(document.body, {
   childList: true,
   subtree: true
});

// ============================================================================
// Knowledge Base Generator
// ============================================================================

class WikitSemanticsKBGenerator {
   constructor(config) {
       this.config = config;
       this.generatedContent = '';
       this.suggestedTitle = '';
   }

    /**
     * Generate KB article using streaming mode
     */
   generateStreaming() {
       const container = this.config.container;
       const loadingEl = container.querySelector('.wikitsemantics-kb-loading');
       const resultEl = container.querySelector('.wikitsemantics-kb-result');
       const actionsEl = container.querySelector('.wikitsemantics-kb-actions');
       const contentEl = container.querySelector('.wikitsemantics-kb-content');

       actionsEl.style.display = 'none';
       resultEl.style.display = 'none';
       loadingEl.style.display = 'block';

       let accumulatedText = '';
       this.suggestedTitle = '';

       const csrfToken = document.querySelector('meta[property="glpi:csrf_token"]')?.getAttribute('content');
       const formData = new FormData();
       formData.append('ticketId', this.config.ticketId);
      if (csrfToken) {
          formData.append('_glpi_csrf_token', csrfToken);
      }

       fetch(this.config.ajaxStreamUrl, {
           method: 'POST',
           body: formData
       }).then(response => {
          if (response.status !== 200) {
              throw new Error('HTTP ' + response.status);
          }

           const reader = response.body.getReader();
           const decoder = new TextDecoder();
           let contentShown = false;

           const read = () => {
               reader.read().then(({done, value}) => {
                  if (done) {
                      loadingEl.style.display = 'none';
                      const htmlContent = wikitSemanticsTextToHtml(accumulatedText);
                      contentEl.innerHTML = htmlContent;
                      resultEl.style.display = 'block';
                      this.generatedContent = htmlContent;
                      return;
                  }

                   const chunk = decoder.decode(value, {stream: true});
                   const lines = chunk.split('\n');

                  for (let line of lines) {
                      line = line.trim();
                     if (!line) continue;

                     if (line.startsWith('event:')) continue;

                     if (line.startsWith('data: ')) {
                        try {
                            const data = JSON.parse(line.substring(6));
                           if (data.chunk) {
                               accumulatedText += data.chunk;

                              if (!contentShown) {
                                  contentShown = true;
                                  loadingEl.style.display = 'none';
                                  resultEl.style.display = 'block';
                              }
                               contentEl.innerHTML = wikitSemanticsTextToHtml(accumulatedText);
                           } else if (data.token) {
                               const metaTag = document.querySelector('meta[property="glpi:csrf_token"]');
                              if (metaTag) {
                                  metaTag.setAttribute('content', data.token);
                              }
                           } else if (data.title) {
                               this.suggestedTitle = data.title;
                           }
                        } catch (e) {
                            console.error('[WikitSemantics KB] Parse error:', e);
                        }
                     }
                  }

                   read();
               }).catch(error => {
                   console.error('[WikitSemantics KB] Read error:', error);
                   loadingEl.style.display = 'none';
                   contentEl.innerHTML = '<div class="alert alert-danger">' + this.config.labels.error + '</div>';
                   resultEl.style.display = 'block';
               });
           };

           read();
       }).catch(error => {
           console.error('[WikitSemantics KB] Fetch error:', error);
           loadingEl.style.display = 'none';
           contentEl.innerHTML = '<div class="alert alert-danger">' + this.config.labels.error + '</div>';
           resultEl.style.display = 'block';
       });
   }

    /**
     * Generate KB article using AJAX mode (fallback)
     */
   generateAjax() {
       const container = this.config.container;
       const loadingEl = container.querySelector('.wikitsemantics-kb-loading');
       const resultEl = container.querySelector('.wikitsemantics-kb-result');
       const actionsEl = container.querySelector('.wikitsemantics-kb-actions');
       const contentEl = container.querySelector('.wikitsemantics-kb-content');

       actionsEl.style.display = 'none';
       resultEl.style.display = 'none';
       loadingEl.style.display = 'block';

       $.ajax({
           type: 'POST',
           url: this.config.ajaxUrl,
           data: {
               'ticketId': this.config.ticketId,
               'action': 'generate'
           },
           dataType: 'json',
           success: (response) => {
               loadingEl.style.display = 'none';
              if (response.success) {
                  contentEl.innerHTML = response.content;
                  resultEl.style.display = 'block';
                  this.generatedContent = response.content;
                  this.suggestedTitle = response.suggested_title || '';
              } else {
                  contentEl.innerHTML = '<div class="alert alert-danger">' + (response.error || this.config.labels.error) + '</div>';
                  resultEl.style.display = 'block';
              }
           },
           error: () => {
               loadingEl.style.display = 'none';
               contentEl.innerHTML = '<div class="alert alert-danger">' + this.config.labels.error + '</div>';
               resultEl.style.display = 'block';
           }
       });
   }

    /**
     * Main generate method — dispatches to streaming or AJAX
     */
   generate() {
      if (this.config.isStreamingEnabled) {
          this.generateStreaming();
      } else {
          this.generateAjax();
      }
   }

    /**
     * Create Knowledge Base article from generated content
     */
   createKBArticle() {
       const title = this.suggestedTitle || 'Knowledge Base Article';

       $.ajax({
           type: 'POST',
           url: this.config.ajaxUrl,
           data: {
               'ticketId': this.config.ticketId,
               'action': 'create_kb',
               'kb_name': title,
               'kb_content': this.generatedContent
           },
           dataType: 'json',
           success: (response) => {
              if (response.success && response.redirect_url) {
                  window.location.href = response.redirect_url;
              } else {
                  alert(response.error || 'Failed to create article');
              }
           },
           error: () => {
               alert('Failed to create article');
           }
       });
   }

    /**
     * Reset the tab to initial state
     */
   reset() {
       const container = this.config.container;
       container.querySelector('.wikitsemantics-kb-actions').style.display = 'block';
       container.querySelector('.wikitsemantics-kb-loading').style.display = 'none';
       container.querySelector('.wikitsemantics-kb-result').style.display = 'none';
       container.querySelector('.wikitsemantics-kb-content').innerHTML = '';
       this.generatedContent = '';
       this.suggestedTitle = '';
   }
}

window.WikitSemanticsKBGenerator = WikitSemanticsKBGenerator;

/**
 * Auto-initialize Knowledge Base tab containers
 */
function initializeWikitSemanticsKBTabs() {
    document.querySelectorAll('.wikitsemantics-kb-container').forEach(container => {
      if (container.dataset.initialized === 'true') return;
       container.dataset.initialized = 'true';

       const config = {
           container: container,
           ticketId: parseInt(container.dataset.ticketId, 10),
           isStreamingEnabled: parseInt(container.dataset.streamingEnabled, 10),
           pluginPath: container.dataset.pluginPath || '../plugins/wikitsemantics',
           ajaxUrl: container.dataset.ajaxUrl,
           ajaxStreamUrl: container.dataset.ajaxStreamUrl,
           canCreateKb: parseInt(container.dataset.canCreateKb, 10),
           labels: {
               generate: container.dataset.labelGenerate,
               create: container.dataset.labelCreate,
               discard: container.dataset.labelDiscard,
               regenerate: container.dataset.labelRegenerate,
               error: container.dataset.labelError,
           }
       };

       const generator = new WikitSemanticsKBGenerator(config);

       // Generate button
       const generateBtn = container.querySelector('.wikitsemantics-kb-generate-btn');
      if (generateBtn) {
          generateBtn.onclick = (e) => {
              e.preventDefault();
              generator.generate();
          };
      }

       // Create KB button
       const createBtn = container.querySelector('.wikitsemantics-kb-create-btn');
      if (createBtn) {
          createBtn.onclick = (e) => {
              e.preventDefault();
              generator.createKBArticle();
          };
      }

       // Regenerate button
       const regenerateBtn = container.querySelector('.wikitsemantics-kb-regenerate-btn');
      if (regenerateBtn) {
          regenerateBtn.onclick = (e) => {
              e.preventDefault();
              generator.generate();
          };
      }

       // Discard button
       const discardBtn = container.querySelector('.wikitsemantics-kb-discard-btn');
      if (discardBtn) {
          discardBtn.onclick = (e) => {
              e.preventDefault();
              generator.reset();
          };
      }

    });
}

// Initialize KB tabs on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeWikitSemanticsKBTabs);
} else {
    initializeWikitSemanticsKBTabs();
}

// Observe for dynamically loaded KB tabs (GLPI loads tabs via AJAX)
const kbObserver = new MutationObserver(function(mutations) {
    let shouldReinitialize = false;
    mutations.forEach(function(mutation) {
        mutation.addedNodes.forEach(function(node) {
         if (node.nodeType === 1) {
            if (node.classList && node.classList.contains('wikitsemantics-kb-container')) {
                shouldReinitialize = true;
            } else if (node.querySelector && node.querySelector('.wikitsemantics-kb-container')) {
                shouldReinitialize = true;
            }
         }
        });
    });
   if (shouldReinitialize) {
       initializeWikitSemanticsKBTabs();
   }
});

kbObserver.observe(document.body, {
   childList: true,
   subtree: true
});

// ============================================================================
// Editor AI Assistant — Magic Wand for TinyMCE
// ============================================================================

class WikitSemanticsEditorAI {
   constructor(config) {
       this.config = config;
       this.currentEditorIframe = null;
   }

    /**
     * Show the editor AI modal
     */
   showModal() {
       const modalElement = document.getElementById('popupEditorAI');
      if (modalElement) {
          const modal = new bootstrap.Modal(modalElement);
          modal.show();
      }
   }

    /**
     * Reset modal body to spinner
     */
   resetModal() {
       const modalBody = document.querySelector('#popupEditorAI .modal-body');
      if (modalBody) {
          modalBody.innerHTML = '<div style="display: block; height: 200px; padding: 20px"><i class="fas fa-4x fa-spinner fa-pulse m-5 start-50" style="position: relative; margin: auto !important;"></i></div>';
      }
   }

    /**
     * Get content from a TinyMCE iframe
     * @param {HTMLIFrameElement} iframe
     * @returns {string} Editor HTML content
     */
   getEditorContent(iframe) {
      if (iframe && iframe.contentWindow && iframe.contentWindow.document.body) {
          const body = iframe.contentWindow.document.body;
          return body.innerHTML || '';
      }
       return '';
   }

    /**
     * Set content into a TinyMCE iframe
     * @param {HTMLIFrameElement} iframe
     * @param {string} html
     */
   setEditorContent(iframe, html) {
      if (iframe && iframe.contentWindow && iframe.contentWindow.document.body) {
          iframe.contentWindow.document.body.innerHTML = html;
      }
   }

    /**
     * Find the TinyMCE iframe associated with a container element
     * @param {HTMLElement} element - The .tox container or a child element
     * @returns {HTMLIFrameElement|null}
     */
   findEditorIframe(element) {
       // Find the iframe inside .tox-editor-container
       // element can be the .tox container itself or a child
       const toxContainer = element.classList.contains('tox') ? element : element.closest('.tox');
      if (toxContainer) {
          return toxContainer.querySelector('.tox-editor-container iframe');
      }
       return null;
   }

    /**
     * Process an editor AI action
     * @param {string} action - 'correction', 'formatting', or 'translation'
     * @param {HTMLIFrameElement} editorIframe
     */
   processAction(action, editorIframe) {
       const content = this.getEditorContent(editorIframe);
      if (!content || !content.trim()) {
          return;
      }

       this.currentEditorIframe = editorIframe;
       this.showModal();
       this.resetModal();

      if (this.config.isStreamingEnabled) {
          this.generateStreaming(action, content);
      } else {
          this.generateAjax(action, content);
      }
   }

    /**
     * Generate using streaming mode
     * @param {string} action
     * @param {string} content
     */
   generateStreaming(action, content) {
       const modalBody = document.querySelector('#popupEditorAI .modal-body');
      if (!modalBody) return;

       let accumulatedText = '';
       let spinnerVisible = true;

       const spinnerTimeout = setTimeout(() => {
           spinnerVisible = false;
           modalBody.innerHTML = '<div id="divEditorAIResult" style="padding: 20px;">' + wikitSemanticsTextToHtml(accumulatedText) + '</div>';
       }, 1000);

       const csrfToken = document.querySelector('meta[property="glpi:csrf_token"]')?.getAttribute('content');
       const formData = new FormData();
       formData.append('content', content);
       formData.append('action', action);
      if (csrfToken) {
          formData.append('_glpi_csrf_token', csrfToken);
      }

       fetch(this.config.ajaxStreamUrl, {
           method: 'POST',
           body: formData
       }).then(response => {
          if (response.status !== 200) {
              throw new Error('HTTP ' + response.status);
          }

           const reader = response.body.getReader();
           const decoder = new TextDecoder();

           const read = () => {
               reader.read().then(({done, value}) => {
                  if (done) {
                      clearTimeout(spinnerTimeout);
                      const htmlContent = wikitSemanticsTextToHtml(accumulatedText);
                      modalBody.innerHTML = '<div id="divEditorAIResult" style="padding: 20px;">' + htmlContent + '</div>';
                      this.addActionButtons(modalBody, htmlContent);
                      return;
                  }

                   const chunk = decoder.decode(value, {stream: true});
                   const lines = chunk.split('\n');

                  for (let line of lines) {
                      line = line.trim();
                     if (!line) continue;
                     if (line.startsWith('event:')) continue;

                     if (line.startsWith('data: ')) {
                        try {
                            const data = JSON.parse(line.substring(6));
                           if (data.chunk) {
                               accumulatedText += data.chunk;
                              if (!spinnerVisible) {
                                  const resultDiv = document.querySelector('#divEditorAIResult');
                                 if (resultDiv) {
                                     resultDiv.innerHTML = wikitSemanticsTextToHtml(accumulatedText);
                                 }
                              } else {
                                  clearTimeout(spinnerTimeout);
                                  spinnerVisible = false;
                                  modalBody.innerHTML = '<div id="divEditorAIResult" style="padding: 20px;">' + wikitSemanticsTextToHtml(accumulatedText) + '</div>';
                              }
                           } else if (data.token) {
                               const metaTag = document.querySelector('meta[property="glpi:csrf_token"]');
                              if (metaTag) {
                                  metaTag.setAttribute('content', data.token);
                              }
                           }
                        } catch (e) {
                            console.error('[WikitSemantics EditorAI] Parse error:', e);
                        }
                     }
                  }

                   read();
               }).catch(error => {
                   console.error('[WikitSemantics EditorAI] Read error:', error);
                   clearTimeout(spinnerTimeout);
                   modalBody.innerHTML = '<div style="padding: 20px; color: red;">' + this.config.labels.error + '</div>';
                   this.addCloseButton(modalBody);
               });
           };

           read();
       }).catch(error => {
           console.error('[WikitSemantics EditorAI] Fetch error:', error);
           clearTimeout(spinnerTimeout);
           modalBody.innerHTML = '<div style="padding: 20px; color: red;">' + this.config.labels.error + '</div>';
           this.addCloseButton(modalBody);
       });
   }

    /**
     * Generate using AJAX mode (fallback)
     * @param {string} action
     * @param {string} content
     */
   generateAjax(action, content) {
       const modalBody = document.querySelector('#popupEditorAI .modal-body');
      if (!modalBody) return;

       $.ajax({
           type: 'POST',
           url: this.config.ajaxUrl,
           data: {
               'content': content,
               'action': action
           },
           dataType: 'json',
           success: (response) => {
              if (response.success) {
                  modalBody.innerHTML = '<div id="divEditorAIResult" style="padding: 20px;">' + response.content + '</div>';
                  this.addActionButtons(modalBody, response.content);
              } else {
                  modalBody.innerHTML = '<div style="padding: 20px; color: red;">' + (response.error || this.config.labels.error) + '</div>';
                  this.addCloseButton(modalBody);
              }
           },
           error: () => {
               modalBody.innerHTML = '<div style="padding: 20px; color: red;">' + this.config.labels.error + '</div>';
               this.addCloseButton(modalBody);
           }
       });
   }

    /**
     * Add Apply + Close buttons to the modal
     * @param {HTMLElement} modalBody
     * @param {string} htmlContent - The generated content to apply
     */
   addActionButtons(modalBody, htmlContent) {
       const btnContainer = document.createElement('div');
       btnContainer.className = 'mt-3 px-3 pb-3';

       const btnApply = document.createElement('button');
       btnApply.className = 'btn btn-primary me-2';
       btnApply.textContent = this.config.labels.apply;
       btnApply.setAttribute('data-bs-dismiss', 'modal');
       btnApply.onclick = () => {
          if (this.currentEditorIframe) {
              this.setEditorContent(this.currentEditorIframe, htmlContent);
          }
           this.resetModal();
       };

       const btnClose = document.createElement('button');
       btnClose.className = 'btn btn-secondary';
       btnClose.textContent = this.config.labels.close;
       btnClose.setAttribute('data-bs-dismiss', 'modal');
       btnClose.onclick = () => this.resetModal();

       btnContainer.appendChild(btnApply);
       btnContainer.appendChild(btnClose);
       modalBody.appendChild(btnContainer);
   }

    /**
     * Add only a Close button to the modal
     * @param {HTMLElement} modalBody
     */
   addCloseButton(modalBody) {
       const btn = document.createElement('button');
       btn.className = 'btn btn-secondary mt-3 ms-3';
       btn.textContent = this.config.labels.close;
       btn.setAttribute('data-bs-dismiss', 'modal');
       btn.onclick = () => this.resetModal();
       modalBody.appendChild(btn);
   }

    /**
     * Create and inject the magic wand button into a TinyMCE editor
     * @param {HTMLElement} toxContainer - The .tox-tinymce container element
     */
   injectButton(toxContainer) {
      if (toxContainer.querySelector('.wikitsemantics-editor-ai-btn')) {
          return; // Already injected
      }

       const iframe = toxContainer.querySelector('.tox-editor-container iframe');
      if (!iframe) return;

       // Find the stable header element — this does NOT get rebuilt by TinyMCE
       const header = toxContainer.querySelector('.tox-editor-header');
      if (!header) return;

       // Ensure the header is positioned for absolute child
       header.style.position = 'relative';

       const self = this;

       // Create the floating button — positioned absolutely in the header
       const btnWrapper = document.createElement('div');
       btnWrapper.className = 'wikitsemantics-editor-ai-btn';
       btnWrapper.style.cssText = 'position:absolute;top:4px;right:8px;z-index:1;';

       // Create the button matching TinyMCE button style
       const btn = document.createElement('button');
       btn.className = 'tox-tbtn';
       btn.setAttribute('type', 'button');
       btn.setAttribute('title', this.config.labels.editorAI);
       btn.style.cssText = 'display:flex;align-items:center;justify-content:center;width:34px;height:34px;padding:0;cursor:pointer;';
       btn.innerHTML = '<span class="tox-icon" style="display:flex;align-items:center;justify-content:center;">'
           + '<i class="ti ti-wand" style="font-size:18px;"></i>'
           + '</span>';

       // Create TinyMCE-native dropdown menu — appended to body to avoid overflow clipping
       const menu = document.createElement('div');
       menu.className = 'tox-menu tox-collection tox-collection--list';
       menu.setAttribute('role', 'menu');
       menu.style.cssText = 'position:fixed;z-index:10060;display:none;min-width:200px;background:#fff;border-radius:6px;box-shadow:0 2px 12px rgba(0,0,0,0.15);';

       const actions = [
           { action: 'correction', label: this.config.labels.correction, icon: 'ti ti-writing' },
           { action: 'formatting', label: this.config.labels.formatting, icon: 'ti ti-layout-list' },
       ];

      for (const item of actions) {
          const menuItem = document.createElement('div');
          menuItem.className = 'tox-collection__item';
          menuItem.setAttribute('role', 'menuitem');
          menuItem.style.cssText = 'cursor:pointer;display:flex;align-items:center;padding:4px 8px;';
          const icon = document.createElement('div');
          icon.className = 'tox-collection__item-icon';
          icon.style.cssText = 'width:24px;height:24px;display:flex;align-items:center;justify-content:center;margin-right:8px;flex-shrink:0;';
          icon.innerHTML = '<i class="' + item.icon + '" style="font-size:18px;"></i>';
          const itemLabel = document.createElement('div');
          itemLabel.className = 'tox-collection__item-label';
          itemLabel.textContent = item.label;
          menuItem.appendChild(icon);
          menuItem.appendChild(itemLabel);
          menuItem.onclick = (e) => {
              e.preventDefault();
              e.stopPropagation();
              menu.style.display = 'none';
              self.processAction(item.action, iframe);
          };
          menu.appendChild(menuItem);
      }

       // Translation item with submenu
       const languages = [
           { code: 'en', label: 'English' },
           { code: 'fr', label: 'Français' },
           { code: 'es', label: 'Español' },
       ];

       const translationItem = document.createElement('div');
       translationItem.className = 'tox-collection__item';
       translationItem.setAttribute('role', 'menuitem');
       translationItem.style.cssText = 'cursor:pointer;display:flex;align-items:center;padding:4px 8px;position:relative;';
       const translationIcon = document.createElement('div');
       translationIcon.className = 'tox-collection__item-icon';
       translationIcon.style.cssText = 'width:24px;height:24px;display:flex;align-items:center;justify-content:center;margin-right:8px;flex-shrink:0;';
       translationIcon.innerHTML = '<i class="ti ti-language" style="font-size:18px;"></i>';
       const translationLabel = document.createElement('div');
       translationLabel.className = 'tox-collection__item-label';
       translationLabel.style.cssText = 'flex:1;';
       translationLabel.textContent = this.config.labels.translation;
       const translationArrow = document.createElement('div');
       translationArrow.className = 'tox-collection__item-caret';
       translationArrow.style.cssText = 'margin-left:auto;display:flex;align-items:center;';
       translationArrow.innerHTML = '<i class="ti ti-chevron-right" style="font-size:14px;"></i>';
       translationItem.appendChild(translationIcon);
       translationItem.appendChild(translationLabel);
       translationItem.appendChild(translationArrow);

       // Submenu for languages
       const subMenu = document.createElement('div');
       subMenu.className = 'tox-menu tox-collection tox-collection--list';
       subMenu.style.cssText = 'position:fixed;z-index:10061;display:none;min-width:160px;background:#fff;border-radius:6px;box-shadow:0 2px 12px rgba(0,0,0,0.15);';

      for (const lang of languages) {
          const langItem = document.createElement('div');
          langItem.className = 'tox-collection__item';
          langItem.setAttribute('role', 'menuitem');
          langItem.style.cssText = 'cursor:pointer;display:flex;align-items:center;padding:4px 8px;';
          const langLabel = document.createElement('div');
          langLabel.className = 'tox-collection__item-label';
          langLabel.textContent = lang.label;
          langItem.appendChild(langLabel);
          langItem.onclick = (e) => {
              e.preventDefault();
              e.stopPropagation();
              menu.style.display = 'none';
              subMenu.style.display = 'none';
              self.processAction('translation_' + lang.code, iframe);
          };
          subMenu.appendChild(langItem);
      }

       // Show/hide submenu on hover
       translationItem.addEventListener('mouseenter', () => {
           const rect = translationItem.getBoundingClientRect();
           subMenu.style.display = 'block';
           subMenu.style.top = rect.top + 'px';
           // Show on right by default, flip to left if too close to screen edge
           const subMenuWidth = subMenu.offsetWidth;
          if (rect.right + subMenuWidth > window.innerWidth) {
              subMenu.style.left = (rect.left - subMenuWidth) + 'px';
          } else {
              subMenu.style.left = rect.right + 'px';
          }
       });
       translationItem.addEventListener('mouseleave', (e) => {
          if (!subMenu.contains(e.relatedTarget)) {
              subMenu.style.display = 'none';
          }
       });
       subMenu.addEventListener('mouseleave', (e) => {
          if (!translationItem.contains(e.relatedTarget)) {
              subMenu.style.display = 'none';
          }
       });

       menu.appendChild(translationItem);
       document.body.appendChild(subMenu);

       // Toggle menu on button click — position relative to button
       btn.onclick = (e) => {
           e.preventDefault();
           e.stopPropagation();
           const isVisible = menu.style.display !== 'none';
          if (isVisible) {
              menu.style.display = 'none';
          } else {
              const rect = btn.getBoundingClientRect();
              menu.style.top = (rect.bottom) + 'px';
              menu.style.left = (rect.right - menu.offsetWidth) + 'px';
              menu.style.display = 'block';
              // Recalc left after display to get actual width
              const menuWidth = menu.offsetWidth;
              menu.style.left = (rect.right - menuWidth) + 'px';
          }
       };

       // Close menus helper
       const closeMenus = () => {
           menu.style.display = 'none';
           subMenu.style.display = 'none';
       };

       // Close menu on click outside
       document.addEventListener('click', (e) => {
          if (!btnWrapper.contains(e.target) && !menu.contains(e.target) && !subMenu.contains(e.target)) {
              closeMenus();
          }
       });

       // Close menu on focus change (scroll, resize, tab switch, editor focus)
       document.addEventListener('scroll', closeMenus, true);
       window.addEventListener('resize', closeMenus);
       window.addEventListener('blur', closeMenus);

       btnWrapper.appendChild(btn);
       document.body.appendChild(menu);
       header.appendChild(btnWrapper);
   }
}

window.WikitSemanticsEditorAI = WikitSemanticsEditorAI;

/**
 * Inject Editor AI buttons into all existing TinyMCE containers
 */
function injectEditorAIButtons() {
   if (!window._wikitSemanticsEditorAIInstance) return;

    const instance = window._wikitSemanticsEditorAIInstance;
    document.querySelectorAll('.tox.tox-tinymce').forEach(toxContainer => {
        instance.injectButton(toxContainer);
    });
}

/**
 * Load Editor AI config via AJAX and initialize
 * This runs on every page — the AJAX endpoint checks rights and config
 */
function loadAndInitEditorAI() {
    // Already loaded
   if (window.wikitSemanticsEditorAIConfig) {
       injectEditorAIButtons();
       return;
   }

    // Determine plugin path from current script URL
    const scriptEl = document.querySelector('script[src*="wikitsemantics"]');
    let pluginWebPath = '/plugins/wikitsemantics';
    if (scriptEl) {
        const match = scriptEl.src.match(/(.*\/plugins\/wikitsemantics)\//);
        if (match) {
            pluginWebPath = new URL(match[1]).pathname;
        }
    }

    fetch(pluginWebPath + '/ajax/editorai_config.php', {
        method: 'GET',
        credentials: 'same-origin',
    })
    .then(response => response.json())
    .then(config => {
        window.wikitSemanticsEditorAIConfig = config;

       if (!config || !config.enabled) return;

        // Inject the modal HTML into the DOM
        const modalHtml = '<div class="modal fade" id="popupEditorAI" tabindex="-1" aria-labelledby="popupEditorAILabel" aria-hidden="true">'
            + '<div class="modal-dialog modal-xl" style="max-width: 1180px;">'
            + '<div class="modal-content">'
            + '<div class="modal-header">'
            + '<h5 class="modal-title" id="popupEditorAILabel">' + (config.labels.editorAI || 'Editor AI') + '</h5>'
            + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>'
            + '</div>'
            + '<div class="modal-body" style="min-height: 500px;">'
            + '<div style="display: block; height: 200px; padding: 20px">'
            + '<i class="fas fa-4x fa-spinner fa-pulse m-5 start-50" style="position: relative; margin: auto !important;"></i>'
            + '</div></div></div></div></div>';

       if (!document.getElementById('popupEditorAI')) {
           document.body.insertAdjacentHTML('beforeend', modalHtml);
       }

        // Create singleton instance
        window._wikitSemanticsEditorAIInstance = new WikitSemanticsEditorAI(config);

        // Helper to hook into TinyMCE lifecycle
        function hookTinyMCE() {
            const inst = window._wikitSemanticsEditorAIInstance;

            // For editors already initialized
            tinymce.get().forEach(editor => {
                const container = editor.getContainer();
                if (container) {
                    inst.injectButton(container);
                }
            });

            // For editors initialized later (GLPI loads forms dynamically)
            tinymce.on('AddEditor', function(e) {
                e.editor.on('init', function() {
                    const container = e.editor.getContainer();
                    if (container) {
                        inst.injectButton(container);
                    }
                });
            });
        }

        if (typeof tinymce !== 'undefined') {
            hookTinyMCE();
        } else {
            // tinymce not yet loaded — wait for it
            const waitForTinyMCE = setInterval(() => {
                if (typeof tinymce !== 'undefined') {
                    clearInterval(waitForTinyMCE);
                    hookTinyMCE();
                }
            }, 300);
            setTimeout(() => clearInterval(waitForTinyMCE), 15000);
        }
    })
    .catch(error => {
        console.error('[WikitSemantics EditorAI] Failed to load config:', error);
    });
}

// Load Editor AI config on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadAndInitEditorAI);
} else {
    loadAndInitEditorAI();
}
