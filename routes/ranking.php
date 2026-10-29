<?php
// ============================================================================
// routes/ranking.php
// Ruta del ranking global (solo lectura).
// ============================================================================

return function (Router $router): void {
    // GET /api/ranking (opcional ?limite=10) -> mejor partida de cada jugador, de mayor a menor
    $router->agregar('GET', '/ranking', RankingController::class, 'index');
};
