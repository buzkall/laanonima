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
        'logo'        => 'Logotipo',
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

    'merge' => [
        'label'         => 'Fusionar',
        'heading'       => 'Fusionar editoriales en :name',
        'description'   => 'Los libros de las editoriales que elijas pasarán a :name y esas editoriales se eliminarán.',
        'submit'        => 'Fusionar',
        'absorbed'      => 'Editoriales que se absorberán',
        'absorbed_hint' => 'Busca por nombre. Entre paréntesis, los libros que cambiarían de editorial.',
        'option'        => '{0} :name (sin libros)|{1} :name (1 libro)|[2,*] :name (:count libros)',
        'done'          => 'Editoriales fusionadas',
        'moved'         => '{0} No había libros que reasignar a :name.|{1} 1 libro reasignado a :name.|[2,*] :count libros reasignados a :name.',
    ],

    'logo_fetch' => [
        'label'               => 'Buscar logotipo',
        'heading'             => 'Buscar el logotipo de :name',
        'replace_description' => 'Esta editorial ya tiene logotipo. Si se encuentra otro, sustituirá al actual; si no, se conserva.',
        'submit'              => 'Buscar',
        'done_title'          => 'Logotipo importado',
        'done_wikidata'       => 'Tomado de Wikidata.',
        'done_website'        => 'Tomado de la web de la editorial.',
        'website_filled'      => 'También se ha rellenado su sitio web.',
        'missing_title'       => 'No se ha encontrado logotipo',
        'missing_body'        => 'Ni Wikidata ni su sitio web tienen uno que se pueda usar. Puedes subirlo a mano.',
    ],

    'filters' => [
        'with_books' => 'Con libros en catálogo',
    ],

];
