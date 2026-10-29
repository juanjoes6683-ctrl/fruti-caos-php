<?php
// ============================================================================
// controllers/JugadorController.php
// CONTROLADOR de jugadores: recibe la petición, comprueba PERMISOS, VALIDA los datos,
// le pide el trabajo al modelo y responde con el código HTTP correcto. No escribe SQL.
//
// Permisos:
//   POST   /jugadores        pública (es el registro de cuenta)
//   GET    /jugadores        solo administradores
//   GET/PUT/PATCH/DELETE /jugadores/:id   el propio jugador o un administrador
// ============================================================================

class JugadorController
{
    // Modelo que se encarga de la tabla "jugadores"
    private Jugador $modelo;

    // Al crear el controlador se crea también su modelo
    public function __construct()
    {
        $this->modelo = new Jugador();
    }

    // GET /api/jugadores -> 200 con la lista (solo admin), 401 sin token, 403 si no es admin
    public function index(array $params): void
    {
        // Exige token válido y rol admin (si no, responde 401 o 403 y corta)
        Autenticacion::requerirAdmin();

        // Pide la lista al modelo y la responde con 200 OK
        Respuesta::json($this->modelo->listar(), 200);
    }

    // GET /api/jugadores/:id -> 200, 400 id inválido, 401 sin token, 403 sin permiso, 404 no existe
    public function show(array $params): void
    {
        // Exige token válido
        $usuario = Autenticacion::requerirUsuario();

        // Valida que el id de la URL sea un entero positivo
        $id = Peticion::idValido($params['id']);
        if ($id === null) {
            Respuesta::error('El id debe ser un entero positivo', 400);
        }

        // Busca al jugador en la base
        $jugador = $this->modelo->buscarPorId($id);

        // Si no existe, responde 404 Not Found
        if ($jugador === null) {
            Respuesta::error('Jugador no encontrado', 404);
        }

        // Solo el propio jugador o un admin pueden ver este perfil
        Autenticacion::requerirPropietarioOAdmin($usuario, $id);

        // Responde 200 OK con el jugador
        Respuesta::json($jugador, 200);
    }

    // POST /api/jugadores (registro público) -> 201 si se crea, 400 datos inválidos, 409 tag repetido
    public function store(array $params): void
    {
        // Lee el JSON enviado por el cliente
        $cuerpo = Peticion::cuerpoJson();

        // Si el JSON está roto, responde 400 Bad Request
        if ($cuerpo === null) {
            Respuesta::error('El cuerpo de la petición no es un JSON válido', 400);
        }

        // Valida tag y contraseña (ambos obligatorios) y junta los errores que aparezcan
        $detalles = [];
        $tag = $this->leerTag($cuerpo, $detalles, true);
        $password = $this->leerPassword($cuerpo, $detalles, true);

        // Si hubo errores de validación, responde 400 con el detalle de cada campo
        if (!empty($detalles)) {
            Respuesta::error('Datos inválidos', 400, $detalles);
        }

        // Verifica que no exista ya un jugador con ese tag
        if ($this->modelo->buscarPorTag($tag) !== null) {
            // 409 Conflict: el tag ya está registrado
            Respuesta::error('Ese tag ya existe', 409);
        }

        // Convierte la contraseña en hash (nunca se guarda el texto original)
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Intenta crear al jugador
        try {
            $id = $this->modelo->crear($tag, $hash);
        } catch (PDOException $e) {
            // Código 23000 = violación de unicidad (otro usuario creó el mismo tag justo antes)
            if ($e->getCode() === '23000') {
                Respuesta::error('Ese tag ya existe', 409);
            }
            // Cualquier otro error se deja subir: el punto de entrada lo convierte en 500
            throw $e;
        }

        // Responde 201 Created con el jugador recién creado (sin contraseña)
        Respuesta::json($this->modelo->buscarPorId($id), 201);
    }

    // PUT/PATCH /api/jugadores/:id -> 200, 400, 401, 403, 404, 409
    // PUT exige el tag; PATCH acepta tag y/o password (al menos uno)
    public function update(array $params): void
    {
        // Exige token válido
        $usuario = Autenticacion::requerirUsuario();

        // Valida el id de la URL
        $id = Peticion::idValido($params['id']);
        if ($id === null) {
            Respuesta::error('El id debe ser un entero positivo', 400);
        }

        // Verifica que el jugador exista antes de modificarlo
        if ($this->modelo->buscarPorId($id) === null) {
            Respuesta::error('Jugador no encontrado', 404);
        }

        // Solo el propio jugador o un admin pueden modificarlo
        Autenticacion::requerirPropietarioOAdmin($usuario, $id);

        // Lee el JSON enviado
        $cuerpo = Peticion::cuerpoJson();
        if ($cuerpo === null) {
            Respuesta::error('El cuerpo de la petición no es un JSON válido', 400);
        }

        // En PUT el tag es obligatorio; en PATCH es opcional
        $tagObligatorio = ($_SERVER['REQUEST_METHOD'] === 'PUT');

        // Valida los campos recibidos (la contraseña siempre es opcional al modificar)
        $detalles = [];
        $tag = $this->leerTag($cuerpo, $detalles, $tagObligatorio);
        $password = $this->leerPassword($cuerpo, $detalles, false);

        // Si no hubo errores pero tampoco llegó nada para modificar, también es un error
        if (empty($detalles) && $tag === null && $password === null) {
            $detalles['cuerpo'] = 'Enviá al menos uno de estos campos: tag, password';
        }

        // Si algo falló, responde 400
        if (!empty($detalles)) {
            Respuesta::error('Datos inválidos', 400, $detalles);
        }

        // Si cambia el tag, no puede coincidir con el de otro jugador
        if ($tag !== null) {
            $otro = $this->modelo->buscarPorTag($tag);
            if ($otro !== null && $otro['id'] !== $id) {
                Respuesta::error('Ese tag ya lo usa otro jugador', 409);
            }
        }

        // Arma la lista de cambios a guardar
        $campos = [];
        if ($tag !== null) {
            $campos['tag'] = $tag;
        }
        if ($password !== null) {
            $campos['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        // Guarda los cambios en la base
        $this->modelo->actualizar($id, $campos);

        // Responde 200 OK con el jugador ya actualizado
        Respuesta::json($this->modelo->buscarPorId($id), 200);
    }

    // DELETE /api/jugadores/:id -> 200, 400 id inválido, 401, 403, 404
    public function destroy(array $params): void
    {
        // Exige token válido
        $usuario = Autenticacion::requerirUsuario();

        // Valida el id de la URL
        $id = Peticion::idValido($params['id']);
        if ($id === null) {
            Respuesta::error('El id debe ser un entero positivo', 400);
        }

        // Verifica que el jugador exista
        if ($this->modelo->buscarPorId($id) === null) {
            Respuesta::error('Jugador no encontrado', 404);
        }

        // Solo el propio jugador o un admin pueden eliminarlo
        Autenticacion::requerirPropietarioOAdmin($usuario, $id);

        // Lo elimina (sus partidas se borran en cascada)
        $this->modelo->eliminar($id);

        // Responde 200 OK con un mensaje de confirmación
        Respuesta::json(['mensaje' => 'Jugador eliminado junto con sus partidas'], 200);
    }

    // Valida el campo "tag" del cuerpo; devuelve el tag limpio o null y agrega el error a $detalles.
    // $obligatorio indica si el campo debe venir sí o sí.
    private function leerTag(array $cuerpo, array &$detalles, bool $obligatorio): ?string
    {
        // Si el campo no vino: es error solo cuando era obligatorio
        if (!array_key_exists('tag', $cuerpo)) {
            if ($obligatorio) {
                $detalles['tag'] = 'El campo tag es obligatorio';
            }
            return null;
        }

        // Si vino pero no es texto, es error
        if (!is_string($cuerpo['tag'])) {
            $detalles['tag'] = 'El tag debe ser texto';
            return null;
        }

        // Quita espacios al principio y al final
        $tag = trim($cuerpo['tag']);

        // Cuenta los caracteres reales (mb_strlen respeta tildes y emojis)
        $largo = mb_strlen($tag);

        // Debe tener entre 1 y 20 caracteres
        if ($largo < 1 || $largo > 20) {
            $detalles['tag'] = 'El tag debe tener entre 1 y 20 caracteres';
            return null;
        }

        // Devuelve el tag ya validado
        return $tag;
    }

    // Valida el campo "password" del cuerpo; devuelve la contraseña o null y agrega el error a $detalles
    private function leerPassword(array $cuerpo, array &$detalles, bool $obligatoria): ?string
    {
        // Si el campo no vino: es error solo cuando era obligatorio
        if (!array_key_exists('password', $cuerpo)) {
            if ($obligatoria) {
                $detalles['password'] = 'El campo password es obligatorio';
            }
            return null;
        }

        // Si vino pero no es texto, es error
        if (!is_string($cuerpo['password'])) {
            $detalles['password'] = 'La contraseña debe ser texto';
            return null;
        }

        // Largo en bytes: bcrypt solo usa los primeros 72, por eso ese es el máximo
        $largo = strlen($cuerpo['password']);

        // Debe tener entre 6 y 72 caracteres
        if ($largo < 6 || $largo > 72) {
            $detalles['password'] = 'La contraseña debe tener entre 6 y 72 caracteres';
            return null;
        }

        // Devuelve la contraseña tal cual (no se le quitan espacios: son parte de la clave)
        return $cuerpo['password'];
    }
}
