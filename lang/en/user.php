<?php

return [

    'label'            => 'user',
    'plural_label'     => 'users',
    'navigation_label' => 'Users',

    'sections' => [
        'account' => 'Account',
    ],

    'fields' => [
        'name'                  => 'Name',
        'role'                  => 'Role',
        'email'                 => 'Email address',
        'phone'                 => 'Phone',
        'password'              => 'Password',
        'password_confirmation' => 'Confirm password',
        'email_verified_at'     => 'Verified at',
        'created_at'            => 'Created at',
        'updated_at'            => 'Updated at',
    ],

    'roles' => [
        'admin'  => 'Administrator',
        'client' => 'Client',
    ],

    'helpers' => [
        'password'              => 'Leave empty to keep the current password.',
        'password_requirements' => 'Must be at least 12 characters long, with upper and lower case letters and numbers.',
    ],

    'actions' => [
        'generate_password'        => 'Generate password',
        'password_generated_title' => 'Password generated',
        'password_generated_body'  => 'The password has been copied to your clipboard.',
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
