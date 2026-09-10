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
        'portrait'    => 'Fotografía',
        'updated_at'  => 'Última modificación',
    ],

    'hints' => [
        'portrait' => 'Se muestra en la página pública del autor/a. Si La Cupida encuentra una foto en Wikimedia Commons la archiva aquí sola.',
        'slug'     => 'Se genera a partir del nombre si lo dejas en blanco.',
    ],

    'sections' => [
        'portrait' => 'Fotografía',
    ],

    'relations' => [
        'books' => 'Libros en catálogo',
    ],

    'filters' => [
        'with_books' => 'Con libros en catálogo',
    ],

];
