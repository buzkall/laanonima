<?php

return [

    'resource' => [
        'label'            => 'Publisher',
        'plural_label'     => 'Publishers',
        'navigation_label' => 'Publishers',
        'navigation_group' => 'Catalog',
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

    'relations' => [
        'books' => 'Books in the catalog',
    ],

    'merge' => [
        'label'         => 'Merge',
        'heading'       => 'Merge publishers into :name',
        'description'   => 'The books of the publishers you pick move to :name, and those publishers are deleted.',
        'submit'        => 'Merge',
        'absorbed'      => 'Publishers to absorb',
        'absorbed_hint' => 'Search by name. In brackets, the books that would change publisher.',
        'option'        => '{0} :name (no books)|{1} :name (1 book)|[2,*] :name (:count books)',
        'done'          => 'Publishers merged',
        'moved'         => '{0} There were no books to move to :name.|{1} 1 book moved to :name.|[2,*] :count books moved to :name.',
    ],

    'logo_fetch' => [
        'label'               => 'Find logotype',
        'heading'             => 'Find the logotype of :name',
        'replace_description' => 'This publisher already has a logotype. If another one is found it replaces it; otherwise it is kept.',
        'submit'              => 'Find',
        'done_title'          => 'Logotype imported',
        'done_wikidata'       => 'Taken from Wikidata.',
        'done_website'        => "Taken from the publisher's website.",
        'website_filled'      => 'Its website has been filled in too.',
        'missing_title'       => 'No logotype found',
        'missing_body'        => 'Neither Wikidata nor its website has a usable one. You can upload it by hand.',
    ],

    'filters' => [
        'with_books' => 'With books in the catalog',
    ],

];
