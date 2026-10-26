<?php

declare(strict_types=1);

return [
    'url' => env('OPENAI_URL', 'https://9r.fasekita.my.id/v1'),
    'model' => env('AI_MODEL', 'ag/gemini-3.8-flash-high'),
    'key' => env('OPENAI_API_KEY'),
];
