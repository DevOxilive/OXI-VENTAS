<?php

return [
    'default_timeout_minutes' => 120,
    'warning_minutes' => 5,

    'roles' => [
        // Ventas conserva su sesión mediante la renovación automática ya aprobada.
        'Ventas' => ['timeout_minutes' => null, 'keep_alive' => true],
        'Sistemas' => ['timeout_minutes' => 10],
        'Super Administrador' => ['timeout_minutes' => 10],
        'Administrador' => ['timeout_minutes' => 10],
        'Recursos Humanos' => ['timeout_minutes' => 30],
        'Inventario' => ['timeout_minutes' => 30],
    ],
];
