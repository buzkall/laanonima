<?php

return [

    'label'            => 'user',
    'plural_label'     => 'users',
    'navigation_label' => 'Users',

    'sections' => [
        'account' => 'Account',
    ],

    'fields' => [
        'name'              => 'Name',
        'role'              => 'Role',
        'email'             => 'Email address',
        'password'          => 'Password',
        'email_verified_at' => 'Verified at',
        'created_at'        => 'Created at',
        'updated_at'        => 'Updated at',
    ],

    'roles' => [
        'admin'  => 'Administrator',
        'client' => 'Client',
    ],

    'helpers' => [
        'password' => 'Leave empty to keep the current password.',
    ],

    'placeholders' => [
        'not_verified' => 'Not verified',
    ],

    'filters' => [
        'role' => 'Role',

        'email_verification' => [
            'label'      => 'Email verification',
            'all'        => 'All users',
            'verified'   => 'Verified users',
            'unverified' => 'Unverified users',
        ],
    ],

    'policy' => [
        'cannot_delete_self' => [
            'all'  => 'You cannot delete your own account.',
            'some' => ':count of the :total selected users is your own account.',
        ],
    ],

];
