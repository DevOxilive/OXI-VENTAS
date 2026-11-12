<?php

return [
    'default_timeout_minutes' => 120,
    'warning_minutes' => 5,

    'roles' => [
        // Ventas se mantiene activa mientras exista interacción; sin actividad,
        // conserva la duración general de sesión antes de mostrar la alerta.
        'Ventas' => ['timeout_minutes' => 120],
        'Sistemas' => ['timeout_minutes' => 10],
        'Super Administrador' => ['timeout_minutes' => 10],
        'Administrador' => ['timeout_minutes' => 10],
        'Recursos Humanos' => ['timeout_minutes' => 30],
        'Inventario' => ['timeout_minutes' => 30],
    ],
];
