<?php
// ============================================================================
// helpers/Jwt.php
// Crea y verifica tokens JWT (JSON Web Token) firmados con HS256.
// Un JWT tiene 3 partes separadas por puntos: encabezado.cuerpo.firma
// Está hecho a mano (sin librerías) para que el proyecto no necesite Composer.
// ============================================================================

class Jwt
{
    // Convierte texto a Base64URL (la variante de Base64 segura para URLs y encabezados HTTP)
    private static function base64UrlEncode(string $datos): string
    {
        // Pasa a Base64, cambia "+" por "-" y "/" por "_", y quita los "=" del final
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }

    // Convierte Base64URL de vuelta a texto; devuelve false si no es Base64 válido
    private static function base64UrlDecode(string $texto)
    {
        // Calcula cuántos caracteres faltan para que el largo sea múltiplo de 4
        $faltan = strlen($texto) % 4;

        // Si faltan, completa con "=" (el relleno que se había quitado)
        if ($faltan > 0) {
            $texto .= str_repeat('=', 4 - $faltan);
        }

        // Deshace el cambio de caracteres y decodifica (modo estricto: rechaza caracteres raros)
        return base64_decode(strtr($texto, '-_', '+/'), true);
    }

    // Crea un token firmado a partir de los datos ($carga) y la clave secreta
    public static function crear(array $carga, string $secreto): string
    {
        // Encabezado: indica el tipo de token y el algoritmo de firma
        $encabezado = self::base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        // Cuerpo: los datos que viajan dentro del token (id del jugador, vencimiento, etc.)
        $cuerpo = self::base64UrlEncode(json_encode($carga));

        // Firma: huella HMAC-SHA256 de "encabezado.cuerpo" hecha con la clave secreta
        $firma = self::base64UrlEncode(hash_hmac('sha256', $encabezado . '.' . $cuerpo, $secreto, true));

        // El token final une las tres partes con puntos
        return $encabezado . '.' . $cuerpo . '.' . $firma;
    }

    // Verifica un token; devuelve sus datos si es válido y no venció, o null si no lo es
    public static function verificar(string $token, string $secreto): ?array
    {
        // Un token válido tiene exactamente tres partes
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            return null;
        }

        // Separa las tres partes en variables
        [$encabezadoB64, $cuerpoB64, $firmaB64] = $partes;

        // Decodifica el encabezado y comprueba que el algoritmo sea HS256 (rechaza "none" u otros)
        $encabezado = json_decode((string) self::base64UrlDecode($encabezadoB64), true);
        if (!is_array($encabezado) || ($encabezado['alg'] ?? '') !== 'HS256') {
            return null;
        }

        // Recalcula la firma que debería tener el token con nuestra clave secreta
        $firmaEsperada = self::base64UrlEncode(hash_hmac('sha256', $encabezadoB64 . '.' . $cuerpoB64, $secreto, true));

        // Compara las firmas de forma segura (hash_equals evita ataques por tiempo de respuesta)
        if (!hash_equals($firmaEsperada, $firmaB64)) {
            return null;
        }

        // La firma es correcta: ahora se lee el cuerpo del token
        $carga = json_decode((string) self::base64UrlDecode($cuerpoB64), true);
        if (!is_array($carga)) {
            return null;
        }

        // Comprueba que tenga fecha de vencimiento y que todavía no haya pasado
        if (!isset($carga['exp']) || !is_int($carga['exp']) || $carga['exp'] < time()) {
            return null;
        }

        // Token válido: devuelve sus datos
        return $carga;
    }
}
