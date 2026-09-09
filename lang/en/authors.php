<?php

return [

    'resource' => [
        'label'            => 'Author',
        'plural_label'     => 'Authors',
        'navigation_label' => 'Authors',
        'navigation_group' => 'Catalog',
    ],

    'fields' => [
        'name'        => 'Name',
        'slug'        => 'URL',
        'bio'         => 'Biography',
        'books_count' => 'Books',
        'created_at'  => 'Created',
        'updated_at'  => 'Last modified',
    ],

    'hints' => [
        'slug' => 'Generated from the name if you leave it blank.',
    ],

    'relations' => [
        'books' => 'Books in the catalog',
    ],

    'filters' => [
        'with_books' => 'With books in the catalog',
    ],

];
