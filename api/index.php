<?php
// ============================================================================
// api/index.php
// PUNTO DE ENTRADA ÚNICO de la API (front controller).
// El archivo .htaccess manda aquí todas las URL que empiezan con /api/.
// Su trabajo: cargar el entorno, aplicar CORS, registrar las rutas y despachar la petición.
// ============================================================================

// No muestra errores de PHP en pantalla (romperían el JSON); se registran en el log del servidor
ini_set('display_errors', '0');

// Carpeta raíz del proyecto (una carpeta más arriba que /api)
$raiz = dirname(__DIR__);

// ---- Carga de todas las piezas de la aplicación ----
require $raiz . '/config/env.php';             // lector del archivo .env
require $raiz . '/config/cors.php';            // permisos CORS
require $raiz . '/config/database.php';        // conexión a MySQL
require $raiz . '/helpers/Respuesta.php';      // respuestas JSON (la "vista")
require $raiz . '/helpers/Peticion.php';       // lectura y validación de la petición
require $raiz . '/helpers/Jwt.php';            // creación y verificación de tokens JWT
require $raiz . '/models/Jugador.php';         // modelo de jugadores
require $raiz . '/models/Partida.php';         // modelo de partidas
require $raiz . '/helpers/Autenticacion.php';  // emisión de tokens y control de permisos (usa el modelo Jugador)
require $raiz . '/controllers/AuthController.php';     // controlador de inicio de sesión
require $raiz . '/controllers/JugadorController.php';  // controlador de jugadores
require $raiz . '/controllers/PartidaController.php';  // controlador de partidas
require $raiz . '/controllers/RankingController.php';  // controlador del ranking
require $raiz . '/routes/Router.php';          // enrutador

try {
    // Lee el .env (credenciales, puertos y ajustes)
    cargarEnv($raiz . '/.env');

    // Agrega los encabezados CORS (y contesta el "preflight" OPTIONS si corresponde)
    aplicarCors();

    // Crea el enrutador y le registra las rutas de cada recurso (cada archivo devuelve una función)
    $router = new Router();
    (require $raiz . '/routes/auth.php')($router);
    (require $raiz . '/routes/jugadores.php')($router);
    (require $raiz . '/routes/partidas.php')($router);
    (require $raiz . '/routes/ranking.php')($router);

    // Carpeta donde vive este archivo dentro de la URL (ej: /fruti-caos-php/api)
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

    // Solo la parte de la ruta de la URL pedida, sin los parámetros ?x=y
    $ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Le saca el prefijo de la carpeta para quedarse con "/jugadores/3", "/ranking", etc.
    if (strpos($ruta, $base) === 0) {
        $ruta = substr($ruta, strlen($base));
    }

    // Normaliza: siempre empieza con "/" y no termina con "/"
    $ruta = '/' . trim($ruta, '/');

    // Busca la ruta y ejecuta el controlador (responde y termina dentro del enrutador)
    $router->despachar($_SERVER['REQUEST_METHOD'], $ruta);

} catch (Throwable $e) {
    // Cualquier error inesperado (base caída, .env incompleto, bug) se registra en el log del servidor
    error_log('[FRUTI-CAOS API] ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());

    // Si APP_DEBUG=true en el .env, se muestra el motivo; si no, se oculta por seguridad
    $detalles = (env('APP_DEBUG', 'false') === 'true') ? ['mensaje' => $e->getMessage()] : [];

    // Responde 500 Internal Server Error con formato JSON
    Respuesta::error('Error interno del servidor', 500, $detalles);
}
