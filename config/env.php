<?php
// ============================================================================
// config/env.php
// Lee el archivo .env (credenciales y ajustes) para que NO estén escritas en el código.
// ============================================================================

// Función que carga el archivo .env y guarda cada variable en $_ENV.
// Recibe la ruta completa del archivo y no devuelve nada (void).
function cargarEnv(string $rutaArchivo): void
{
    // Si el archivo .env no existe, se corta todo con un mensaje claro
    if (!is_file($rutaArchivo)) {
        throw new RuntimeException('Falta el archivo .env: copiá .env.example como .env y completalo');
    }

    // Lee el archivo línea por línea (sin los saltos de línea y salteando las líneas vacías)
    $lineas = file($rutaArchivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    // Si no se pudo leer el archivo, también se corta con un error
    if ($lineas === false) {
        throw new RuntimeException('No se pudo leer el archivo .env');
    }

    // Recorre cada línea del archivo
    foreach ($lineas as $linea) {
        // Saca los espacios del principio y del final
        $linea = trim($linea);

        // Salta las líneas vacías y los comentarios (empiezan con #)
        if ($linea === '' || $linea[0] === '#') {
            continue;
        }

        // Salta las líneas que no tienen el signo "=" (no son CLAVE=valor)
        if (strpos($linea, '=') === false) {
            continue;
        }

        // Separa la línea en clave y valor usando solo el primer "="
        [$clave, $valor] = explode('=', $linea, 2);

        // Limpia espacios sobrantes de ambos lados
        $clave = trim($clave);
        $valor = trim($valor);

        // Si el valor está entre comillas simples o dobles, se las quita
        if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && substr($valor, -1) === $valor[0]) {
            $valor = substr($valor, 1, -1);
        }

        // Guarda la variable para poder leerla después con env()
        $_ENV[$clave] = $valor;
    }
}

// Devuelve el valor de una variable del .env, o $porDefecto si no existe.
function env(string $clave, ?string $porDefecto = null): ?string
{
    // Busca la clave en $_ENV; si no está, usa el valor por defecto
    return $_ENV[$clave] ?? $porDefecto;
}

// Devuelve el valor de una variable del .env y falla si falta o está vacía.
// Se usa para datos imprescindibles (host, puerto, base y usuario de la base de datos).
function envObligatoria(string $clave): string
{
    // Lee la variable (null si no existe)
    $valor = env($clave);

    // Si no existe o está vacía, avisa cuál falta
    if ($valor === null || $valor === '') {
        throw new RuntimeException("Falta la variable $clave en el archivo .env");
    }

    // Devuelve el valor ya validado
    return $valor;
}
