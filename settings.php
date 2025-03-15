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
 * Settings for terusrag block
 *
 * @package    block_terusrag
 * @copyright  2025 Terus e-Learning
 * @author     Khairu Aqsara <khairu@teruselearning.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$geminimodels = [
    'gemini-2.0-flash' => 'Gemini 2.0 Flash',
    'gemini-2.0-flash-lite' => 'Gemini 2.0 Flash-Lite',
    'gemini-1.5-flash' => 'Gemini 1.5 Flash',
    'gemini-1.5-flash-8b' => 'Gemini 1.5 Flash 8B',
    'gemini-1.5-pro' => 'Gemini 1.5 Pro',
];

$geminiembeddingmodels = [
    'text-embedding-004' => 'text-embedding-004',
];

$openaimodels = [
    'gpt-4' => 'GPT-4',
    'gpt-4o' => 'GPT-4o',
    'gpt-4-turbo' => 'GPT-4 Turbo',
    'gtp-4o-mini' => 'GPT-4o Mini',
    'gpt-3.5-turbo' => 'GPT-3.5 Turbo',
];
$openaiembeddingmodels = [
    'text-embedding-3-large' => 'text-embedding-3-large',
    'text-embedding-3-small' => 'text-embedding-3-small',
    'text-embedding-ada-002' => 'text-embedding-ada-002',
];

$availableai = [
    'gemini' => 'Google Gemini',
    'openai' => 'OpenAI',
];

if ($hassiteconfig) {

    $settings->add(new admin_setting_configselect('block_terusrag/aiprovider',
        get_string('aiprovider', 'block_terusrag'),
        get_string('aiprovider_desc', 'block_terusrag'),
        'gemini',
        $availableai));

    // Gemini AI Settings.
    $settings->add(new admin_setting_heading('block_terusrag/geminisettings',
        get_string('geminisettings', 'block_terusrag'),
        get_string('geminisettings_desc', 'block_terusrag')));

    $settings->add(new admin_setting_configtext('block_terusrag/gemini_api_key',
        get_string('gemini_api_key', 'block_terusrag'),
        get_string('gemini_api_key_desc', 'block_terusrag'),
        '',
        PARAM_TEXT));
    $settings->hide_if('block_terusrag/gemini_api_key', 'block_terusrag/aiprovider', 'eq', 'openai');

    $settings->add(new admin_setting_configtext('block_terusrag/gemini_endpoint',
        get_string('gemini_endpoint', 'block_terusrag'),
        get_string('gemini_endpoint_desc', 'block_terusrag'),
        'https://generativelanguage.googleapis.com',
        PARAM_URL));
    $settings->hide_if('block_terusrag/gemini_endpoint', 'block_terusrag/aiprovider', 'eq', 'openai');

    $settings->add(new admin_setting_configselect('block_terusrag/gemini_model_chat',
        get_string('gemini_model_chat', 'block_terusrag'),
        get_string('gemini_model_chat_desc', 'block_terusrag'),
        'gemini-2.0-flash',
        $geminimodels));
    $settings->hide_if('block_terusrag/gemini_model_chat', 'block_terusrag/aiprovider', 'eq', 'openai');

    $settings->add(new admin_setting_configselect('block_terusrag/gemini_model_embedding',
        get_string('gemini_model_embedding', 'block_terusrag'),
        get_string('gemini_model_embedding_desc', 'block_terusrag'),
        'text-embedding-004',
        $geminiembeddingmodels));
    $settings->hide_if('block_terusrag/gemini_model_embedding', 'block_terusrag/aiprovider', 'eq', 'openai');

    // OpenAI Settings.
    $settings->add(new admin_setting_heading('block_terusrag/openaisettings',
        get_string('openaisettings', 'block_terusrag'),
        get_string('openaisettings_desc', 'block_terusrag')));

    $settings->add(new admin_setting_configtext('block_terusrag/openai_api_key',
        get_string('openai_api_key', 'block_terusrag'),
        get_string('openai_api_key_desc', 'block_terusrag'),
        '',
        PARAM_TEXT));
    $settings->hide_if('block_terusrag/openai_api_key', 'block_terusrag/aiprovider', 'eq', 'gemini');

    $settings->add(new admin_setting_configtext('block_terusrag/openai_endpoint',
        get_string('openai_endpoint', 'block_terusrag'),
        get_string('openai_endpoint_desc', 'block_terusrag'),
        'https://api.openai.com/v1',
        PARAM_URL));
    $settings->hide_if('block_terusrag/openai_endpoint', 'block_terusrag/aiprovider', 'eq', 'gemini');

    $settings->add(new admin_setting_configselect('block_terusrag/openai_model_chat',
        get_string('openai_model_chat', 'block_terusrag'),
        get_string('openai_model_chat_desc', 'block_terusrag'),
        'gpt-4',
        $openaimodels));
    $settings->hide_if('block_terusrag/openai_model_chat', 'block_terusrag/aiprovider', 'eq', 'gemini');

    $settings->add(new admin_setting_configselect('block_terusrag/openai_model_embedding',
        get_string('openai_model_embedding', 'block_terusrag'),
        get_string('openai_model_embedding_desc', 'block_terusrag'),
        'text-embedding-3-small',
        $openaiembeddingmodels));
    $settings->hide_if('block_terusrag/openai_model_embedding', 'block_terusrag/aiprovider', 'eq', 'gemini');

    // End of OpenAI Settings.

    // Vector Database Settings.
    $settings->add(new admin_setting_heading('block_terusrag/vectordbsettings',
        get_string('vectordbsettings', 'block_terusrag'),
        get_string('vectordbsettings_desc', 'block_terusrag')));

    $settings->add(new admin_setting_configselect('block_terusrag/vector_database',
        get_string('vector_database', 'block_terusrag'),
        get_string('vector_database_desc', 'block_terusrag'),
        'flatfile',
        [
            'flatfile' => get_string('vectordb_flatfile', 'block_terusrag'),
            'chromadb' => get_string('vectordb_chromadb', 'block_terusrag'),
            'supabase' => get_string('vectordb_supabase', 'block_terusrag'),
        ]));

    $settings->add(new admin_setting_configtext('block_terusrag/vectordb_host',
        get_string('vectordb_host', 'block_terusrag'),
        get_string('vectordb_host_desc', 'block_terusrag'),
        'localhost',
        PARAM_TEXT));
    $settings->hide_if('block_terusrag/vectordb_host', 'block_terusrag/vector_database', 'eq', 'flatfile');

    $settings->add(new admin_setting_configtext('block_terusrag/vectordb_port',
        get_string('vectordb_port', 'block_terusrag'),
        get_string('vectordb_port_desc', 'block_terusrag'),
        '8000',
        PARAM_INT));
    $settings->hide_if('block_terusrag/vectordb_port', 'block_terusrag/vector_database', 'eq', 'flatfile');

    $settings->add(new admin_setting_configtext('block_terusrag/vectordb_username',
        get_string('vectordb_username', 'block_terusrag'),
        get_string('vectordb_username_desc', 'block_terusrag'),
        '',
        PARAM_TEXT));
    $settings->hide_if('block_terusrag/vectordb_username', 'block_terusrag/vector_database', 'eq', 'flatfile');

    $settings->add(new admin_setting_configpasswordunmask('block_terusrag/vectordb_password',
        get_string('vectordb_password', 'block_terusrag'),
        get_string('vectordb_password_desc', 'block_terusrag'),
        ''));
    $settings->hide_if('block_terusrag/vectordb_password', 'block_terusrag/vector_database', 'eq', 'flatfile');

    $settings->add(new admin_setting_heading('block_terusrag/promptsettings',
        get_string('promptsettings', 'block_terusrag'),
        get_string('promptsettings_desc', 'block_terusrag')));

    $settings->add(new admin_setting_configtextarea('block_terusrag/system_prompt',
        get_string('system_prompt', 'block_terusrag'),
        get_string('system_prompt_desc', 'block_terusrag'),
        get_string('system_prompt_default', 'block_terusrag'),
        PARAM_TEXT));
}
