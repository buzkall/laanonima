<?php

return [

    'title' => 'La Cupida',
    'intro' => 'Tres preguntas y te decimos qué leer. Di que sí a lo que te apetezca y que no a lo que no, y al final te damos un libro que está en la librería ahora mismo.',
    'nav'   => 'La Cupida',

    'start' => [
        'kicker'   => 'Librería · Madrid',
        'greeting' => 'Hola, :name',
        'guest'    => 'Anónima',
        'lead'     => 'Soy La Cupida de los libros.',
        'promise'  => 'Voy a hacerte 3 preguntas y te daré :match.',
        /* The whole phrase and not the noun on its own: flechazo, crush and
           match are masculine and cita is not, so a shared "tu próxima" is
           wrong three times in four. */
        'matches' => ['tu próxima cita', 'tu próximo flechazo', 'tu próximo crush', 'tu próximo match'],
        'button'  => 'Empezar',
    ],

    'questions' => [
        'theme'  => '¿Qué te apetece leer?',
        'author' => '¿Y con quién?',
        'mood'   => '¿Qué esperas del libro?',
    ],

    'kinds' => [
        'theme'  => 'Género',
        'author' => 'Autoría',
        'mood'   => 'Ánimo',
    ],

    'progress' => 'Pregunta :current de :total',

    /* The same counter with no room for words: it rides in the question's own
     row below `wide:`, where the label would cost the deck a line. */
    'progress_short' => ':current/:total',

    'cards' => [
        'stocked' => '{0} nada en la mesa|{1} :count libro en la librería|[2,*] :count libros en la librería',
    ],

    'moods' => [
        'heartbreak'  => 'Que me rompa el corazón',
        'laugh'       => 'Para reírme en el metro',
        'think'       => 'Que me haga pensar',
        'escape'      => 'Para irme muy lejos',
        'short'       => 'Algo corto, que voy justa de tiempo',
        'doorstop'    => 'Algo largo, que me dure semanas',
        'rage'        => 'Que me dé rabia',
        'comfort'     => 'Que me reconforte',
        'true_story'  => 'Que haya pasado de verdad',
        'strange'     => 'Algo raro, sorpréndeme',
        'love'        => 'Que me enamore',
        'mystery'     => 'Que me tenga en vilo',
        'fear'        => 'Que me dé un poco de miedo',
        'future'      => 'Que imagine otro mundo',
        'past'        => 'Que me lleve a otra época',
        'learn'       => 'Que me enseñe algo',
        'poetry'      => 'Que suene bonito',
        'women'       => 'Que hable de mujeres',
        'queer'       => 'Historias LGTBIQ+',
        'family'      => 'Que vaya de familias',
        'city'        => 'Que pase en una ciudad',
        'nature'      => 'Que me saque al campo',
        'slow'        => 'Algo tranquilo, sin prisa',
        'intense'     => 'Que no pueda soltarlo',
        'hope'        => 'Que me deje con esperanza',
        'dark'        => 'Que sea muy oscuro',
        'art'         => 'Que hable de arte o de música',
        'politics'    => 'Algo político',
        'body'        => 'Que hable del cuerpo',
        'mind'        => 'Que hable de la cabeza',
        'food'        => 'Que me dé hambre',
        'work'        => 'Que hable del trabajo',
        'identity'    => 'Que hable de las raíces',
        'latin'       => 'Voces de Latinoamérica',
        'world'       => 'De la otra punta del mundo',
        'illustrated' => 'Con dibujos',
        'classic'     => 'Un clásico de toda la vida',
    ],

    'swipe' => [
        'like' => 'Me gusta',
        'pass' => 'Paso',
        'help' => 'Arrastra la carta, o usa los botones y las flechas del teclado.',
    ],

    'thinking' => [
        'heading' => 'Buscando tu libro…',
        'line'    => 'Mirando en la estantería lo que encaja con lo que nos has dicho.',
    ],

    'result' => [
        /* Parallel to `start.matches` and picked by the same seed: the heading
           over the book says the same word the opening card promised. */
        'kickers'        => ['Tu cita', 'Tu flechazo', 'Tu crush', 'Tu match'],
        'heading'        => 'Creemos que es este',
        'by'             => 'de :author',
        'read_more'      => 'Ver el libro',
        'buy'            => 'Verlo en la librería',
        'at_the_shop'    => 'Verlo en la web de la librería',
        'more'           => 'Seguir leyendo',
        'synopsis'       => 'Sinopsis',
        'again'          => 'Otra vez',
        'fallback_pitch' => 'Este te lo damos a ojo, que es como se acierta algunas veces. Ábrelo por cualquier página y ya verás.',
    ],

    'empty' => [
        'heading' => 'La Cupida está cerrada un momento',
        'line'    => 'Todavía no tenemos las cartas preparadas. Vuelve en un rato.',
    ],

    'credit' => [
        'low' => [
            'subject'     => 'A La Cupida le queda poco saldo',
            'body'        => 'Según nuestras cuentas quedan :remaining en la cuenta de Anthropic.',
            'consequence' => 'Cuando se acabe, la página seguirá en pie y seguirá dando libros, pero dejará de escribir la recomendación: saldrá el texto de siempre.',
            'action'      => 'Ver La Cupida',
        ],

        'exhausted' => [
            'subject'     => 'La Cupida se ha quedado sin saldo',
            'body'        => 'Anthropic ha rechazado la última petición por falta de saldo.',
            'consequence' => 'La página sigue en pie y sigue dando libros, elegidos por nuestra propia puntuación, pero con el texto de siempre en vez de uno escrito.',
            'action'      => 'Ver La Cupida',
        ],
    ],

    'admin' => [
        'resource' => [
            'title'            => 'La Cupida',
            'label'            => 'Recomendación',
            'plural_label'     => 'Recomendaciones',
            'navigation_label' => 'La Cupida',
            'navigation_group' => 'Catálogo',
        ],

        'anonymous' => 'Sin cuenta',

        'fields' => [
            'created_at' => 'Cuándo',
            'book'       => 'Libro recomendado',
            'author'     => 'Autor/a',
            'cover'      => 'Portada',
            'likes'      => 'Le gustó',
            'passes'     => 'Pasó de',
            'pitch'      => 'Lo que le dijimos',
            'written'    => 'Escrita',
            'user'       => 'Lectora',
            'in_catalog' => 'En nuestro catálogo',
            'ean'        => 'EAN',
            'cost'       => 'Coste',
            'cost_total' => 'Total',
            'shortlist'  => 'Sin IA',
        ],

        'tokens' => ':input de entrada · :output de salida',

        'cost_none'    => 'Sin coste',
        'cost_unknown' => 'Sin tarifa',

        'written' => [
            'yes' => 'IA',
            'no'  => 'Código de toda la vida',
        ],

        'filters' => [
            'written'    => 'Escrita por la IA',
            'in_catalog' => 'En nuestro catálogo',
            'agreed'     => 'Coincidió con la lista',
            'sort'       => 'Ordenar por',
        ],

        'shortlist' => [
            'same'    => 'Sin IA habríamos dado este mismo',
            'instead' => 'Sin IA habríamos dado: :book',
        ],

        'export' => [
            'action'  => 'Descargar registro',
            'tooltip' => 'Un JSON con lo que hay en pantalla: las respuestas, el libro, la ficha que le escribimos y lo que costó. Pensado para dárselo a una IA y que te diga qué tal lo está haciendo.',
        ],

        'credit' => [
            'action'       => 'Saldo IA',
            'heading'      => 'Saldo de la cuenta',
            'description'  => 'Anthropic no publica cuánto queda, así que lo llevamos aquí: apunta lo que has recargado y vamos descontando lo que cuesta cada recomendación.',
            'balance'      => 'Saldo recargado',
            'balance_hint' => 'En dólares, que es como factura Anthropic.',
            'topped_up_at' => 'Fecha de la recarga',
            'remaining'    => 'Queda por gastar',
            'spent'        => 'Gastado desde la recarga',
            'unknown'      => 'Sin saldo apuntado',
            'save'         => 'Guardar',
            'saved'        => 'Apuntado. Avisamos cuando baje de :threshold.',
        ],

        'sort' => [
            'newest'     => 'Las más recientes',
            'oldest'     => 'Las más antiguas',
            'title_asc'  => 'Título (A-Z)',
            'title_desc' => 'Título (Z-A)',
            'cost_desc'  => 'Coste (de mayor a menor)',
            'rank_desc'  => 'Donde más se alejó de la lista',
            'cost_asc'   => 'Coste (de menor a mayor)',
        ],

        'prompt' => [
            'action'       => 'Prompt IA',
            'heading'      => 'Instrucciones para La Cupida',
            'description'  => 'Lo que añadas aquí se suma a las instrucciones de base. Sirve para decir cómo es La Cupida, y para la temporada, la mesa de novedades o quien quieras empujar este mes.',
            'base_heading' => 'Instrucciones de base',
            'base_hint'    => 'Esto no se toca desde aquí: es lo que impide que se invente libros. Se cambia en el código.',
            'extra'        => 'Instrucciones extra',
            'extra_hint'   => 'En español: cómo es La Cupida y qué quiere la librería que recomiende. Describe a la librera, no a quien lee: sus gustos inclinan la elección, pero nunca se cuentan como respuestas de quien está jugando. Déjalo vacío para no añadir nada.',
            'save'         => 'Guardar',
            'saved'        => 'Guardado. La próxima recomendación ya lo tiene en cuenta.',
        ],
    ],

    'portraits' => [
        /* Debajo del retrato, en la propia carta: quién la hizo, con qué
           licencia, y que la hemos recortado. */
        'credit'         => 'Foto: :artist · :license · recortada',
        'unknown_artist' => 'autoría desconocida',
    ],

];
