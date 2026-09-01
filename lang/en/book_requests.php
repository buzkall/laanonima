<?php

return [

    'resource' => [
        'label'            => 'Book request',
        'plural_label'     => 'Book requests',
        'navigation_label' => 'Book requests',
        'navigation_group' => 'Catalogue',
    ],

    'sections' => [
        'book'     => 'What they are asking for',
        'reader'   => 'Who is asking',
        'handling' => 'Follow-up',
    ],

    'fields' => [
        'title'       => 'Title',
        'author'      => 'Author',
        'publisher'   => 'Publisher',
        'isbn'        => 'ISBN',
        'notes'       => 'Comments',
        'phone'       => 'Phone',
        'user_id'     => 'Client',
        'book_id'     => 'Book in the catalogue',
        'status'      => 'Status',
        'admin_notes' => 'Internal notes',
        'created_at'  => 'Received',
        'updated_at'  => 'Last modified',
    ],

    'hints' => [
        'book_id'     => 'Only if the request came from a record already on the web.',
        'user_id'     => 'A book is only asked for from an account, so there is always one.',
        'admin_notes' => 'Never sent to whoever made the request.',
    ],

    'status' => [
        'pending'     => 'Pending',
        'in_progress' => 'In progress',
        'obtained'    => 'Got it',
        'dropped'     => 'Dropped',
    ],

    'filters' => [
        'in_catalogue' => 'About a book in the catalogue',
        'mine'         => 'Still open',
    ],

    'mail' => [
        'received' => [
            'subject' => 'New book request: :title',
            'heading' => 'Somebody is asking us for a book',
            'intro'   => ':name has filled in the request form on the website.',
            'action'  => 'See the request',
        ],
        'withdrawn' => [
            'subject' => 'Book request withdrawn: :title',
            'heading' => 'A request has been called off',
            'intro'   => ':name no longer wants the book they asked us for. If you had ordered it, you may still be in time to stop it.',
            'action'  => 'See the request',
        ],
    ],

    'client' => [
        'navigation_label' => 'My book requests',
        'label'            => 'My book request',
        'plural_label'     => 'My book requests',
        'empty'            => 'You have not asked us for any book yet.',
        'empty_hint'       => 'Tell us what you are after and we will order it.',
        'ask'              => 'Ask for a book',
    ],

    'actions' => [
        'withdraw'             => 'Withdraw',
        'withdraw_heading'     => 'Call this request off?',
        'withdraw_description' => 'We stop looking for it and let the shop know. This cannot be undone.',
        'withdraw_confirm'     => 'Yes, call it off',
        'withdrawn_title'      => 'Book request withdrawn',
        'withdrawn_body'       => 'We have let the shop know.',
    ],

    'public' => [
        'kicker'       => 'Ask us for a book',
        'heading'      => 'Not on our shelves? We will get it.',
        'intro'        => 'Tell us what you are after and we will order it. We let you know the moment it reaches the shop, and asking costs you nothing.',
        'book_kicker'  => 'Order it',
        'book_intro'   => 'We order it from the distributor and let you know when it arrives. It usually takes two days.',
        'submit'       => 'Find me the book',
        'back'         => 'Back to the shelf',
        'required'     => 'A title is enough. Anything else you know saves us time.',
        'signed_in_as' => 'We will write to you at :email.',
        'phone_note'   => 'We keep it on your account so we can call if we need to.',
        'optional'     => 'optional',
        'sent'         => [
            'heading' => 'Noted. We are on it.',
            'body'    => 'We will write to you as soon as we know anything about “:title”.',
        ],
    ],

];
