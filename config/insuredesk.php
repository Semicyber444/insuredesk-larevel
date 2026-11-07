<?php

return [
    // One-click demo accounts on the login page. Never enable in production.
    'demo_auth' => env('ENABLE_DEMO_AUTH', false),

    // What each role may do (Section 6). A user with several roles gets the union.
    'permissions' => [
        'viewer' => ['canSearch', 'canCompare', 'canViewPublished'],
        'editor' => ['canSearch', 'canCompare', 'canViewPublished', 'canEditDraft', 'canSubmitReview'],
        'reviewer' => ['canSearch', 'canCompare', 'canViewPublished', 'canReview', 'canPublish', 'canViewAudit'],
        'admin' => ['canSearch', 'canCompare', 'canViewPublished', 'canEditDraft', 'canSubmitReview', 'canReview',
            'canPublish', 'canManageUsers', 'canViewAudit'],
    ],
];
