<?php

return [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('AI_AGENCY_MODEL', 'gpt-5.6-luna'),
    'monthly_budget_eur' => (float) env('AI_AGENCY_MONTHLY_BUDGET_EUR', 10),
    'input_eur_per_million' => (float) env('AI_AGENCY_INPUT_EUR_PER_MILLION', 1),
    'output_eur_per_million' => (float) env('AI_AGENCY_OUTPUT_EUR_PER_MILLION', 6),
    'web_search_eur_per_call' => (float) env('AI_AGENCY_WEB_SEARCH_EUR_PER_CALL', 0.025),
    'max_output_tokens' => (int) env('AI_AGENCY_MAX_OUTPUT_TOKENS', 5000),
    'operation_max_output_tokens' => (int) env('AI_AGENCY_OPERATION_MAX_OUTPUT_TOKENS', 3000),
];
