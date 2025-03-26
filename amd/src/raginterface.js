/* eslint-disable */
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
 * @module     block_terusrag/raginterface
  * @copyright  2025 Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification', 'core/str'],
    function(Ajax, Notification, Str) {
    'use strict';

    /**
     * Module level variables.
     */
    var RAGInterface = {};
    var SELECTORS = {
        COMPONENT: '.block_terusrag',
        FORM: '.rag-form',
        QUERY_INPUT: '[data-region="query-input"]',
        SUBMIT_BUTTON: '[data-action="submit-query"]',
        RESPONSE_AREA: '[data-region="response-area"]',
        RESPONSE_CONTENT: '[data-region="response-content"]',
        RESPONSE_TEXT: '[data-region="response-text"]',
        RESPONSE_METADATA: '[data-region="response-metadata"]',
        LOADING_INDICATOR: '[data-region="loading-indicator"]',
        PLACEHOLDER: '[data-region="placeholder"]'
    };

    /**
     * Initialize the module.
     *
     * @param {string} selector The CSS selector for the RAG block
     */
    RAGInterface.init = function(selector) {
        const container = document.querySelector(selector);
        if (!container) {
            return;
        }

        const form = container.querySelector(SELECTORS.FORM);
        

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const query = container.querySelector(SELECTORS.QUERY_INPUT).value.trim();
            if (query) {
                RAGInterface.submitQuery(container, query);
            }
        });
    };

    /**
     * Submit a query to the RAG system.
     *
     * @param {Element} container The RAG block container element
     * @param {string} query The user's query
     */
    RAGInterface.submitQuery = function(container, query) {
        const courseId = container.dataset.courseid;
        const loadingIndicator = container.querySelector(SELECTORS.LOADING_INDICATOR);
        const responseContent = container.querySelector(SELECTORS.RESPONSE_CONTENT);
        const responseText = container.querySelector(SELECTORS.RESPONSE_TEXT);
        const responseMetadata = container.querySelector(SELECTORS.RESPONSE_METADATA);
        // Store a direct reference to the input field
        const queryInputField = container.querySelector(SELECTORS.QUERY_INPUT);
        const contentPlaceholder = container.querySelector(SELECTORS.PLACEHOLDER);

        // Debug log
        console.log('Query input found:', queryInputField);

        // Show loading indicator
        loadingIndicator.classList.remove('hidden');
        contentPlaceholder.classList.add('hidden');
        // Clear previous results
        responseText.textContent = '';
        responseMetadata.textContent = '';
        responseContent.classList.remove('hidden');

        // Make AJAX call
        Ajax.call([{
            methodname: 'block_terusrag_submit_query',
            args: {
                query: query,
                courseid: courseId
            },
            done: function(response) {
                // Clear the input field after successful submission
                if (queryInputField) {
                    queryInputField.value = '';
                    console.log('Input field cleared');
                } else {
                    console.error('Query input field not found for clearing');
                }
                
                loadingIndicator.classList.add('hidden');
                // Check if answer exists and is an array
                if (response.answer && Array.isArray(response.answer)) {
                    // Display response with proper structure
                    responseText.innerHTML = response.answer.map(function(item) {
                        let content = `
                            <div class="rag-response-item mb-3">
                                <h4 class="rag-response-title">
                                    <a href="${item.viewurl}" target="_blank">${item.title}</a>
                                </h4>
                                <div class="rag-response-content" data-content-id="${item.id}">
                                    ${item.content}
                                </div>
                            </div>
                        `;
                        return content;
                    }).join('');
                } else {
                    responseText.innerHTML = '<p>'+Str.get_string('noresultsfound', 'block_terusrag')+'</p>';
                }

                // Show metadata
                responseMetadata.classList.remove('hidden'); // Ensure metadata container is visible
                
                // Format and display token usage information
                if (response.promptTokenCount !== undefined && 
                    response.responseTokenCount !== undefined && 
                    response.totalTokenCount !== undefined) {
                    
                    Str.get_string('token_usage', 'block_terusrag', {
                        prompt: response.promptTokenCount,
                        response: response.responseTokenCount,
                        total: response.totalTokenCount
                    }).then(function(str) {
                        responseMetadata.innerHTML = str;
                        return;
                    }).catch(Notification.exception);
                } else {
                    responseMetadata.innerHTML = '<div class="token-info">'+Str.get_string('notokeninformation', 'block_terusrag')+'</div>';
                }
            },
            fail: function(error) {
                console.error('Query submission failed:', error);
                Notification.exception(error);
            }
        }]);
    };

    return RAGInterface;
});
