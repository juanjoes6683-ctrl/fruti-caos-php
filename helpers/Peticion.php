<?php
// ============================================================================
// helpers/Peticion.php
// Funciones auxiliares para leer y validar lo que manda el cliente.
// ============================================================================

class Peticion
{
    // Lee el cuerpo de la petición (JSON) y lo devuelve como array.
    // Devuelve [] si no hay cuerpo y null si el JSON es inválido.
    public static function cuerpoJson(): ?array
    {
        // Lee el cuerpo crudo de la petición HTTP
        $crudo = file_get_contents('php://input');

        // Si no hay cuerpo, se devuelve un array vacío (los validadores avisarán qué falta)
        if ($crudo === false || trim($crudo) === '') {
            return [];
        }

        // Convierte el texto JSON a un array asociativo de PHP
        $datos = json_decode($crudo, true);

        // Si no es un objeto/array válido (JSON roto, o solo un número o texto), devuelve null
        if (!is_array($datos)) {
            return null;
        }

        // Devuelve los datos ya convertidos
        return $datos;
    }

    // Convierte el id de la URL a entero positivo; devuelve null si no es válido (ej: "abc" o "0")
    public static function idValido(string $texto): ?int
    {
        // ctype_digit acepta solo caracteres 0-9; además el número debe ser mayor que cero
        if (ctype_digit($texto) && (int) $texto > 0) {
            return (int) $texto;
        }

        // Cualquier otra cosa es un id inválido
        return null;
    }

    // Convierte un valor cualquiera (del JSON o de la URL) a entero; devuelve null si no es un entero válido
    public static function entero($valor): ?int
    {
        // Se rechazan booleanos y arrays (filter_var los aceptaría o confundiría)
        if (is_bool($valor) || is_array($valor) || $valor === null) {
            return null;
        }

        // Valida que sea un entero (acepta 5 y "5", rechaza "5.5" y "abc")
        $resultado = filter_var($valor, FILTER_VALIDATE_INT);

        // filter_var devuelve false cuando no es entero (0 es válido, por eso se compara con ===)
        return $resultado === false ? null : $resultado;
    }
}
