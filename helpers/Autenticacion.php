<?php
// ============================================================================
// helpers/Autenticacion.php
// Todo lo relacionado con "quién hace la petición" y "qué puede hacer":
//  - emite el token al iniciar sesión,
//  - exige token válido (401 si falta o es inválido),
//  - exige rol o propiedad del recurso (403 si no tiene permiso).
// 401 Unauthorized = no sabemos quién sos. 403 Forbidden = sabemos quién sos, pero no podés.
// ============================================================================

class Autenticacion
{
    // Guarda el jugador ya identificado en esta petición (evita consultar la base varias veces)
    private static ?array $usuario = null;

    // Lee el encabezado "Authorization" de la petición (Apache a veces lo esconde, por eso se prueban varias formas)
    private static function leerEncabezado(): ?string
    {
        // Variables donde Apache puede dejar el encabezado (la segunda aparece tras una reescritura de URL)
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $clave) {
            if (!empty($_SERVER[$clave])) {
                return $_SERVER[$clave];
            }
        }

        // Si no estaba ahí, se buscan todos los encabezados (función disponible en Apache)
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $nombre => $valor) {
                // Los nombres de encabezados no distinguen mayúsculas
                if (strtolower($nombre) === 'authorization') {
                    return $valor;
                }
            }
        }

        // No se encontró ningún encabezado Authorization
        return null;
    }

    // Devuelve la clave secreta para firmar tokens (del .env) y exige que sea larga
    private static function secreto(): string
    {
        // Lee JWT_SECRET del .env (falla si no existe)
        $secreto = envObligatoria('JWT_SECRET');

        // Una clave corta es fácil de adivinar: se exigen al menos 32 caracteres
        if (strlen($secreto) < 32) {
            throw new RuntimeException('JWT_SECRET debe tener al menos 32 caracteres (generá una nueva, ver .env.example)');
        }

        // Devuelve la clave ya validada
        return $secreto;
    }

    // Crea un token para el jugador indicado; devuelve el token, su tipo y cuándo vence
    public static function emitirToken(int $jugadorId): array
    {
        // Horas de vida del token, configurables en el .env (por defecto 8)
        $horas = (int) env('JWT_TTL_HORAS', '8');
        if ($horas < 1) {
            $horas = 8;
        }

        // Momento actual y momento de vencimiento (en segundos Unix)
        $ahora = time();
        $vence = $ahora + $horas * 3600;

        // Crea el token con: sub = a quién pertenece, iat = cuándo se emitió, exp = cuándo vence
        $token = Jwt::crear(['sub' => $jugadorId, 'iat' => $ahora, 'exp' => $vence], self::secreto());

        // Devuelve todo lo que necesita el cliente
        return ['token' => $token, 'tipo' => 'Bearer', 'expira_en' => $vence];
    }

    // Exige un token válido y devuelve el jugador dueño del token (o responde 401 y corta)
    public static function requerirUsuario(): array
    {
        // Si ya se identificó en esta petición, se reutiliza
        if (self::$usuario !== null) {
            return self::$usuario;
        }

        // Lee el encabezado Authorization
        $encabezado = self::leerEncabezado();

        // Debe existir y empezar con "Bearer " (así se envían los tokens)
        if ($encabezado === null || stripos($encabezado, 'Bearer ') !== 0) {
            self::noAutorizado('Falta el token: enviá el encabezado Authorization: Bearer <token>');
        }

        // Se queda solo con el token (lo que viene después de "Bearer ")
        $token = trim(substr($encabezado, 7));

        // Verifica firma y vencimiento
        $carga = Jwt::verificar($token, self::secreto());
        if ($carga === null || !isset($carga['sub'])) {
            self::noAutorizado('Token inválido o vencido');
        }

        // Busca al jugador en la base (así se usa su rol actual y se detecta si fue eliminado)
        $usuario = (new Jugador())->buscarPorId((int) $carga['sub']);
        if ($usuario === null) {
            self::noAutorizado('El jugador de este token ya no existe');
        }

        // Guarda y devuelve el jugador identificado
        self::$usuario = $usuario;
        return $usuario;
    }

    // Exige que el jugador sea administrador (401 si no hay token, 403 si no es admin)
    public static function requerirAdmin(): array
    {
        // Primero debe estar identificado
        $usuario = self::requerirUsuario();

        // Si su rol no es admin, no tiene permiso
        if ($usuario['rol'] !== 'admin') {
            Respuesta::error('Esta acción es solo para administradores', 403);
        }

        // Es admin: se devuelve
        return $usuario;
    }

    // Exige que el jugador sea el dueño del recurso o un administrador (si no, responde 403)
    public static function requerirPropietarioOAdmin(array $usuario, int $idJugadorDueno): void
    {
        // Los administradores pueden todo; los demás solo sobre lo suyo
        if ($usuario['rol'] !== 'admin' && $usuario['id'] !== $idJugadorDueno) {
            Respuesta::error('No tenés permiso sobre este recurso', 403);
        }
    }

    // Responde 401 con el encabezado WWW-Authenticate que pide el estándar HTTP
    private static function noAutorizado(string $mensaje): void
    {
        // Indica al cliente qué tipo de credencial se espera
        header('WWW-Authenticate: Bearer');

        // Envía el error 401 y corta la ejecución
        Respuesta::error($mensaje, 401);
    }
}
