<?php
// ============================================================================
// config/database.php
// Crea (una sola vez) la conexión a MySQL usando PDO y los datos del .env.
// ============================================================================

class Database
{
    // Guarda la conexión ya creada para reutilizarla (patrón "singleton")
    private static ?PDO $conexion = null;

    // Devuelve la conexión a la base de datos
    public static function conectar(): PDO
    {
        // Si ya existe una conexión abierta, se reutiliza en vez de abrir otra
        if (self::$conexion !== null) {
            return self::$conexion;
        }

        // Lee los datos de conexión desde el .env (nada escrito en el código)
        $host    = envObligatoria('DB_HOST'); // servidor de MySQL
        $puerto  = envObligatoria('DB_PORT'); // puerto de MySQL
        $nombre  = envObligatoria('DB_NAME'); // nombre de la base de datos
        $usuario = envObligatoria('DB_USER'); // usuario de MySQL
        $clave   = env('DB_PASS', '');        // contraseña (puede ir vacía, por ejemplo en WAMP)

        // DSN: la "dirección" que PDO necesita para conectarse (utf8mb4 = acentos y emojis)
        $dsn = "mysql:host=$host;port=$puerto;dbname=$nombre;charset=utf8mb4";

        // Abre la conexión con tres opciones de seguridad y comodidad
        self::$conexion = new PDO($dsn, $usuario, $clave, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // los errores de SQL lanzan excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // las filas llegan como arrays con nombre de columna
            PDO::ATTR_EMULATE_PREPARES   => false,                   // consultas preparadas reales (más seguras)
        ]);

        // Devuelve la conexión recién creada
        return self::$conexion;
    }
}
