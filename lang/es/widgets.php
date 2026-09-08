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

    'catalogue' => [
        'books'                 => 'Libros',
        'books_online'          => '{0} ninguno en la web|{1} :count en la web|[2,*] :count en la web',
        'authors'               => 'Autores/as',
        'authors_with_books'    => '{0} sin libros|{1} :count con libro|[2,*] :count con libros',
        'publishers'            => 'Editoriales',
        'publishers_with_books' => '{0} sin libros|{1} :count con libro|[2,*] :count con libros',
        'subjects'              => 'Materias',
        'subjects_scheme'       => 'de :count en el esquema THEMA',
        'requests'              => 'Solicitudes',
        'requests_open'         => '{0} ninguna sin cerrar|{1} :count sin cerrar|[2,*] :count sin cerrar',
        'recommendations'       => 'La Cupida',
        'recommendations_month' => '{0} ninguna este mes|{1} :count este mes|[2,*] :count este mes',
    ],

    'requests' => [
        'heading' => 'Últimas solicitudes',
        'all'     => 'Verlas todas',
        'open'    => 'Abrir la solicitud',
        'empty'   => 'Nadie ha pedido nada todavía.',
    ],

];
