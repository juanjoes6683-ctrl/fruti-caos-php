<?php
// ============================================================================
// controllers/AuthController.php
// CONTROLADOR de autenticación: inicio de sesión y "quién soy".
// (El registro de jugadores nuevos es POST /api/jugadores, en JugadorController.)
// ============================================================================

class AuthController
{
    // Modelo que se encarga de la tabla "jugadores"
    private Jugador $modelo;

    // Al crear el controlador se crea también su modelo
    public function __construct()
    {
        $this->modelo = new Jugador();
    }

    // POST /api/auth/login -> 200 con el token, 400 si faltan datos, 401 si las credenciales son incorrectas
    public function login(array $params): void
    {
        // Lee el JSON enviado por el cliente
        $cuerpo = Peticion::cuerpoJson();

        // Si el JSON está roto, responde 400 Bad Request
        if ($cuerpo === null) {
            Respuesta::error('El cuerpo de la petición no es un JSON válido', 400);
        }

        // Toma el tag y la contraseña solo si son texto (si no, quedan vacíos)
        $tag = (isset($cuerpo['tag']) && is_string($cuerpo['tag'])) ? trim($cuerpo['tag']) : '';
        $password = (isset($cuerpo['password']) && is_string($cuerpo['password'])) ? $cuerpo['password'] : '';

        // Junta los errores de campos faltantes
        $detalles = [];
        if ($tag === '') {
            $detalles['tag'] = 'El campo tag es obligatorio';
        }
        if ($password === '') {
            $detalles['password'] = 'El campo password es obligatorio';
        }

        // Si falta algo, responde 400 con el detalle
        if (!empty($detalles)) {
            Respuesta::error('Datos inválidos', 400, $detalles);
        }

        // Busca los datos de acceso del tag (incluye el hash de la contraseña)
        $credenciales = $this->modelo->buscarCredenciales($tag);

        // Tag inexistente, jugador de ejemplo sin contraseña o contraseña incorrecta: mismo mensaje
        // (así no se le revela a un atacante si el tag existe o no)
        if (
            $credenciales === null
            || $credenciales['password_hash'] === null
            || !password_verify($password, $credenciales['password_hash'])
        ) {
            Respuesta::error('Tag o contraseña incorrectos', 401);
        }

        // Credenciales correctas: crea el token del jugador
        $sesion = Autenticacion::emitirToken($credenciales['id']);

        // Agrega los datos públicos del jugador para que el juego sepa quién es
        $sesion['jugador'] = $this->modelo->buscarPorId($credenciales['id']);

        // Responde 200 OK con el token y el jugador
        Respuesta::json($sesion, 200);
    }

    // GET /api/auth/yo -> 200 con el jugador dueño del token, 401 si no hay token válido
    public function yo(array $params): void
    {
        // requerirUsuario responde 401 solo si el token falta o es inválido
        Respuesta::json(Autenticacion::requerirUsuario(), 200);
    }
}
