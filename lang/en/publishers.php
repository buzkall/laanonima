<?php

return [

    'resource' => [
        'label'            => 'Publisher',
        'plural_label'     => 'Publishers',
        'navigation_label' => 'Publishers',
        'navigation_group' => 'Catalogue',
    ],

    'sections' => [
        'identification' => 'Identification',
        'presentation'   => 'Presentation',
        'logo'           => 'Logotype',
    ],

    'fields' => [
        'name'        => 'Name',
        'slug'        => 'URL',
        'website'     => 'Website',
        'description' => 'Description',
        'logo'        => 'Logotype',
        'books_count' => 'Books',
        'created_at'  => 'Created',
        'updated_at'  => 'Last modified',
    ],

    'hints' => [
        'slug' => 'Generated from the name if you leave it blank.',
    ],

    'filters' => [
        'with_books' => 'With books in the catalogue',
    ],

];
