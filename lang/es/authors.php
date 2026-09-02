<?php

return [

    'resource' => [
        'label'            => 'Autor/a',
        'plural_label'     => 'Autores/as',
        'navigation_label' => 'Autores/as',
        'navigation_group' => 'Catálogo',
    ],

    'fields' => [
        'name'        => 'Nombre',
        'slug'        => 'URL',
        'bio'         => 'Biografía',
        'books_count' => 'Libros',
        'created_at'  => 'Alta',
        'updated_at'  => 'Última modificación',
    ],

    'hints' => [
        'slug' => 'Se genera a partir del nombre si lo dejas en blanco.',
    ],

    'relations' => [
        'books' => 'Libros en catálogo',
    ],

    'filters' => [
        'with_books' => 'Con libros en catálogo',
    ],

];
