<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Maximum requests per minute. "auth" covers login and register; "api"
    | covers every other API route, keyed by the authenticated user.
    |
    */

    'rate_limits' => [
        'auth' => (int) env('API_AUTH_RATE_LIMIT', 10),
        'api' => (int) env('API_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Default and maximum page size accepted through the "per_page" query
    | parameter on paginated endpoints.
    |
    */

    'pagination' => [
        'default_per_page' => (int) env('API_DEFAULT_PER_PAGE', 15),
        'max_per_page' => (int) env('API_MAX_PER_PAGE', 100),
    ],

];
