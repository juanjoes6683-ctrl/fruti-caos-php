# FRUTI-CAOS — API REST (PHP + MySQL, patrón MVC, autenticación JWT)

Backend del juego FRUTI-CAOS. El juego (HTML/CSS/JS) se comunica con la API de forma asíncrona (`fetch`) y todas las respuestas son JSON.

## Requisitos
- WAMP (Apache con `mod_rewrite` activo, PHP 7.4 o superior con `pdo_mysql` y `mbstring`, MySQL/MariaDB)
- Postman (para las pruebas)

## Puesta en marcha
1. Copiá esta carpeta dentro de `www` (ej: `C:\wamp64\www\fruti-caos-php`).
2. En phpMyAdmin → **Importar** → `database/fruti_caos.sql`. Crea la base, las dos tablas y datos de prueba.
   - Si ya habías importado la versión anterior (sin autenticación), importá en cambio `database/migracion_autenticacion.sql` (una sola vez): agrega las columnas de contraseña y rol.
3. Copiá `.env.example` como `.env` y completá tus datos de MySQL.
4. **Generá tu `JWT_SECRET`** (mínimo 32 caracteres) y pegalo en el `.env`. Con PHP en consola: `php -r "echo bin2hex(random_bytes(32));"`. Mientras tenga menos de 32 caracteres, la API responde 500 en las rutas con token.
5. Abrí `http://localhost/fruti-caos-php/`, tocá **Crear cuenta** y jugá.

Si la API devuelve 404 en todo, revisá que `mod_rewrite` esté activo en WAMP y que el directorio permita `AllowOverride All`.

### Crear un administrador
No hay ninguno por defecto (para no dejar contraseñas en el código). Registrá una cuenta normal y ascendela con SQL:
```sql
UPDATE jugadores SET rol = 'admin' WHERE tag = 'TuTag';
```

## Autenticación (JWT)
1. Registro: `POST /api/jugadores` con `{"tag": "Ana", "password": "mi-clave"}` (la contraseña se guarda como hash con `password_hash`, nunca en texto plano ni en las respuestas).
2. Login: `POST /api/auth/login` con tag y password → devuelve `{"token": "...", "tipo": "Bearer", "expira_en": ..., "jugador": {...}}`.
3. En cada petición protegida se envía el encabezado `Authorization: Bearer <token>`.
- El token es un JWT firmado con HS256 usando `JWT_SECRET`; dura `JWT_TTL_HORAS` horas (8 por defecto). En cada petición la API vuelve a leer al jugador en la base, así que si se elimina su cuenta o cambia su rol, el token deja de valer o se ajusta enseguida.
- **401 Unauthorized**: falta el token, es inválido o venció (o credenciales incorrectas en el login). **403 Forbidden**: el token es válido pero no tenés permiso.
- Cerrar sesión = descartar el token en el cliente (el JWT es sin estado; el juego lo guarda solo en memoria).

## Endpoints y permisos

| Método | Ruta | Quién puede | Códigos |
|---|---|---|---|
| POST | `/api/jugadores` | Público (registro) | 201 · 400 · 409 |
| POST | `/api/auth/login` | Público | 200 · 400 · 401 |
| GET | `/api/auth/yo` | Token válido | 200 · 401 |
| GET | `/api/ranking` (`?limite=10`) | Público | 200 · 400 |
| GET | `/api/jugadores` | Solo admin | 200 · 401 · 403 |
| GET | `/api/jugadores/:id` | Dueño o admin | 200 · 400 · 401 · 403 · 404 |
| PUT / PATCH | `/api/jugadores/:id` | Dueño o admin | 200 · 400 · 401 · 403 · 404 · 409 |
| DELETE | `/api/jugadores/:id` | Dueño o admin | 200 · 400 · 401 · 403 · 404 |
| GET | `/api/partidas` (`?jugador_id=`) | Jugador: solo las suyas · Admin: todas | 200 · 400 · 401 · 403 |
| POST | `/api/partidas` | Token válido (se guarda a nombre del dueño del token) | 201 · 400 · 401 · 403 |
| GET | `/api/partidas/:id` | Dueño o admin | 200 · 400 · 401 · 403 · 404 |
| PUT / PATCH | `/api/partidas/:id` | Solo admin | 200 · 400 · 401 · 403 · 404 |
| DELETE | `/api/partidas/:id` (borrado lógico) | Solo admin | 200 · 400 · 401 · 403 · 404 |

Otros códigos: `405` (método no permitido en una ruta existente), `404` (ruta inexistente), `500` (error interno). Los errores siempre tienen la forma `{"error": "...", "detalles": {"campo": "motivo"}}`.

## Estructura (MVC)
```
.env.example            plantilla de variables de entorno (el .env real NO se sube a git)
.gitignore              ignora .env, logs y archivos del sistema
.htaccess               reescribe /api/*, pasa el encabezado Authorization y protege carpetas internas y .env
api/index.php           punto de entrada único: carga todo, aplica CORS y despacha la ruta
config/                 env.php (lee .env) · database.php (conexión PDO) · cors.php
routes/                 Router.php · auth.php · jugadores.php · partidas.php · ranking.php
controllers/            Auth / Jugador / Partida / Ranking: validan, comprueban permisos y eligen el código HTTP
models/                 ÚNICA capa con SQL: Jugador.php · Partida.php
helpers/                Respuesta.php (la "vista" JSON) · Peticion.php · Jwt.php · Autenticacion.php
database/               fruti_caos.sql (esquema + datos) · migracion_autenticacion.sql
postman/                colección con todas las pruebas
index.html · script.js · estilos.css · imagenes/   el juego (cliente)
```

## Base de datos (2 tablas relacionadas)
- `jugadores` (id, tag único, password_hash, rol, creado_en)
- `partidas` (id, **jugador_id → jugadores.id**, puntaje, nivel_jugo 0-5, resultado, creado_en, eliminado_en)

## Decisiones de diseño
- **Solo el admin modifica o borra partidas**: si cada jugador pudiera editar su puntaje con PUT/PATCH, el ranking no tendría sentido. Los jugadores solo crean partidas (a su nombre) y las consultan.
- **Los jugadores de ejemplo del SQL no tienen contraseña**: no se puede iniciar sesión con ellos, solo sirven para llenar el ranking.
- **Login con mensaje genérico** ("Tag o contraseña incorrectos") para no revelar si un tag existe.
- **Partidas: borrado lógico** (`eliminado_en`). **Jugadores: borrado físico** con `ON DELETE CASCADE`.
- **Seguridad:** consultas preparadas, credenciales y clave JWT solo en `.env`, `.env` bloqueado por `.htaccess`, CORS configurable con `CORS_ORIGIN` (el encabezado `Authorization` está permitido).
- **Límites conocidos:** no hay límite de intentos de login (fuerza bruta), ni tokens de refresco, ni revocación de tokens antes de que venzan. En producción conviene usar HTTPS.

## Pruebas con Postman (evidencia)
1. Importá `postman/fruti-caos.postman_collection.json`.
2. Revisá la variable `baseUrl` (por defecto `http://localhost/fruti-caos-php/api`).
3. Para la carpeta **5. Administrador**: creá un admin (ver arriba) y completá las variables `adminTag` y `adminPassword` de la colección. Si no lo hacés, destildá esa carpeta en el Runner.
4. Ejecutá la colección con **Run collection** (en orden): cada request trae sus tests automáticos y la colección crea y limpia sus propios jugadores de prueba.
5. Sacá capturas del Runner y de las respuestas 200, 201, 400, 401, 403 y 404 para el entregable.
