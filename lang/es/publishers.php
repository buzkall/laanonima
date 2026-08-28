<?php

return [

    'resource' => [
        'label'            => 'Editorial',
        'plural_label'     => 'Editoriales',
        'navigation_label' => 'Editoriales',
        'navigation_group' => 'Catálogo',
    ],

    'sections' => [
        'identification' => 'Identificación',
        'presentation'   => 'Presentación',
        'logo'           => 'Logotipo',
    ],

    'fields' => [
        'name'        => 'Nombre',
        'slug'        => 'URL',
        'website'     => 'Sitio web',
        'description' => 'Descripción',
        'logo_path'   => 'Logotipo',
        'books_count' => 'Libros',
        'created_at'  => 'Alta',
        'updated_at'  => 'Última modificación',
    ],

    'hints' => [
        'slug' => 'Se genera a partir del nombre si lo dejas en blanco.',
    ],

    'filters' => [
        'with_books' => 'Con libros en catálogo',
    ],

];
