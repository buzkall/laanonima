<?php

return [

    'label'            => 'usuario',
    'plural_label'     => 'usuarios',
    'navigation_label' => 'Usuarios',

    'sections' => [
        'account' => 'Cuenta',
    ],

    'fields' => [
        'name'              => 'Nombre',
        'role'              => 'Rol',
        'email'             => 'Correo electrónico',
        'password'          => 'Contraseña',
        'email_verified_at' => 'Verificado el',
        'created_at'        => 'Creado el',
        'updated_at'        => 'Actualizado el',
    ],

    'roles' => [
        'admin'  => 'Administrador',
        'client' => 'Cliente',
    ],

    'helpers' => [
        'password' => 'Déjalo vacío para mantener la contraseña actual.',
    ],

    'placeholders' => [
        'not_verified' => 'Sin verificar',
    ],

    'filters' => [
        'role' => 'Rol',

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
