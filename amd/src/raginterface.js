// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * JavaScript for Terus RAG block.
 *
 * Handles the chat-style query interface: submits queries via Moodle AJAX,
 * renders structured answer items, and displays token-usage metadata.
 *
 * @module     block_terusrag/raginterface
 * @copyright  2025 Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification', 'core/str'],
    function(Ajax, Notification, Str) {
    'use strict';

    /** @type {Object} */
    var RAGInterface = {};

    /** @type {Object} CSS selector constants for DOM lookups. */
    var SELECTORS = {
        COMPONENT:        '.block_terusrag',
        FORM:             '.rag-form',
        QUERY_INPUT:      '[data-region="query-input"]',
        SUBMIT_BUTTON:    '[data-action="submit-query"]',
        RESPONSE_AREA:    '[data-region="response-area"]',
        RESPONSE_CONTENT: '[data-region="response-content"]',
        RESPONSE_TEXT:    '[data-region="response-text"]',
        RESPONSE_METADATA:'[data-region="response-metadata"]',
        LOADING_INDICATOR:'[data-region="loading-indicator"]',
        PLACEHOLDER:      '[data-region="placeholder"]'
    };

    /**
     * Initialise the RAG interface for a given block container.
     *
     * @param {string} selector CSS selector that matches the block root element.
     */
    RAGInterface.init = function(selector) {
        var container = document.querySelector(selector);
        if (!container) {
            return;
        }

        var form = container.querySelector(SELECTORS.FORM);
        if (!form) {
            return;
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var input = container.querySelector(SELECTORS.QUERY_INPUT);
            var query = input ? input.value.trim() : '';
            if (query) {
                RAGInterface.submitQuery(container, query);
            }
        });
    };

    /**
     * Submit a query to the RAG system via Moodle AJAX.
     *
     * @param {Element} container The RAG block root element.
     * @param {string}  query     The user's natural-language question.
     */
    RAGInterface.submitQuery = function(container, query) {
        var courseId          = container.dataset.courseid;
        var loadingIndicator  = container.querySelector(SELECTORS.LOADING_INDICATOR);
        var responseContent   = container.querySelector(SELECTORS.RESPONSE_CONTENT);
        var responseText      = container.querySelector(SELECTORS.RESPONSE_TEXT);
        var responseMetadata  = container.querySelector(SELECTORS.RESPONSE_METADATA);
        var queryInputField   = container.querySelector(SELECTORS.QUERY_INPUT);
        var contentPlaceholder = container.querySelector(SELECTORS.PLACEHOLDER);

        // Show loading state.
        loadingIndicator.classList.remove('hidden');
        contentPlaceholder.classList.add('hidden');
        responseText.textContent    = '';
        responseMetadata.textContent = '';
        responseContent.classList.remove('hidden');

        Ajax.call([{
            methodname: 'block_terusrag_submit_query',
            args: {
                query:    query,
                courseid: parseInt(courseId, 10) || 0
            },

            done: function(response) {
                // Clear input immediately after successful AJAX response.
                if (queryInputField) {
                    queryInputField.value = '';
                }

                loadingIndicator.classList.add('hidden');

                // Handle warning / error statuses returned by the server.
                // These are pre-translated by PHP via get_string(), so we
                // display them directly — no client-side Str.get_string() needed.
                if (response.status === 'warning' || response.status === 'error') {
                    responseContent.classList.add('hidden');
                    contentPlaceholder.classList.remove('hidden');

                    var alertClass = response.status === 'error' ? 'alert-danger' : 'alert-warning';
                    contentPlaceholder.innerHTML =
                        '<div class="alert ' + alertClass + ' mt-2" role="alert">'
                        + response.statusMessage
                        + '</div>';
                    return;
                }

                // Render answer items when the server returned results.
                if (response.answer && Array.isArray(response.answer) && response.answer.length > 0) {
                    responseText.innerHTML = response.answer.map(function(item) {
                        var titleHtml;
                        if (item.viewurl) {
                            titleHtml = '<a href="' + item.viewurl + '" target="_blank">'
                                + item.title + '</a>';
                        } else {
                            titleHtml = item.title;
                        }

                        return '<div class="rag-response-item mb-3">'
                            + '<h4 class="rag-response-title">' + titleHtml + '</h4>'
                            + '<div class="rag-response-content" data-content-id="' + item.id + '">'
                            + item.content
                            + '</div>'
                            + '</div>';
                    }).join('');

                    contentPlaceholder.classList.add('hidden');
                    responseContent.classList.remove('hidden');

                } else {
                    // No results — show a localised message using the async Str API.
                    Str.get_string('noresultsfound', 'block_terusrag').then(function(str) {
                        responseText.innerHTML =
                            '<p class="text-muted">' + str + '</p>';
                        return;
                    }).catch(Notification.exception);

                    contentPlaceholder.classList.add('hidden');
                    responseContent.classList.remove('hidden');
                }

                // Render token-usage metadata using the async Str API.
                // Moodle's Str.get_string() always returns a Promise — never concatenate it directly.
                if (response.promptTokenCount !== undefined
                        && response.responseTokenCount !== undefined
                        && response.totalTokenCount !== undefined) {

                    Str.get_string('token_usage', 'block_terusrag', {
                        prompt:   response.promptTokenCount,
                        response: response.responseTokenCount,
                        total:    response.totalTokenCount
                    }).then(function(str) {
                        responseMetadata.innerHTML = str;
                        return;
                    }).catch(Notification.exception);

                } else {
                    Str.get_string('notokeninformation', 'block_terusrag').then(function(str) {
                        responseMetadata.innerHTML =
                            '<span class="token-info">' + str + '</span>';
                        return;
                    }).catch(Notification.exception);
                }
            },

            fail: function(error) {
                Notification.exception(error);
                loadingIndicator.classList.add('hidden');
                responseContent.classList.add('hidden');
                contentPlaceholder.classList.remove('hidden');
            }
        }]);
    };

    return RAGInterface;
});
