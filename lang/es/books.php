<?php

return [

    'resource' => [
        'label'            => 'Libro',
        'plural_label'     => 'Libros',
        'navigation_label' => 'Libros',
        'navigation_group' => 'Catálogo',
    ],

    'author' => [
        'create' => 'Nuevo autor/a',
    ],

    'publisher' => [
        'label'        => 'Editorial',
        'plural_label' => 'Editoriales',
        'create'       => 'Nueva editorial',
    ],

    'sections' => [
        'identification' => 'Identificación',
        'record'         => 'Ficha',
        'edition'        => 'Edición',
        'physical'       => 'Características físicas',
        'content'        => 'Contenido',
        'commercial'     => 'Venta',
    ],

    'fields' => [
        'isbn13'                 => 'ISBN',
        'isbn10'                 => 'ISBN-10',
        'ean13'                  => 'EAN',
        'slug'                   => 'URL',
        'external_reference'     => 'Referencia interna',
        'title'                  => 'Título',
        'subtitle'               => 'Subtítulo',
        'original_title'         => 'Título original',
        'contributors'           => 'Autoría y colaboraciones',
        'contributor_name'       => 'Nombre',
        'contributor_role'       => 'Función',
        'authors_line'           => 'Autoría',
        'publisher_id'           => 'Editorial',
        'imprint'                => 'Sello',
        'collection_name'        => 'Colección',
        'collection_number'      => 'Número en la colección',
        'published_on'           => 'Fecha de publicación',
        'published_year'         => 'Año',
        'edition_number'         => 'Número de edición',
        'edition_statement'      => 'Mención de edición',
        'country_of_publication' => 'País de publicación',
        'city_of_publication'    => 'Ciudad de publicación',
        'legal_deposit'          => 'Depósito legal',
        'binding'                => 'Encuadernación',
        'pages'                  => 'Páginas',
        'height_mm'              => 'Alto (mm)',
        'width_mm'               => 'Ancho (mm)',
        'thickness_mm'           => 'Grosor (mm)',
        'weight_grams'           => 'Peso (g)',
        'measures'               => 'Medidas',
        'language'               => 'Idioma',
        'original_language'      => 'Idioma original',
        'subjects'               => 'Materias',
        'subject_scheme'         => 'Sistema',
        'subject_code'           => 'Código',
        'subject_heading'        => 'Materia',
        'synopsis'               => 'Sinopsis',
        'back_cover_text'        => 'Texto de contracubierta',
        'cover'                  => 'Cubierta',
        'covers'                 => 'Imágenes',
        'cover_source_url'       => 'Origen de la cubierta',
        'cover_color'            => 'Color de la cubierta',
        'price_cents'            => 'Precio venta público',
        'vat_rate'               => 'IVA (%)',
        'currency'               => 'Moneda',
        'stock'                  => 'Existencias',
        'availability'           => 'Disponibilidad',
        'is_featured'            => 'Destacado',
        'is_active'              => 'Visible en la web',
        'metadata_source'        => 'Origen de los datos',
        'metadata_synced_at'     => 'Datos actualizados el',
        'created_at'             => 'Alta',
        'updated_at'             => 'Última modificación',
    ],

    'hints' => [
        'isbn13'           => 'Escribe el ISBN y pulsa la lupa para rellenar la ficha automáticamente.',
        'price_cents'      => 'PVP, IVA incluido.',
        'slug'             => 'Se genera a partir del título si lo dejas en blanco.',
        'covers'           => 'La primera imagen es la cubierta. Arrastra para reordenarlas.',
        'cover_source_url' => 'Dirección de la imagen. Solo se aceptan estas fuentes: :hosts',
        'cover_color'      => 'Se toma de la cubierta solo mientras está vacío. Elígelo a mano y no se tocará más.',
    ],

    'lookup' => [
        'label'           => 'Buscar por ISBN',
        'found_title'     => 'Ficha encontrada',
        'found_body'      => 'Hemos rellenado los datos de :title. Revísalos antes de guardar.',
        'not_found_title' => 'No hemos encontrado este ISBN',
        'not_found_body'  => 'Ninguna de las fuentes consultadas tiene este libro. Rellena la ficha a mano.',
        'invalid_title'   => 'ISBN no válido',
        'invalid_body'    => 'Revisa el número: el dígito de control no cuadra.',

        'found_without_cover' => 'No hemos encontrado cubierta: descárgala desde una dirección o sube la imagen a mano.',
    ],

    'cover_download' => [
        'label'             => 'Descargar cubierta',
        'heading'           => 'Descargar la cubierta desde una dirección',
        'submit'            => 'Descargar',
        'done_title'        => 'Cubierta descargada',
        'deferred_title'    => 'Dirección guardada',
        'deferred_body'     => 'Descargaremos la cubierta al guardar la ficha.',
        'failed_after_save' => 'La fuente no ha servido una imagen que podamos usar. Pulsa «Descargar cubierta» para probar con otra dirección, o sube la imagen a mano.',
        'failed_title'      => 'No hemos podido descargar la imagen',
        'failed_body'       => 'Comprueba que la dirección apunta a una imagen de una fuente aceptada y que mide al menos :width × :height píxeles.',
    ],

    'cover_color' => [
        'reset'   => 'Recalcular desde la cubierta',
        'invalid' => 'Escribe un color en formato #rrggbb.',
    ],

    'filters' => [
        'featured' => 'Destacados',
        'active'   => 'Visibles en la web',
    ],

    'binding' => [
        'paperback'  => 'Rústica',
        'hardback'   => 'Tapa dura',
        'pocket'     => 'Bolsillo',
        'board_book' => 'Cartoné',
        'spiral'     => 'Espiral',
        'ebook'      => 'Libro electrónico',
        'audiobook'  => 'Audiolibro',
    ],

    'availability' => [
        'available'         => 'Disponible',
        'to_order'          => 'Bajo pedido',
        'out_of_stock'      => 'Agotado',
        'out_of_print'      => 'Descatalogado',
        'not_yet_published' => 'Aún no publicado',
    ],

    'language' => [
        'spa' => 'Castellano',
        'cat' => 'Catalán',
        'eus' => 'Euskera',
        'glg' => 'Gallego',
        'eng' => 'Inglés',
        'fra' => 'Francés',
        'por' => 'Portugués',
        'ita' => 'Italiano',
        'deu' => 'Alemán',
    ],

    'contributor_role' => [
        'author'       => 'Autoría',
        'translator'   => 'Traducción',
        'illustrator'  => 'Ilustración',
        'editor'       => 'Edición literaria',
        'foreword'     => 'Prólogo',
        'photographer' => 'Fotografía',
    ],

    'actions' => [
        'view_on_site' => 'Ver en la web',
    ],

    'public' => [
        'tagline'           => 'Librería · Madrid',
        'login'             => 'Entrar',
        'account'           => 'Tu cuenta',
        'buy'               => 'Comprar',
        'in_stock_note'     => 'disponible en la mesa de novedades',
        'out_of_stock_note' => 'agotado en tienda',
        'no_cover'          => 'Sin cubierta',
        'synopsis_kicker'   => 'De qué va',
        'object_kicker'     => 'El libro',
        'published_format'  => 'MMMM [de] YYYY',
        'measures'          => ':height × :width cm',
        'authors_kicker'    => 'Quién lo escribe',
        'also_by_them'      => 'De ellas y ellos también',
        'also_note'         => 'Si sacan algo nuevo, lo pedimos el primer día.',
        'publisher_kicker'  => 'Publicado por :publisher',
        'publisher_intro'   => 'Si este te gusta, del mismo sello solemos tener en mesa:',
        'footer_line'       => ':name · Librería independiente · Madrid',

        'shelf_back' => 'Ver toda la estantería',

        'author' => [
            'kicker' => 'Libros de',
            'intro'  => 'Todo lo que tenemos de :name en la librería. Si falta algo suyo, pídenoslo y lo encargamos.',
        ],

        'publisher' => [
            'kicker'  => 'Editorial',
            'intro'   => 'Lo que tenemos en la librería publicado por :publisher.',
            'empty'   => 'Ahora mismo no tenemos nada de :publisher en la web. Pregúntanos: casi siempre podemos encargarlo.',
            'website' => 'Su web',
            'all'     => 'Ver todo de :publisher',
        ],

        'shelf' => [
            'title'   => 'La estantería',
            'heading' => 'La estantería, a tamaño real',
            'intro'   => 'Los mismos libros que hay en la librería, cada uno con sus medidas de verdad. Coge uno, gíralo y míralo de cerca.',
        ],

        'home' => [
            'heading'      => 'Todo lo que hay hoy en la mesa',
            'intro'        => 'Esto es lo que tenemos en la librería ahora mismo. Lo que no esté aquí, casi siempre lo conseguimos en un par de días.',
            'count'        => '{1} :count libro en la estantería|[2,*] :count libros en la estantería',
            'featured'     => 'Recomendado',
            'in_stock'     => 'En la mesa',
            'out_of_stock' => 'Por encargo',
            'empty'        => 'Todavía no hemos subido ningún libro a la web. Pásate por la librería y te enseñamos lo que hay.',
            'prev'         => 'Anterior',
            'next'         => 'Siguiente',
        ],

        'in_stock' => [
            'heading' => 'Lo tenemos en la mesa de novedades.',
            'body'    => 'Pásate y hojéalo, o te lo guardamos con tu nombre en un papelito.',
            'cta'     => 'Guardádmelo',
            'subject' => 'Reservar :title',
        ],

        'out_of_stock' => [
            'heading' => '¿No lo tenemos en tienda? Pídenoslo.',
            'body'    => 'Lo encargamos y te avisamos cuando llegue. Suele tardar dos días.',
            'cta'     => 'Quiero que me lo encarguéis',
        ],
    ],

];
