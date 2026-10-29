<?php
// ============================================================================
// routes/Router.php
// Enrutador: guarda la lista de rutas (método + URL) y llama al controlador correcto.
// ============================================================================

class Router
{
    // Lista de rutas registradas; cada una es un array con método, regex, controlador y acción
    private array $rutas = [];

    // Registra una ruta. Ej: agregar('GET', '/jugadores/:id', JugadorController::class, 'show')
    public function agregar(string $metodo, string $patron, string $controlador, string $accion): void
    {
        // Convierte los parámetros ":id" del patrón en grupos con nombre de una expresión regular
        $regex = preg_replace('#:([a-zA-Z_]+)#', '(?P<$1>[^/]+)', $patron);

        // Guarda la ruta lista para compararla después con cada petición
        $this->rutas[] = [
            'metodo'      => strtoupper($metodo),   // método HTTP en mayúsculas
            'regex'       => '#^' . $regex . '$#',  // la URL debe coincidir completa con el patrón
            'controlador' => $controlador,          // nombre de la clase controladora
            'accion'      => $accion,               // método de la clase que se ejecutará
        ];
    }

    // Busca la ruta que coincide con la petición y ejecuta su controlador
    public function despachar(string $metodo, string $ruta): void
    {
        // Indica si alguna ruta coincide con la URL (aunque el método HTTP no sea el correcto)
        $urlExiste = false;

        // Métodos que sí acepta esa URL (para el encabezado Allow del error 405)
        $metodosPermitidos = [];

        // Prueba cada ruta registrada
        foreach ($this->rutas as $r) {
            // ¿La URL de la petición coincide con el patrón de esta ruta?
            if (preg_match($r['regex'], $ruta, $coincidencias)) {
                // La URL existe en la API
                $urlExiste = true;

                // ¿Además coincide el método HTTP (GET, POST, ...)?
                if ($r['metodo'] === strtoupper($metodo)) {
                    // Se quedan solo los parámetros con nombre (ej: "id"), sin los índices numéricos
                    $parametros = array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);

                    // Toma el nombre de la clase controladora (guardado como texto)
                    $clase = $r['controlador'];

                    // Crea el controlador a partir de ese nombre
                    $controlador = new $clase();

                    // Ejecuta la acción del controlador pasándole los parámetros de la URL
                    $controlador->{$r['accion']}($parametros);

                    // Ya se atendió la petición: se sale de la función
                    return;
                }

                // Si el método no coincide, se anota cuál sí está permitido para esta URL
                $metodosPermitidos[] = $r['metodo'];
            }
        }

        // La URL existe pero con otro método: 405 Method Not Allowed
        if ($urlExiste) {
            // El estándar HTTP pide indicar qué métodos sí se aceptan
            header('Allow: ' . implode(', ', array_unique($metodosPermitidos)));
            Respuesta::error('Método no permitido para este recurso', 405);
        }

        // Ninguna ruta coincide con la URL: 404 Not Found
        Respuesta::error('Ruta no encontrada', 404);
    }
}
