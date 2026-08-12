<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Per Page
    |--------------------------------------------------------------------------
    |
    | The default number of records returned per page for cursor-paginated
    | API endpoints when the client doesn't specify a per_page value.
    |
    */

    'default_per_page' => (int) env('PAGINATION_DEFAULT_PER_PAGE', 15),

];
