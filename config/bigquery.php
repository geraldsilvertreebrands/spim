<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BigQuery Project ID
    |--------------------------------------------------------------------------
    |
    | The Google Cloud project ID where BigQuery is enabled.
    |
    */
    'project_id' => env('BIGQUERY_PROJECT_ID', 'silvertreepoc'),

    /*
    |--------------------------------------------------------------------------
    | BigQuery Dataset
    |--------------------------------------------------------------------------
    |
    | The default BigQuery dataset to query.
    |
    */
    'dataset' => env('BIGQUERY_DATASET', 'sh_output'),

    /*
    |--------------------------------------------------------------------------
    | Google Cloud Credentials Path
    |--------------------------------------------------------------------------
    |
    | The path to the Google Cloud service account JSON credentials file.
    | Store this file in the secrets/ directory which is gitignored.
    |
    */
    'credentials_path' => env('GOOGLE_APPLICATION_CREDENTIALS'),

    /*
    |--------------------------------------------------------------------------
    | Company ID
    |--------------------------------------------------------------------------
    |
    | The company ID to filter data by.
    | 3 = Faithful to Nature (FtN)
    | 5 = Pet Heaven (PH)
    | 9 = UCOOK
    |
    */
    'company_id' => (int) env('COMPANY_ID', 3),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | The time-to-live in seconds for cached BigQuery results.
    | Default: 900 seconds (15 minutes)
    |
    */
    'cache_ttl' => (int) env('BIGQUERY_CACHE_TTL', 900),

    /*
    |--------------------------------------------------------------------------
    | Query Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds to wait for a BigQuery query to complete.
    | Default: 30 seconds
    |
    */
    'timeout' => (int) env('BIGQUERY_TIMEOUT', 30),

];
