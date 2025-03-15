# Terus Rag Block for Moodle

## Overview
This Moodle block plugin implements Retrieval-Augmented Generation (RAG) functionality, allowing users to query course content using large language models. The plugin integrates with Gemini API to provide intelligent responses based on your course data.

## Features
- Implements RAG (Retrieval-Augmented Generation) architecture
- Integrates with Google's Gemini API
- Supports vector embeddings for semantic search
- Uses hybrid ranking with BM25 and cosine similarity
- Automatically processes and indexes course content
- Proper capability management for adding block instances

## How It Works

```mermaid
graph TB
    subgraph Content Layer
        A[Course Materials] --> B[Content Extractor]
        B --> C[Text Chunker]
    end

    subgraph Embedding Layer
        C --> D[Gemini Embeddings]
        D --> E{Vector Storage}
        E -->|Simple| F[Moodle DB]
        E -->|Scalable| G[ChromaDB]
        E -->|Cloud| H[Supabase]
    end

    subgraph Query Layer
        I[User Query] --> J[Query Processor]
        J --> K[Embedding Generation]
        K --> L[Vector Search]
        F & G & H --> L
    end

    subgraph Response Layer
        L --> M[Context Assembly]
        M --> N[Gemini API]
        N --> O[Response Formatter]
        O --> P[UI Display]
    end

    style Content Layer fill:#e1f5fe,stroke:#01579b
    style Embedding Layer fill:#f3e5f5,stroke:#4a148c
    style Query Layer fill:#e8f5e9,stroke:#1b5e20
    style Response Layer fill:#fff3e0,stroke:#e65100
```

## Installation
1. Copy the terusrag folder into your Moodle blocks directory
2. Visit the notifications page (Site administration → Notifications) to complete the installation
3. Configure the API keys in the block settings
4. Add the block to your course or Dashboard

## Requirements
- Moodle 4.1.3 or later
- PHP 7.4 or later
- Access to Gemini API

## Configuration
1. Obtain a Gemini API key from Google AI Studio
2. Go to Site Administration → Plugins → Blocks → Terus RAG
3. Enter your API key and other required settings
4. Save changes and initialize the data process

## Core Files
- **provider_interface.php**: Interface defining LLM provider capabilities
- **gemini.php**: Implementation of the Gemini API integration
- **bm25.php**: BM25 ranking algorithm for text retrieval
- **llm.php**: Helper class with vector operations for LLM processing

## Capabilities
- block/terusrag:addinstance - Controls who can add the block to a course
- block/terusrag:myaddinstance - Controls who can add the block to their Dashboard

## Author
- Name: Khairu Aqsara
- Email: khairu@teruselearning.co.uk
- Website: https://teruselearning.co.uk


## License
This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
