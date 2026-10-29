<?php
// ============================================================================
// routes/auth.php
// Rutas de autenticación.
// ============================================================================

return function (Router $router): void {
    // POST /api/auth/login -> inicia sesión y devuelve un token (pública)
    $router->agregar('POST', '/auth/login', AuthController::class, 'login');

    // GET /api/auth/yo -> devuelve el jugador del token (requiere token)
    $router->agregar('GET', '/auth/yo', AuthController::class, 'yo');
};
