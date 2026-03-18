<?php
// app/rbac.php
// Sistema de roles y permisos (usa require_admin() del helpers.php)

function current_admin_role(): string {
    return $_SESSION['admin_role'] ?? 'AUDITOR';
}

/**
 * Valida acceso por rol específico (usa require_admin del helpers)
 */
function require_role(array $allowedRoles): void {
    require_admin(); // ESTA viene de helpers.php

    $role = current_admin_role();

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        die("No tienes permisos para acceder a esta sección.");
    }
}

/**
 * Permisos por acción granular
 */
function can(string $action): bool {
    $role = current_admin_role();

    $matrix = [
        'SUPERADMIN' => ['*'],

        'SOPORTE' => [
            'contracts.view',
            'contracts.resend',
            'contracts.extend',
            'contracts.download',
            'audit.view',
        ],

        'VENTAS' => [
            'contracts.view',
            'contracts.create',
            'contracts.resend',
            'contracts.extend',
            'contracts.download',
        ],

        'AUDITOR' => [
            'contracts.view',
            'contracts.download',
            'audit.view',
        ],
    ];

    $allowed = $matrix[$role] ?? [];

    return in_array('*', $allowed, true) || in_array($action, $allowed, true);
}

/**
 * Valida acción específica
 */
function require_action(string $action): void {
    require_admin(); // del helpers.php

    if (!can($action)) {
        http_response_code(403);
        die("No tienes permisos para realizar esta acción.");
    }
}
