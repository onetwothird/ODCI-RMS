<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legacy project root
    |--------------------------------------------------------------------------
    |
    | The Vanilla PHP application being migrated. It is READ-ONLY reference
    | material and is never written to.
    |
    | Uploaded files (profile pictures, documents, post media) must keep
    | resolving exactly where they already are on disk, so Laravel reads
    | them from here instead of moving them.
    |
    */

    'legacy_root' => env('ODCI_LEGACY_ROOT', 'C:\\laragon\\www\\ODCI'),

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Directory name inside the legacy root that holds uploaded files.
    | Paths stored in the DB are relative to this directory, e.g.
    | "uploads/profiles/profile_27_1791081185.png".
    |
    */

    'uploads_dir' => 'uploads',

    /*
    |--------------------------------------------------------------------------
    | System user
    |--------------------------------------------------------------------------
    |
    | When a user is deleted, the legacy code reassigns their files and
    | folders to this user id (hardcoded as 1 in users-script.php).
    | Preserved as-is so ownership history matches the original data.
    |
    */

    'system_user_id' => 1,

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | Page sizes copied from the legacy scripts.
    |
    */

    'per_page' => [
        'users' => 20,
        'departments' => 12,
    ],

];