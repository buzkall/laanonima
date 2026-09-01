<?php

return [

    'label'            => 'usuario',
    'plural_label'     => 'usuarios',
    'navigation_label' => 'Usuarios',

    'sections' => [
        'account' => 'Cuenta',
    ],

    'fields' => [
        'name'                  => 'Nombre',
        'role'                  => 'Rol',
        'email'                 => 'Correo electrónico',
        'phone'                 => 'Teléfono',
        'password'              => 'Contraseña',
        'password_confirmation' => 'Confirmar contraseña',
        'email_verified_at'     => 'Verificado el',
        'created_at'            => 'Creado el',
        'updated_at'            => 'Actualizado el',
    ],

    'roles' => [
        'admin'  => 'Administrador',
        'client' => 'Cliente',
    ],

    'tabs' => [
        'client' => 'Clientes',
        'admin'  => 'Administradores',
    ],

    'relations' => [
        'book_requests' => [
            'empty'      => 'Este cliente no nos ha pedido ningún libro.',
            'empty_hint' => 'Aquí aparecerán las solicitudes que envíe desde la web.',
        ],
    ],

    'helpers' => [
        'password'              => 'Déjalo vacío para mantener la contraseña actual.',
        'password_requirements' => 'Debe tener al menos 12 caracteres, con mayúsculas, minúsculas y números.',
    ],

    'actions' => [
        'generate_password'        => 'Generar contraseña',
        'password_generated_title' => 'Contraseña generada',
        'password_generated_body'  => 'La contraseña se ha copiado al portapapeles.',
    ],

    'badges' => [
        'verified' => 'Verificado el :date',
    ],

    'placeholders' => [
        'not_verified' => 'Sin verificar',
    ],

    'filters' => [
        'email_verification' => [
            'label'      => 'Verificación del correo',
            'all'        => 'Todos los usuarios',
            'verified'   => 'Usuarios verificados',
            'unverified' => 'Usuarios sin verificar',
        ],
    ],

    'policy' => [
        'cannot_delete_self' => [
            'all'  => 'No puedes eliminar tu propia cuenta.',
            'some' => ':count de los :total usuarios seleccionados es tu propia cuenta.',
        ],
    ],

];
