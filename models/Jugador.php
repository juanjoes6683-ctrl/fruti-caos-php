<?php
// ============================================================================
// models/Jugador.php
// MODELO de jugadores: es la ÚNICA capa que habla con la tabla "jugadores".
// No valida datos ni responde HTTP: solo ejecuta consultas SQL y devuelve datos.
// La contraseña se guarda siempre como hash (nunca en texto plano) y NUNCA sale en las respuestas.
// ============================================================================

class Jugador
{
    // Conexión a la base de datos que usarán todos los métodos
    private PDO $db;

    // Al crear el modelo se obtiene la conexión compartida
    public function __construct()
    {
        $this->db = Database::conectar();
    }

    // Devuelve todos los jugadores, del más antiguo al más nuevo
    public function listar(): array
    {
        // Consulta simple (sin datos del usuario, por eso no necesita parámetros); no incluye el hash
        $consulta = $this->db->query('SELECT id, tag, rol, creado_en FROM jugadores ORDER BY id ASC');

        // Da formato a cada fila y devuelve la lista completa
        return array_map([$this, 'formatear'], $consulta->fetchAll());
    }

    // Busca un jugador por su id; devuelve null si no existe
    public function buscarPorId(int $id): ?array
    {
        // Consulta preparada: el :id se reemplaza de forma segura (evita inyección SQL)
        $consulta = $this->db->prepare('SELECT id, tag, rol, creado_en FROM jugadores WHERE id = :id');

        // Ejecuta la consulta con el valor real del id
        $consulta->execute([':id' => $id]);

        // Toma la primera (y única) fila; devuelve false si no hay ninguna
        $fila = $consulta->fetch();

        // Si no hay fila devuelve null; si hay, la devuelve con formato
        return $fila ? $this->formatear($fila) : null;
    }

    // Busca un jugador por su tag (sin distinguir mayúsculas); devuelve null si no existe
    public function buscarPorTag(string $tag): ?array
    {
        // Consulta preparada por tag (la columna usa una collation que ignora mayúsculas/minúsculas)
        $consulta = $this->db->prepare('SELECT id, tag, rol, creado_en FROM jugadores WHERE tag = :tag');

        // Ejecuta la consulta con el tag recibido
        $consulta->execute([':tag' => $tag]);

        // Toma la fila encontrada (o false)
        $fila = $consulta->fetch();

        // Devuelve la fila con formato o null si no existe
        return $fila ? $this->formatear($fila) : null;
    }

    // Busca los datos de acceso (incluye el hash) de un tag; SOLO se usa para iniciar sesión
    public function buscarCredenciales(string $tag): ?array
    {
        // Consulta preparada que sí trae el hash de la contraseña
        $consulta = $this->db->prepare('SELECT id, tag, password_hash, rol FROM jugadores WHERE tag = :tag');

        // Ejecuta con el tag recibido
        $consulta->execute([':tag' => $tag]);

        // Toma la fila encontrada (o false)
        $fila = $consulta->fetch();

        // Si no existe devuelve null; si existe, devuelve id, hash y rol con el id como número
        return $fila ? ['id' => (int) $fila['id'], 'password_hash' => $fila['password_hash'], 'rol' => $fila['rol']] : null;
    }

    // Inserta un jugador nuevo con su contraseña ya convertida en hash y devuelve su id
    public function crear(string $tag, string $passwordHash): int
    {
        // Consulta preparada de inserción (el rol queda en "jugador" por defecto en la base)
        $consulta = $this->db->prepare('INSERT INTO jugadores (tag, password_hash) VALUES (:tag, :hash)');

        // Ejecuta la inserción con el tag y el hash
        $consulta->execute([':tag' => $tag, ':hash' => $passwordHash]);

        // Devuelve el id autogenerado del jugador recién creado
        return (int) $this->db->lastInsertId();
    }

    // Modifica los campos indicados de un jugador (solo tag y password_hash; el rol NO se cambia desde la API)
    public function actualizar(int $id, array $campos): void
    {
        // Lista blanca: únicas columnas que se pueden modificar
        $permitidos = ['tag', 'password_hash'];

        // Fragmentos "columna = :columna" que formarán el SET del UPDATE
        $asignaciones = [];

        // Valores a enviar a la consulta (el id siempre va)
        $parametros = [':id' => $id];

        // Recorre los campos recibidos
        foreach ($campos as $columna => $valor) {
            // Ignora cualquier campo que no esté en la lista blanca (así nadie se puede ascender a admin)
            if (!in_array($columna, $permitidos, true)) {
                continue;
            }

            // Agrega el fragmento SQL (el nombre sale de la lista blanca, es seguro)
            $asignaciones[] = "$columna = :$columna";

            // Guarda el valor para enlazarlo a su parámetro
            $parametros[":$columna"] = $valor;
        }

        // Si no hay nada que modificar, no se consulta la base
        if (empty($asignaciones)) {
            return;
        }

        // Arma y ejecuta el UPDATE final
        $consulta = $this->db->prepare('UPDATE jugadores SET ' . implode(', ', $asignaciones) . ' WHERE id = :id');
        $consulta->execute($parametros);
    }

    // Elimina al jugador; la base borra también sus partidas (ON DELETE CASCADE)
    public function eliminar(int $id): void
    {
        // Consulta preparada de borrado
        $consulta = $this->db->prepare('DELETE FROM jugadores WHERE id = :id');

        // Ejecuta el borrado del jugador indicado
        $consulta->execute([':id' => $id]);
    }

    // Convierte una fila de la base en el array que se enviará como JSON (con tipos correctos)
    private function formatear(array $fila): array
    {
        return [
            'id'        => (int) $fila['id'],  // el id siempre sale como número
            'tag'       => $fila['tag'],       // nombre del jugador
            'rol'       => $fila['rol'],       // "jugador" o "admin"
            'creado_en' => $fila['creado_en'], // fecha y hora de registro
        ];
    }
}
