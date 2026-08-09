<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The parent host
    |--------------------------------------------------------------------------
    |
    | Everything that is not a known mini-store host is the parent store. The
    | resolver compares against this to decide whether a request arrived at a
    | mini store by subdomain, so it must be the bare registrable host with no
    | scheme, port or leading dot.
    |
    */

    'central_domain' => env('TENANCY_CENTRAL_DOMAIN', 'vikyplus.ir'),

    /*
    | Hosts that are always the parent store even if they look like a
    | subdomain: the ones we run ourselves.
    */

    'reserved_subdomains' => ['www', 'api', 'admin', 'panel', 'cdn', 'static', 'mail', 'assets'],

    /*
    |--------------------------------------------------------------------------
    | Path fallbacks
    |--------------------------------------------------------------------------
    |
    | Subdomains are the cleanest option and the preferred one, but a path
    | always works — on localhost, behind a preview URL, and before DNS for a
    | new branch exists. Both resolve to the same tenant and the same routes.
    |
    */

    'vendor_path_prefix' => 's',
    'franchise_path_prefix' => 'f',

    /*
    |--------------------------------------------------------------------------
    | Mini-store isolation
    |--------------------------------------------------------------------------
    |
    | "No visible route back to the parent store needs to be exposed in the
    | user interface." Isolation is enforced in three places, and this is the
    | switch for the third:
    |
    |   1. the mini-store layout, which shares nothing with the parent's;
    |   2. the route group, which carries no parent routes at all;
    |   3. this response guard, which fails loudly in local and testing if a
    |      mini-store response ever contains a link to the parent host.
    |
    | The guard is not a security control — it is a tripwire, so that the day
    | someone drops a parent URL into a shared partial, a test says so instead
    | of a customer finding it.
    |
    */

    'assert_no_parent_links' => (bool) env('TENANCY_ASSERT_NO_PARENT_LINKS', true),

];
