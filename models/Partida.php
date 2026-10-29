<?php
// ============================================================================
// models/Partida.php
// MODELO de partidas: es la ÚNICA capa que habla con la tabla "partidas".
// Una partida = una sesión de juego de un jugador, con su puntaje.
// No valida datos ni responde HTTP: solo ejecuta consultas SQL y devuelve datos.
// ============================================================================

class Partida
{
    // Conexión a la base de datos que usarán todos los métodos
    private PDO $db;

    // Texto SQL base: partida + tag del jugador (JOIN), sin las partidas borradas lógicamente
    private const SELECT_BASE =
        'SELECT p.id, p.jugador_id, j.tag AS jugador, p.puntaje, p.nivel_jugo, p.resultado, p.creado_en
         FROM partidas p
         JOIN jugadores j ON j.id = p.jugador_id
         WHERE p.eliminado_en IS NULL';

    // Al crear el modelo se obtiene la conexión compartida
    public function __construct()
    {
        $this->db = Database::conectar();
    }

    // Lista las partidas (las más nuevas primero); si se indica $jugadorId, solo las de ese jugador
    public function listar(?int $jugadorId = null): array
    {
        // Arranca con la consulta base
        $sql = self::SELECT_BASE;

        // Parámetros de la consulta (vacío si no hay filtro)
        $parametros = [];

        // Si se pidió filtrar por jugador, se agrega la condición
        if ($jugadorId !== null) {
            $sql .= ' AND p.jugador_id = :jugador_id';
            $parametros[':jugador_id'] = $jugadorId;
        }

        // Ordena de la partida más reciente a la más antigua
        $sql .= ' ORDER BY p.id DESC';

        // Prepara y ejecuta la consulta de forma segura
        $consulta = $this->db->prepare($sql);
        $consulta->execute($parametros);

        // Da formato a cada fila y devuelve la lista
        return array_map([$this, 'formatear'], $consulta->fetchAll());
    }

    // Busca una partida (no borrada) por su id; devuelve null si no existe
    public function buscarPorId(int $id): ?array
    {
        // Consulta base + condición por id
        $consulta = $this->db->prepare(self::SELECT_BASE . ' AND p.id = :id');

        // Ejecuta con el id recibido
        $consulta->execute([':id' => $id]);

        // Toma la fila encontrada (o false)
        $fila = $consulta->fetch();

        // Devuelve la fila con formato o null si no existe
        return $fila ? $this->formatear($fila) : null;
    }

    // Inserta una partida nueva y devuelve su id
    public function crear(int $jugadorId, int $puntaje, int $nivelJugo, string $resultado): int
    {
        // Consulta preparada de inserción
        $consulta = $this->db->prepare(
            'INSERT INTO partidas (jugador_id, puntaje, nivel_jugo, resultado)
             VALUES (:jugador_id, :puntaje, :nivel_jugo, :resultado)'
        );

        // Ejecuta la inserción con los valores recibidos
        $consulta->execute([
            ':jugador_id' => $jugadorId,
            ':puntaje'    => $puntaje,
            ':nivel_jugo' => $nivelJugo,
            ':resultado'  => $resultado,
        ]);

        // Devuelve el id autogenerado de la partida
        return (int) $this->db->lastInsertId();
    }

    // Modifica los campos indicados de una partida (solo puntaje, nivel_jugo y resultado)
    public function actualizar(int $id, array $campos): void
    {
        // Lista blanca: únicas columnas que se pueden modificar (evita tocar otras)
        $permitidos = ['puntaje', 'nivel_jugo', 'resultado'];

        // Fragmentos "columna = :columna" que formarán el SET del UPDATE
        $asignaciones = [];

        // Valores que se enviarán a la consulta (el id siempre va)
        $parametros = [':id' => $id];

        // Recorre los campos recibidos
        foreach ($campos as $columna => $valor) {
            // Ignora cualquier campo que no esté en la lista blanca
            if (!in_array($columna, $permitidos, true)) {
                continue;
            }

            // Agrega el fragmento SQL de esa columna (el nombre viene de la lista blanca, es seguro)
            $asignaciones[] = "$columna = :$columna";

            // Guarda el valor para enlazarlo a su parámetro
            $parametros[":$columna"] = $valor;
        }

        // Si no quedó nada para modificar, no hace falta consultar la base
        if (empty($asignaciones)) {
            return;
        }

        // Arma el UPDATE final (no toca partidas ya borradas)
        $sql = 'UPDATE partidas SET ' . implode(', ', $asignaciones) . ' WHERE id = :id AND eliminado_en IS NULL';

        // Prepara y ejecuta la actualización
        $consulta = $this->db->prepare($sql);
        $consulta->execute($parametros);
    }

    // Borrado LÓGICO: no borra la fila, solo anota la fecha en eliminado_en
    public function eliminarLogico(int $id): void
    {
        // Consulta preparada: marca la partida como eliminada ahora mismo
        $consulta = $this->db->prepare(
            'UPDATE partidas SET eliminado_en = NOW() WHERE id = :id AND eliminado_en IS NULL'
        );

        // Ejecuta el borrado lógico de la partida indicada
        $consulta->execute([':id' => $id]);
    }

    // Mejor puntaje (sin contar partidas borradas) de un jugador; null si todavía no jugó
    public function mejorPuntaje(int $jugadorId): ?int
    {
        // MAX devuelve NULL cuando el jugador no tiene partidas
        $consulta = $this->db->prepare(
            'SELECT MAX(puntaje) FROM partidas WHERE jugador_id = :jugador_id AND eliminado_en IS NULL'
        );

        // Ejecuta con el id del jugador
        $consulta->execute([':jugador_id' => $jugadorId]);

        // Lee el único valor devuelto
        $mejor = $consulta->fetchColumn();

        // Si es null (sin partidas) devuelve null; si no, lo devuelve como número
        return $mejor === null ? null : (int) $mejor;
    }

    // Posición en el ranking de un jugador cuya mejor partida tiene $mejor puntos
    public function posicionRanking(int $mejor): int
    {
        // Cuenta cuántos jugadores tienen una mejor partida superior, y suma 1
        $consulta = $this->db->prepare(
            'SELECT COUNT(*) + 1
             FROM (SELECT jugador_id, MAX(puntaje) AS mejor
                   FROM partidas
                   WHERE eliminado_en IS NULL
                   GROUP BY jugador_id) AS t
             WHERE t.mejor > :mejor'
        );

        // Ejecuta con el puntaje a comparar
        $consulta->execute([':mejor' => $mejor]);

        // Devuelve la posición como número
        return (int) $consulta->fetchColumn();
    }

    // Ranking global: SOLO la mejor partida de cada jugador, de mayor a menor puntaje
    public function ranking(int $limite): array
    {
        // NOT EXISTS descarta cualquier partida que tenga otra mejor del mismo jugador
        // (si empata consigo mismo, queda la más antigua). Funciona en cualquier versión de MySQL.
        $consulta = $this->db->prepare(
            'SELECT j.tag AS jugador, p.puntaje, p.nivel_jugo, p.resultado
             FROM partidas p
             JOIN jugadores j ON j.id = p.jugador_id
             WHERE p.eliminado_en IS NULL
               AND NOT EXISTS (
                   SELECT 1 FROM partidas q
                   WHERE q.jugador_id = p.jugador_id
                     AND q.eliminado_en IS NULL
                     AND (q.puntaje > p.puntaje OR (q.puntaje = p.puntaje AND q.id < p.id))
               )
             ORDER BY p.puntaje DESC, p.id ASC
             LIMIT :limite'
        );

        // El límite se enlaza como entero (LIMIT no acepta texto)
        $consulta->bindValue(':limite', $limite, PDO::PARAM_INT);

        // Ejecuta la consulta
        $consulta->execute();

        // Convierte cada fila a tipos correctos y devuelve la lista
        return array_map(function (array $fila): array {
            return [
                'jugador'    => $fila['jugador'],          // tag del jugador
                'puntaje'    => (int) $fila['puntaje'],    // puntaje como número
                'nivel_jugo' => (int) $fila['nivel_jugo'], // nivel de jugo (0 a 5) como número
                'resultado'  => $fila['resultado'],        // "jugo" o "caja_llena"
            ];
        }, $consulta->fetchAll());
    }

    // Convierte una fila de la base en el array que se enviará como JSON (con tipos correctos)
    private function formatear(array $fila): array
    {
        return [
            'id'         => (int) $fila['id'],          // id de la partida
            'jugador_id' => (int) $fila['jugador_id'],  // id del jugador dueño de la partida
            'jugador'    => $fila['jugador'],           // tag del jugador (viene del JOIN)
            'puntaje'    => (int) $fila['puntaje'],     // puntaje obtenido
            'nivel_jugo' => (int) $fila['nivel_jugo'],  // nivel de jugo (0 a 5)
            'resultado'  => $fila['resultado'],         // "jugo" o "caja_llena"
            'creado_en'  => $fila['creado_en'],         // fecha y hora de la partida
        ];
    }
}
