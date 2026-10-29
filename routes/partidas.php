<?php
// ============================================================================
// routes/partidas.php
// Rutas del recurso "partidas" (cada partida guarda el puntaje de una sesión de juego).
// ============================================================================

return function (Router $router): void {
    // GET /api/partidas (opcional ?jugador_id=) -> lista las partidas (Read) [requiere token]
    $router->agregar('GET', '/partidas', PartidaController::class, 'index');

    // POST /api/partidas -> guarda la partida recién terminada (Create) [requiere token]
    $router->agregar('POST', '/partidas', PartidaController::class, 'store');

    // GET /api/partidas/:id -> consulta una partida por su id (Read específico) [dueño o admin]
    $router->agregar('GET', '/partidas/:id', PartidaController::class, 'show');

    // PUT /api/partidas/:id -> reemplaza los datos de la partida (Update completo) [solo admin]
    $router->agregar('PUT', '/partidas/:id', PartidaController::class, 'update');

    // PATCH /api/partidas/:id -> modifica solo algunos datos de la partida (Update parcial) [solo admin]
    $router->agregar('PATCH', '/partidas/:id', PartidaController::class, 'update');

    // DELETE /api/partidas/:id -> borrado lógico de la partida (Delete / soft delete) [solo admin]
    $router->agregar('DELETE', '/partidas/:id', PartidaController::class, 'destroy');
};
