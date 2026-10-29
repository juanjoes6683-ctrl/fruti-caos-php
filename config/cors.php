<?php
// ============================================================================
// config/cors.php
// Configura CORS: permite que el juego (navegador) llame a la API aunque esté
// en otro origen (otro dominio o puerto) sin que lo bloquee la Same-Origin Policy.
// ============================================================================

// Agrega los encabezados CORS a la respuesta y contesta las peticiones "preflight"
function aplicarCors(): void
{
    // Orígenes permitidos, definidos en el .env (CORS_ORIGIN): "*" o una lista separada por comas
    $permitidos = env('CORS_ORIGIN', '');

    // Origen desde el que el navegador hace la petición (vacío si es el mismo origen)
    $origenPeticion = $_SERVER['HTTP_ORIGIN'] ?? '';

    // Caso 1: se permite cualquier origen
    if ($permitidos === '*') {
        header('Access-Control-Allow-Origin: *');
    // Caso 2: hay una lista; solo se acepta si el origen de la petición está en ella
    } elseif ($origenPeticion !== '') {
        // Convierte "a,b,c" en un array y limpia los espacios de cada uno
        $lista = array_map('trim', explode(',', $permitidos));

        // Si el origen está en la lista, se lo autoriza explícitamente
        if (in_array($origenPeticion, $lista, true)) {
            header('Access-Control-Allow-Origin: ' . $origenPeticion);
            // Avisa a los cachés que la respuesta cambia según el origen
            header('Vary: Origin');
        }
    }

    // Métodos HTTP que el navegador puede usar contra la API
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

    // Encabezados que el navegador puede enviar (Content-Type es necesario para enviar JSON)
    header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization');

    // El navegador puede recordar este permiso 24 horas (86400 segundos) y no repetir el preflight
    header('Access-Control-Max-Age: 86400');

    // Los navegadores mandan primero una petición OPTIONS ("preflight") para preguntar si pueden llamar
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        // Se responde 204 (sin contenido) y se termina: no hay nada más que hacer
        http_response_code(204);
        exit;
    }
}
