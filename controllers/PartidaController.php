<?php
// ============================================================================
// controllers/PartidaController.php
// CONTROLADOR de partidas: comprueba permisos, valida los datos, coordina los modelos
// y responde con el código HTTP adecuado. No escribe SQL.
//
// Permisos (todas las rutas exigen token):
//   GET  /partidas        un jugador ve solo las suyas; un admin ve todas
//   POST /partidas        el jugador guarda SUS partidas (el admin puede guardar para otro)
//   GET  /partidas/:id    solo el dueño de la partida o un admin
//   PUT/PATCH/DELETE /partidas/:id   solo administradores (evita que alguien se cambie el puntaje)
// ============================================================================

class PartidaController
{
    // Puntaje máximo aceptado (tope de seguridad contra valores absurdos)
    private const PUNTAJE_TOPE = 1000000;

    // Modelo de la tabla "partidas"
    private Partida $modelo;

    // Modelo de la tabla "jugadores" (para comprobar que el jugador exista)
    private Jugador $modeloJugador;

    // Al crear el controlador se crean sus dos modelos
    public function __construct()
    {
        $this->modelo = new Partida();
        $this->modeloJugador = new Jugador();
    }

    // GET /api/partidas (opcional ?jugador_id=) -> 200 con la lista, 400 filtro inválido, 401, 403
    public function index(array $params): void
    {
        // Exige token válido
        $usuario = Autenticacion::requerirUsuario();

        // Por defecto no se filtra por jugador
        $jugadorId = null;

        // Si la URL trae ?jugador_id=, se valida
        if (isset($_GET['jugador_id'])) {
            $jugadorId = Peticion::entero($_GET['jugador_id']);
            if ($jugadorId === null || $jugadorId < 1) {
                Respuesta::error('jugador_id debe ser un entero positivo', 400);
            }
        }

        // Un jugador común solo puede ver sus propias partidas
        if ($usuario['rol'] !== 'admin') {
            // Si pidió las de otro jugador, no tiene permiso
            if ($jugadorId !== null && $jugadorId !== $usuario['id']) {
                Respuesta::error('Solo podés ver tus propias partidas', 403);
            }
            // Se fuerza el filtro a su propio id
            $jugadorId = $usuario['id'];
        }

        // Responde 200 OK con las partidas (todas para el admin; las propias para un jugador)
        Respuesta::json($this->modelo->listar($jugadorId), 200);
    }

    // GET /api/partidas/:id -> 200 con la partida, 400 id inválido, 401, 403 sin permiso, 404 no existe
    public function show(array $params): void
    {
        // Exige token válido
        $usuario = Autenticacion::requerirUsuario();

        // Valida el id de la URL
        $id = Peticion::idValido($params['id']);
        if ($id === null) {
            Respuesta::error('El id debe ser un entero positivo', 400);
        }

        // Busca la partida
        $partida = $this->modelo->buscarPorId($id);

        // Si no existe (o fue borrada lógicamente), responde 404
        if ($partida === null) {
            Respuesta::error('Partida no encontrada', 404);
        }

        // Solo el dueño de la partida o un admin pueden verla
        Autenticacion::requerirPropietarioOAdmin($usuario, $partida['jugador_id']);

        // Responde 200 OK con la partida
        Respuesta::json($partida, 200);
    }

    // POST /api/partidas -> 201 si se guarda, 400 datos inválidos, 401 sin token, 403 si es de otro jugador
    public function store(array $params): void
    {
        // Exige token válido: la partida se guarda a nombre del jugador del token
        $usuario = Autenticacion::requerirUsuario();

        // Lee el JSON enviado por el juego
        $cuerpo = Peticion::cuerpoJson();
        if ($cuerpo === null) {
            Respuesta::error('El cuerpo de la petición no es un JSON válido', 400);
        }

        // Aquí se juntan los errores de validación por campo
        $detalles = [];

        // jugador_id es opcional: si no viene, la partida es del jugador del token
        $jugadorId = $usuario['id'];
        if (array_key_exists('jugador_id', $cuerpo)) {
            $jugadorId = Peticion::entero($cuerpo['jugador_id']);
            if ($jugadorId === null || $jugadorId < 1) {
                $detalles['jugador_id'] = 'jugador_id debe ser un entero positivo';
            }
        }

        // Valida puntaje, nivel_jugo y resultado (puntaje y nivel_jugo son obligatorios)
        $validos = $this->validarCampos($cuerpo, ['puntaje', 'nivel_jugo'], $detalles);

        // Si algo falló, responde 400 con el detalle
        if (!empty($detalles)) {
            Respuesta::error('Datos inválidos', 400, $detalles);
        }

        // Un jugador común solo puede guardar partidas propias (el admin puede guardar para cualquiera)
        Autenticacion::requerirPropietarioOAdmin($usuario, $jugadorId);

        // El jugador indicado debe existir en la tabla jugadores
        if ($this->modeloJugador->buscarPorId($jugadorId) === null) {
            Respuesta::error('Datos inválidos', 400, ['jugador_id' => 'No existe un jugador con ese id']);
        }

        // Si no se envió resultado, se asume "jugo"
        $resultado = $validos['resultado'] ?? 'jugo';

        // Mejor puntaje que el jugador tenía ANTES de esta partida (null si es su primera)
        $anterior = $this->modelo->mejorPuntaje($jugadorId);

        // Guarda la partida y obtiene su id
        $id = $this->modelo->crear($jugadorId, $validos['puntaje'], $validos['nivel_jugo'], $resultado);

        // ¿Esta partida supera su mejor marca anterior? (o es la primera)
        $esRecord = ($anterior === null || $validos['puntaje'] > $anterior);

        // Su mejor puntaje actual: el nuevo si es récord, si no el anterior
        $mejor = $esRecord ? $validos['puntaje'] : $anterior;

        // Lee la partida guardada para devolverla completa
        $partida = $this->modelo->buscarPorId($id);

        // Agrega los datos de ranking que usa el juego para mostrar el mensaje final
        $partida['ranking'] = [
            'es_record' => $esRecord,                               // true si superó su marca
            'mejor'     => $mejor,                                  // su mejor puntaje actual
            'posicion'  => $this->modelo->posicionRanking($mejor),  // su puesto en el ranking global
        ];

        // Responde 201 Created con la partida y los datos de ranking
        Respuesta::json($partida, 201);
    }

    // PUT (completo) / PATCH (parcial) /api/partidas/:id -> 200, 400, 401, 403 (solo admin), 404
    public function update(array $params): void
    {
        // Solo administradores pueden modificar partidas (401 sin token, 403 si no es admin)
        Autenticacion::requerirAdmin();

        // Valida el id de la URL
        $id = Peticion::idValido($params['id']);
        if ($id === null) {
            Respuesta::error('El id debe ser un entero positivo', 400);
        }

        // La partida debe existir
        if ($this->modelo->buscarPorId($id) === null) {
            Respuesta::error('Partida no encontrada', 404);
        }

        // Lee el JSON enviado
        $cuerpo = Peticion::cuerpoJson();
        if ($cuerpo === null) {
            Respuesta::error('El cuerpo de la petición no es un JSON válido', 400);
        }

        // PUT reemplaza el recurso entero (los tres campos son obligatorios); PATCH admite solo algunos
        $obligatorios = ($_SERVER['REQUEST_METHOD'] === 'PUT') ? ['puntaje', 'nivel_jugo', 'resultado'] : [];

        // Valida los campos recibidos
        $detalles = [];
        $validos = $this->validarCampos($cuerpo, $obligatorios, $detalles);

        // En PATCH tiene que llegar al menos un campo válido
        if (empty($detalles) && empty($validos)) {
            $detalles['cuerpo'] = 'Enviá al menos uno de estos campos: puntaje, nivel_jugo, resultado';
        }

        // Si algo falló, responde 400
        if (!empty($detalles)) {
            Respuesta::error('Datos inválidos', 400, $detalles);
        }

        // Guarda los cambios
        $this->modelo->actualizar($id, $validos);

        // Responde 200 OK con la partida actualizada
        Respuesta::json($this->modelo->buscarPorId($id), 200);
    }

    // DELETE /api/partidas/:id -> 200 (borrado lógico), 400 id inválido, 401, 403 (solo admin), 404 no existe
    public function destroy(array $params): void
    {
        // Solo administradores pueden eliminar partidas (401 sin token, 403 si no es admin)
        Autenticacion::requerirAdmin();

        // Valida el id de la URL
        $id = Peticion::idValido($params['id']);
        if ($id === null) {
            Respuesta::error('El id debe ser un entero positivo', 400);
        }

        // La partida debe existir y no estar ya borrada
        if ($this->modelo->buscarPorId($id) === null) {
            Respuesta::error('Partida no encontrada', 404);
        }

        // Borrado lógico: la fila queda en la base pero deja de aparecer en la API
        $this->modelo->eliminarLogico($id);

        // Responde 200 OK con un mensaje de confirmación
        Respuesta::json(['mensaje' => 'Partida eliminada (borrado lógico)'], 200);
    }

    // Valida puntaje, nivel_jugo y resultado del cuerpo.
    // $obligatorios: campos que deben venir sí o sí. Los errores se agregan a $detalles.
    // Devuelve solo los campos válidos, ya convertidos al tipo correcto.
    private function validarCampos(array $cuerpo, array $obligatorios, array &$detalles): array
    {
        // Acá se van guardando los campos que pasaron la validación
        $validos = [];

        // ---- puntaje: entero entre 0 y el tope ----
        if (array_key_exists('puntaje', $cuerpo)) {
            $valor = Peticion::entero($cuerpo['puntaje']);
            if ($valor === null || $valor < 0 || $valor > self::PUNTAJE_TOPE) {
                $detalles['puntaje'] = 'puntaje debe ser un entero entre 0 y ' . self::PUNTAJE_TOPE;
            } else {
                $validos['puntaje'] = $valor;
            }
        } elseif (in_array('puntaje', $obligatorios, true)) {
            $detalles['puntaje'] = 'El campo puntaje es obligatorio';
        }

        // ---- nivel_jugo: entero entre 0 y 5 ----
        if (array_key_exists('nivel_jugo', $cuerpo)) {
            $valor = Peticion::entero($cuerpo['nivel_jugo']);
            if ($valor === null || $valor < 0 || $valor > 5) {
                $detalles['nivel_jugo'] = 'nivel_jugo debe ser un entero entre 0 y 5';
            } else {
                $validos['nivel_jugo'] = $valor;
            }
        } elseif (in_array('nivel_jugo', $obligatorios, true)) {
            $detalles['nivel_jugo'] = 'El campo nivel_jugo es obligatorio';
        }

        // ---- resultado: solo "jugo" o "caja_llena" ----
        if (array_key_exists('resultado', $cuerpo)) {
            if (!is_string($cuerpo['resultado']) || !in_array($cuerpo['resultado'], ['jugo', 'caja_llena'], true)) {
                $detalles['resultado'] = 'resultado debe ser "jugo" o "caja_llena"';
            } else {
                $validos['resultado'] = $cuerpo['resultado'];
            }
        } elseif (in_array('resultado', $obligatorios, true)) {
            $detalles['resultado'] = 'El campo resultado es obligatorio';
        }

        // Devuelve solo lo que pasó la validación
        return $validos;
    }
}
