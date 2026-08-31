<?php

return [

    'resource' => [
        'label'            => 'Solicitud de libro',
        'plural_label'     => 'Solicitudes de libros',
        'navigation_label' => 'Solicitudes de libros',
        'navigation_group' => 'Catálogo',
    ],

    'sections' => [
        'book'     => 'Qué nos piden',
        'reader'   => 'Quién lo pide',
        'handling' => 'Seguimiento',
    ],

    'fields' => [
        'title'       => 'Título',
        'author'      => 'Autor o autora',
        'publisher'   => 'Editorial',
        'isbn'        => 'ISBN',
        'notes'       => 'Comentarios',
        'phone'       => 'Teléfono',
        'user_id'     => 'Cliente',
        'book_id'     => 'Libro del catálogo',
        'status'      => 'Estado',
        'admin_notes' => 'Notas internas',
        'created_at'  => 'Recibido',
        'updated_at'  => 'Última modificación',
    ],

    'hints' => [
        'book_id'     => 'Solo si la solicitud salió de una ficha que ya tenemos en la web.',
        'user_id'     => 'Solo se pide un libro con la cuenta abierta, así que siempre hay uno.',
        'admin_notes' => 'No se envían a quien hizo la solicitud.',
    ],

    'status' => [
        'pendiente'  => 'Pendiente',
        'en_curso'   => 'En curso',
        'conseguido' => 'Conseguido',
        'descartado' => 'Descartado',
    ],

    'filters' => [
        'in_catalogue' => 'Sobre un libro del catálogo',
        'mine'         => 'Solo las abiertas',
    ],

    'mail' => [
        'received' => [
            'subject' => 'Nueva solicitud de libro: :title',
            'heading' => 'Alguien nos pide un libro',
            'intro'   => ':name ha rellenado el formulario de solicitudes de la web.',
            'action'  => 'Ver la solicitud',
        ],
        'withdrawn' => [
            'subject' => 'Solicitud anulada: :title',
            'heading' => 'Han anulado una solicitud',
            'intro'   => ':name ya no quiere el libro que nos había pedido. Si lo habías encargado, quizá llegues a tiempo de pararlo.',
            'action'  => 'Ver la solicitud',
        ],
    ],

    'client' => [
        'navigation_label' => 'Mis solicitudes de libros',
        'label'            => 'Mi solicitud de libro',
        'plural_label'     => 'Mis solicitudes de libros',
        'empty'            => 'Todavía no nos has pedido ningún libro.',
        'empty_hint'       => 'Dinos qué buscas y lo encargamos.',
        'ask'              => 'Pedir un libro',
    ],

    'actions' => [
        'withdraw'             => 'Desistir',
        'withdraw_heading'     => '¿Anulamos esta solicitud?',
        'withdraw_description' => 'Dejaremos de buscarlo y avisaremos a la librería. No se puede deshacer.',
        'withdraw_confirm'     => 'Sí, anuladlo',
        'withdrawn_title'      => 'Solicitud anulada',
        'withdrawn_body'       => 'Hemos avisado a la librería.',
    ],

    'public' => [
        'kicker'       => 'Pídenos un libro',
        'heading'      => '¿No lo tenemos? Te lo conseguimos.',
        'intro'        => 'Dinos qué buscas y lo encargamos. Te avisamos en cuanto llegue a la librería, y no te cobramos nada por pedirlo.',
        'book_kicker'  => 'Encargar',
        'book_intro'   => 'Te lo encargamos a la distribuidora y te avisamos cuando llegue. Suele tardar dos días.',
        'submit'       => 'Encontradme el libro',
        'back'         => 'Volver a la estantería',
        'required'     => 'Con el título nos basta. Lo demás, si lo sabes, nos ahorra tiempo.',
        'signed_in_as' => 'Te escribiremos a :email.',
        'phone_note'   => 'Lo guardamos en tu cuenta para avisarte antes si hace falta.',
        'optional'     => 'opcional',
        'sent'         => [
            'heading' => 'Apuntado. Vamos a por él.',
            'body'    => 'Te escribiremos a tu correo en cuanto sepamos algo de «:title».',
        ],
        'placeholders' => [
            'title'     => 'El maestro y Margarita',
            'author'    => 'Mijaíl Bulgákov',
            'publisher' => 'Alianza',
            'isbn'      => '9788491046332',
            'notes'     => 'Si te vale de segunda mano, si lo necesitas para una fecha, si es un regalo…',
        ],
    ],

];
