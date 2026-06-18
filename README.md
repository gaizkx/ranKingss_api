# Employee Rankings API

API REST anónima para la valoración de empleados, construida con Symfony y API Platform.

## Stack tecnológico

| Componente | Tecnología |
|---|---|
| Lenguaje | PHP 8.4 |
| Framework | Symfony 7.4 |
| API layer | API Platform 4 |
| ORM | Doctrine (PostgreSQL 18) |
| Autenticación | JWT (`lexik/jwt-authentication-bundle`) |
| Entorno local | ddev |

## Filosofía de diseño

Los usuarios se registran con un número de cuenta de 12 dígitos (proporcionado por el cliente) y una contraseña. No se almacena ningún dato personal.

---

## Prerrequisitos

- [ddev](https://ddev.readthedocs.io/) ≥ 1.25

---

## Instalación y puesta en marcha

```bash
# 1. Iniciar el entorno ddev
ddev start

# 2. Instalar dependencias PHP
ddev composer install

# 3. Generar claves JWT
ddev console lexik:jwt:generate-keypair

# 4. Ejecutar migraciones
ddev console doctrine:migrations:migrate

# 5. (Opcional) Cargar fixtures de desarrollo
ddev console doctrine:fixtures:load
```

La API estará disponible en la raíz `https://rankingss.ddev.site/` (los recursos
cuelgan directamente, p.ej. `/employees`, `/rankings`; no hay prefijo `/api`).  
La documentación interactiva (Swagger UI) en `https://rankingss.ddev.site/docs`.

---

## Despliegue (imagen Docker de producción)

La imagen (`Dockerfile` → `compose.yaml`) es distroless y corre con `APP_ENV=prod`.
La Swagger UI está **deshabilitada en producción** por diseño (regla de negocio); los
endpoints REST siguen disponibles bajo las rutas documentadas más abajo.

**Secretos requeridos** (la imagen no los hornea; `docker compose` falla si faltan):

```bash
# Secreto de aplicación de Symfony
export APP_SECRET="$(openssl rand -hex 16)"

# Passphrase del par de claves JWT
export JWT_PASSPHRASE="<la-passphrase-de-tus-claves>"
```

**Claves JWT**: se montan en runtime vía el volumen `./config/jwt` (read-only), no
viven dentro de la imagen. Genera el par una sola vez y persístelo en el entorno de
despliegue:

```bash
ddev console lexik:jwt:generate-keypair    # o: php bin/console ... en el host
```

```bash
docker compose build app
docker compose up -d
```

---

## Comandos `app:`

```bash
# Crear un empleado (única forma de añadir empleados)
ddev console app:employee:create "Nombre Apellido"
```

---

## Tests

### Preparar la base de datos de test

El test environment usa `dbname_suffix: _test` en `doctrine.yaml`, por lo que la base de datos se llama `db_test`.

```bash
# Asegurar que existe y está migrada
ddev console doctrine:database:create --env=test
ddev console doctrine:migrations:migrate -n --env=test
```

### Ejecutar tests

```bash
ddev test
```

Con filtro:

```bash
ddev test --filter=RegisterTest
ddev test --filter=testRegisterCreatesUserAndCanLogin
```

---

## API Endpoints

### Autenticación

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| `POST` | `/register` | Crear cuenta anónima | ❌ |
| `POST` | `/auth` | Obtener JWT | ❌ |
| `GET` | `/ping` | Health check | ❌ (JWT opcional) |

#### `POST /register`

```json
// Request
{
  "accountNumber": "847392019384",
  "password": "mi_contraseña_segura"
}

// Response 204 No Content
```

#### `POST /auth`

```json
// Request
{
  "account": "847392019384",
  "password": "mi_contraseña_segura"
}

// Response 200
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJS..."
}
```

#### `GET /ping`

```json
// Response 200 (sin autenticación)
{
  "status": "ok"
}

// Response 200 (con JWT válido)
{
  "status": "ok",
  "user_id": "847392019384"
}
```

---

### Empleados

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| `GET` | `/employees` | Listar todos los empleados con estadísticas básicas | ✅ JWT |

#### `GET /employees`

```json
// Response 200 (JSON-LD)
{
  "@context": "/contexts/Employee",
  "@id": "/employees",
  "@type": "hydra:Collection",
  "member": [
    {
      "id": "01J9Z4X2Y5ABCDEFGHIJKLMNOP",
      "name": "Ana García",
      "totalRankings": 42,
      "averageScore": 7.8
    },
    {
      "id": "01J9Z4X2Y5ABCDEFGHIJKLMNOQ",
      "name": "Carlos López",
      "totalRankings": 35,
      "averageScore": 6.5
    }
  ],
  "totalItems": 2
}
```

> Los campos `totalRankings` y `averageScore` se calculan mediante una consulta DQL agregada en `EmployeeRepository`, no se persisten en base de datos.

---

### Rankings

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| `GET` | `/rankings` | Listar rankings del usuario autenticado | ✅ JWT |
| `POST` | `/rankings` | Crear un nuevo ranking | ✅ JWT |

#### `GET /rankings`

| Query param | Tipo | Requerido | Default | Descripción |
|---|---|---|---|---|
| `startDate` | `YYYY-MM-DD` | ❌ | — | Fecha inicio del rango |
| `endDate` | `YYYY-MM-DD` | ❌ | Hoy | Fecha fin del rango |

> ⚠️ Si se proporciona `startDate`, la diferencia con `endDate` no puede superar los **3 meses** (92 días). Si se supera, se devuelve `HTTP 422`.

```json
// GET /rankings?startDate=2026-03-01
{
  "@context": "/contexts/Ranking",
  "@id": "/rankings",
  "@type": "hydra:Collection",
  "member": [
    {
      "@id": "/rankings/01J9Z4X2Y5ABCDEFGHIJKLMNOP",
      "@type": "Ranking",
      "employee": "/employees/01J9Z4X2Y5ABCDEFGHIJKLMNOQ",
      "score": 8,
      "createdAt": "2026-03-15T10:30:00+00:00"
    }
  ],
  "totalItems": 1
}
```

#### `POST /rankings`

```json
// Request
{
  "employee": "/employees/01J9Z4X2Y5ABCDEFGHIJKLMNOQ",
  "score": 8
}

// Response 201
{
  "@context": "/contexts/Ranking",
  "@id": "/rankings/01J9Z4X2Y5ABCDEFGHIJKLMNOP",
  "@type": "Ranking",
  "employee": "/employees/01J9Z4X2Y5ABCDEFGHIJKLMNOQ",
  "score": 8,
  "createdAt": "2026-05-14T10:30:00+00:00"
}
```

> ⚠️ Máximo **5 rankings por día natural** por usuario. Si se supera, se devuelve `HTTP 422` con mensaje de error.  
> ✅ Se puede rankear al mismo empleado varias veces en el mismo día (dentro del límite).

---

### Estadísticas de empleado

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| `GET` | `/employees/{id}/stats` | Estadísticas completas de un empleado | ✅ JWT |

| Query param | Tipo | Requerido | Default | Descripción |
|---|---|---|---|---|
| `startDate` | `YYYY-MM-DD` | ❌ | Hace 92 días | Fecha inicio del rango |
| `endDate` | `YYYY-MM-DD` | ❌ | Hoy | Fecha fin del rango |

> ⚠️ Si se proporcionan ambas fechas, la diferencia no puede superar los **3 meses** (92 días).  
> El heatmap incluye **todos los días del rango**, incluso los que no tienen rankings (con `avgScore: 0.0` y `rankingCount: 0`).

```json
// GET /employees/01J9Z4X2Y5ABCDEFGHIJKLMNOQ/stats?startDate=2026-03-01&endDate=2026-03-05
{
  "id": "01J9Z4X2Y5ABCDEFGHIJKLMNOQ",
  "name": "Carlos López",
  "totalScore": 34,
  "rankingCount": 5,
  "averageScore": 6.80,
  "heatmap": [
    { "date": "2026-03-01", "avgScore": 0.0,  "rankingCount": 0 },
    { "date": "2026-03-02", "avgScore": 8.5,  "rankingCount": 2 },
    { "date": "2026-03-03", "avgScore": 0.0,  "rankingCount": 0 },
    { "date": "2026-03-04", "avgScore": 7.0,  "rankingCount": 2 },
    { "date": "2026-03-05", "avgScore": 3.0,  "rankingCount": 1 }
  ]
}
```

