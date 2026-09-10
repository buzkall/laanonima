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
        'portrait'    => 'Portrait',
        'updated_at'  => 'Last modified',
    ],

    'hints' => [
        'portrait' => 'Shown on the public author page. When La Cupida finds a photo on Wikimedia Commons it is filed here on its own.',
        'slug'     => 'Generated from the name if you leave it blank.',
    ],

    'sections' => [
        'portrait' => 'Portrait',
    ],

    'relations' => [
        'books' => 'Books in the catalog',
    ],

    'filters' => [
        'with_books' => 'With books in the catalog',
    ],

];
