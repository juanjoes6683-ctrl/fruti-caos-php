<?php
// ============================================================================
// routes/jugadores.php
// Rutas del recurso "jugadores". Devuelve una función que las registra en el Router.
// ============================================================================

return function (Router $router): void {
    // GET /api/jugadores -> lista todos los jugadores (Read) [solo admin]
    $router->agregar('GET', '/jugadores', JugadorController::class, 'index');

    // POST /api/jugadores -> registra un jugador nuevo con tag y password (Create) [pública]
    $router->agregar('POST', '/jugadores', JugadorController::class, 'store');

    // GET /api/jugadores/:id -> consulta un jugador por su id (Read específico) [dueño o admin]
    $router->agregar('GET', '/jugadores/:id', JugadorController::class, 'show');

    // PUT /api/jugadores/:id -> reemplaza los datos del jugador (Update completo) [dueño o admin]
    $router->agregar('PUT', '/jugadores/:id', JugadorController::class, 'update');

    // PATCH /api/jugadores/:id -> modifica solo algunos datos del jugador (Update parcial) [dueño o admin]
    $router->agregar('PATCH', '/jugadores/:id', JugadorController::class, 'update');

    // DELETE /api/jugadores/:id -> elimina al jugador (Delete) [dueño o admin]
    $router->agregar('DELETE', '/jugadores/:id', JugadorController::class, 'destroy');
};
