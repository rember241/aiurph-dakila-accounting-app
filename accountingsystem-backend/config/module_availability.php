<?php

return [
    'profile' => 'free_accounting',

    'cheque_disbursements_enabled' => false,

    // Users and IdentityAccess still load as infrastructure for company sessions.
    'enabled' => [
        'identityaccess',
        'accounting',
        'journal',
        'period',
        'roles',
    ],

    'supported' => [
        'identityaccess',
        'accounting',
        'customers',
        'sales',
        'expenses',
        'inventory',
        'users',
        'dashboard_reports',
        'journal',
        'period',
        'roles',
        'org_units',
        'settings',
    ],
];
