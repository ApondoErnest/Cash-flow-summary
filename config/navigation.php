<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation preview role
    |--------------------------------------------------------------------------
    |
    | Used before authentication exists (Steps 22–31). Override with ?role=
    | query parameter: owner, manager, or cashier.
    |
    */
    'preview_role' => env('NAV_PREVIEW_ROLE', 'owner'),
];
