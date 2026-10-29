<?php
// ============================================================================
// controllers/RankingController.php
// CONTROLADOR del ranking global (solo lectura).
// ============================================================================

class RankingController
{
    // Modelo que sabe calcular el ranking (vive en la tabla "partidas")
    private Partida $modelo;

    // Al crear el controlador se crea su modelo
    public function __construct()
    {
        $this->modelo = new Partida();
    }

    // GET /api/ranking (opcional ?limite=) -> 200 con el ranking, 400 si el límite es inválido
    public function index(array $params): void
    {
        // Por defecto se devuelven 10 jugadores
        $limite = 10;

        // Si la URL trae ?limite=, se valida que sea un entero entre 1 y 50
        if (isset($_GET['limite'])) {
            $limite = Peticion::entero($_GET['limite']);
            if ($limite === null || $limite < 1 || $limite > 50) {
                Respuesta::error('limite debe ser un entero entre 1 y 50', 400);
            }
        }

        // Responde 200 OK con la mejor partida de cada jugador
        Respuesta::json($this->modelo->ranking($limite), 200);
    }
}
