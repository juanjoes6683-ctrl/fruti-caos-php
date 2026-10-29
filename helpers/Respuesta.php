<?php
// ============================================================================
// helpers/Respuesta.php
// "Vista" de la API: arma y envía todas las respuestas JSON con su código HTTP.
// Los controladores nunca imprimen nada directamente: siempre usan esta clase.
// ============================================================================

class Respuesta
{
    // Envía $datos como JSON con el código HTTP indicado y termina la ejecución
    public static function json($datos, int $codigo = 200): void
    {
        // Fija el código de estado HTTP (200, 201, 400, 404, ...)
        http_response_code($codigo);

        // Avisa al cliente que el contenido es JSON en UTF-8
        header('Content-Type: application/json; charset=utf-8');

        // Convierte el dato a JSON (sin escapar tildes ni barras) y lo imprime
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // Corta la ejecución: así el controlador no sigue después de responder
        exit;
    }

    // Envía un error con formato estándar: {"error": "...", "detalles": {...}}
    public static function error(string $mensaje, int $codigo, array $detalles = []): void
    {
        // Cuerpo mínimo: solo el mensaje general
        $cuerpo = ['error' => $mensaje];

        // Si hay detalles por campo (ej: qué campo falló), se agregan
        if (!empty($detalles)) {
            $cuerpo['detalles'] = $detalles;
        }

        // Envía la respuesta con el código de error recibido
        self::json($cuerpo, $codigo);
    }
}
