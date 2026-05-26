<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ai' => [
        // Pilih provider: gemini | groq | openrouter | openai | ollama | custom
        // gemini direkomendasikan: gratis 1500 req/hari, akurasi tinggi, Bahasa Indonesia bagus
        'provider' => env('AI_PROVIDER', 'gemini'),
        'timeout' => (int) env('AI_TIMEOUT', 45),

        // ---- Google Gemini (gratis, paling direkomendasikan) ----
        // Daftar: https://aistudio.google.com/apikey
        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash-lite'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        ],

        // ---- Groq (gratis, sangat cepat, OpenAI-compatible) ----
        // Daftar: https://console.groq.com/keys
        'groq' => [
            'key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        ],

        // ---- OpenRouter (banyak model gratis: deepseek, llama, dll) ----
        // Daftar: https://openrouter.ai/keys
        'openrouter' => [
            'key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'deepseek/deepseek-chat-v3-0324:free'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
        ],

        // ---- OpenAI berbayar (atau provider OpenAI-compatible) ----
        'openai' => [
            'key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        ],

        // ---- Ollama lokal (offline, 100% gratis selamanya) ----
        // jalankan: `ollama serve` lalu `ollama pull llama3.1`
        'ollama' => [
            'key' => env('OLLAMA_API_KEY', 'ollama'),
            'model' => env('OLLAMA_MODEL', 'llama3.1'),
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434/v1'),
        ],

        // ---- Custom endpoint kompatibel OpenAI ----
        'custom' => [
            'key' => env('AI_CUSTOM_KEY'),
            'model' => env('AI_CUSTOM_MODEL'),
            'base_url' => env('AI_CUSTOM_BASE_URL'),
        ],
    ],

];
