<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | The counts on the admin dashboard. Each label is a whole, each line under
    | it the part of that whole a bookseller acts on.
    |
    */

    'catalog' => [
        'books'                 => 'Books',
        'books_online'          => '{0} none on the web|{1} :count on the web|[2,*] :count on the web',
        'authors'               => 'Authors',
        'authors_with_books'    => '{0} none with books|{1} :count with a book|[2,*] :count with books',
        'publishers'            => 'Publishers',
        'publishers_with_books' => '{0} none with books|{1} :count with a book|[2,*] :count with books',
        'subjects'              => 'Subjects',
        'subjects_scheme'       => 'of :count in the THEMA scheme',
        'requests'              => 'Requests',
        'requests_open'         => '{0} none still open|{1} :count still open|[2,*] :count still open',
        'recommendations'       => 'La Cupida',
        'recommendations_month' => '{0} none this month|{1} :count this month|[2,*] :count this month',
    ],

    'requests' => [
        'heading' => 'Latest requests',
        'all'     => 'See them all',
        'open'    => 'Open the request',
        'empty'   => 'Nobody has asked for anything yet.',
    ],

];
