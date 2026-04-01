<?php
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
 * Strings for component 'block_terusrag', language 'en'
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @author     Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['aiprovider'] = 'AI Provider';
$string['aiprovider_desc'] = 'Select the AI provider to use for generating responses';
$string['askbutton'] = 'Ask';
$string['blocktitle'] = 'Block title';
$string['blocktitle_help'] = 'The title that appears at the top of the Terus RAG block';
$string['chunk_not_indexed'] = 'Content source not found in the index.';
$string['datainitializer'] = 'Data Initializer';
$string['gemini_api_key'] = 'Gemini API Key';
$string['gemini_api_key_desc'] = 'Enter your Gemini API key from Google AI Studio';
$string['gemini_endpoint'] = 'Gemini API Endpoint';
$string['gemini_endpoint_desc'] = 'The base URL for Gemini API requests';
$string['gemini_model_chat'] = 'Chat Model';
$string['gemini_model_chat_desc'] = 'Select the Gemini model to use for chat interactions';
$string['gemini_model_embedding'] = 'Embedding Model';
$string['gemini_model_embedding_desc'] = 'Select the model to use for generating embeddings';
$string['geminisettings'] = 'Gemini API Settings';
$string['geminisettings_desc'] = 'Configure the settings for Google Gemini API integration';
$string['general_error'] = 'An error occurred while processing your request. Please try again.';
$string['no_data_initialized'] = 'The content index is empty. Please run the \'Data Initializer\' scheduled task to index course content before querying.';
$string['noresultsfound'] = 'No results found';
$string['noresultsfound_detail'] = 'No relevant results were found. Try rephrasing your question or ensure the content has been indexed.';
$string['notokeninformation'] = 'No token information available';
$string['ollama_api_key'] = 'Ollama API Key';
$string['ollama_api_key_desc'] = 'Enter your Ollama API key';
$string['ollama_endpoint'] = 'Ollama API Endpoint';
$string['ollama_endpoint_desc'] = 'The base URL for Ollama API requests';
$string['ollama_model_chat'] = 'Chat Model';
$string['ollama_model_chat_desc'] = 'Select the Ollama model to use for chat interactions';
$string['ollama_model_embedding'] = 'Embedding Model';
$string['ollama_model_embedding_desc'] = 'Select the model to use for generating embeddings';
$string['ollamasettings'] = 'Ollama Settings';
$string['ollamasettings_desc'] = 'Configure the settings for Ollama integration';
$string['openai_api_key'] = 'OpenAI API Key';
$string['openai_api_key_desc'] = 'Enter your OpenAI API key';
$string['openai_endpoint'] = 'OpenAI API Endpoint';
$string['openai_endpoint_desc'] = 'The base URL for OpenAI API requests';
$string['openai_model_chat'] = 'Chat Model';
$string['openai_model_chat_desc'] = 'Select the OpenAI model to use for chat interactions';
$string['openai_model_embedding'] = 'Embedding Model';
$string['openai_model_embedding_desc'] = 'Select the model to use for generating embeddings';
$string['openaisettings'] = 'OpenAI Settings';
$string['openaisettings_desc'] = 'Configure the settings for OpenAI integration';
$string['optimizeprompt'] = 'Prompt Optimization';
$string['optimizeprompt_desc'] = 'Optimize the system prompt for better AI responses';
$string['pluginname'] = 'Terus RAG';
$string['promptsettings'] = 'Prompt Settings';
$string['promptsettings_desc'] = 'Configure system prompts';
$string['queryplaceholder'] = 'Type your question here...';
$string['responseplaceholder'] = 'Ask a question to get started';
$string['stopwords_not_found'] = 'Stop words file not found';
$string['system_prompt'] = 'System Prompt';
$string['system_prompt_default'] = 'You are a Moodle assistant specialised in answering questions about course materials.

CRITICAL: You MUST follow this response format EXACTLY for every line of your answer:
[chunk_id] Your answer text here

RULES:
1. ALWAYS prefix each answer line with the chunk ID in square brackets: [ID]
2. Use the numeric ID from the corresponding context entry provided below
3. One answer line per relevant chunk — separate with a blank line if needed
4. If no relevant context is available, respond with: [0] I could not find relevant information in the course materials.
5. Do NOT output token counts, usage statistics, or any text outside this format

EXAMPLE CORRECT FORMAT:
[1] The course syllabus states that assignments are due every Friday by 23:59.
[3] Chapter 2 of the textbook covers this topic in detail.

Use ONLY the provided context to answer. If the information is not present, say so with the [0] prefix.';
$string['system_prompt_desc'] = 'Base system prompt for RAG responses (do not remove [the context id] from the prompt)';
$string['terusrag:addinstance'] = 'Add a new Terus RAG block';
$string['terusrag:managesettings'] = 'Manage Terus RAG settings';
$string['terusrag:myaddinstance'] = 'Add a new Terus RAG block to the My Moodle page';
$string['token_usage'] = 'Token usage — Prompt: {$a->prompt}, Response: {$a->response}, Total: {$a->total}';
$string['unknowncourse'] = 'Unknown course';
$string['vector_database'] = 'Vector Database Type';
$string['vector_database_desc'] = 'Choose which vector database to use for storing embeddings';
$string['vectordb_chromadb'] = 'ChromaDB';
$string['vectordb_flatfile'] = 'Moodle DB (Simple)';
$string['vectordb_host'] = 'Database Host';
$string['vectordb_host_desc'] = 'The hostname where your vector database is running';
$string['vectordb_password'] = 'Database Password';
$string['vectordb_password_desc'] = 'Password for authenticating with the vector database';
$string['vectordb_port'] = 'Database Port';
$string['vectordb_port_desc'] = 'The port number for connecting to the vector database';
$string['vectordb_supabase'] = 'Supabase';
$string['vectordb_username'] = 'Database Username';
$string['vectordb_username_desc'] = 'Username for authenticating with the vector database';
$string['vectordbsettings'] = 'Vector Database Settings';
$string['vectordbsettings_desc'] = 'Configure the vector database backend';
