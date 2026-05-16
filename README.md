# Employee Rankings API

API REST anónima para la valoración de empleados, construida con Symfony y API Platform.

## Stack tecnológico

| Componente | Tecnología |
|---|---|
| Lenguaje | PHP 8.4 |
| Framework | Symfony 7.x |
| API layer | API Platform 3.x |
| ORM | Doctrine (PostgreSQL 16) |
| Autenticación | JWT (`lexik/jwt-authentication-bundle`) |
| Entorno local | ddev |

## Filosofía de diseño

Los usuarios se registran de forma **completamente anónima**: el sistema genera un número aleatorio de 12 dígitos que actúa como identificador único. No se almacena ningún dato personal.

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
ddev exec console lexik:jwt:generate-keypair

# 4. Ejecutar migraciones
ddev exec console doctrine:migrations:migrate

# 5. (Opcional) Cargar fixtures de desarrollo
ddev exec console doctrine:fixtures:load
```

La API estará disponible en `https://rankingss.ddev.site/api`.  
La documentación interactiva (Swagger UI) en `https://rankingss.ddev.site/api/docs`.

---

## Configuración ddev

`.ddev/config.yaml`:

```yaml
name: rankingss
type: symfony
php_version: "8.4"
webserver_type: nginx-fpm
database:
  type: postgres
  version: "16"
```

---

## Variables de entorno

Copia `.env` en `.env.local` y ajusta los valores:

```dotenv
DATABASE_URL="postgresql://db:db@db:5432/db?serverVersion=16&charset=utf8"
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=change_me
```

---

## Estructura del proyecto

```
src/
├── ApiResource/
│   └── EmployeeStats.php               # Recurso virtual (sin entidad DB)
├── Command/
│   └── CreateEmployeeCommand.php       # app:employee:create
├── DataTransferObject/
│   └── HeatmapEntry.php                # {date, avgScore, rankingCount}
├── Entity/
│   ├── Employee.php
│   ├── Ranking.php
│   └── User.php
├── Repository/
│   ├── EmployeeRepository.php
│   ├── RankingRepository.php           # Consultas de estadísticas y filtros
│   └── UserRepository.php
├── State/
│   ├── Provider/
│   │   └── EmployeeStatsProvider.php   # Calcula estadísticas en tiempo real
│   └── Processor/
│       ├── UserRegistrationProcessor.php  # Genera número + hashea contraseña
│       └── RankingCreateProcessor.php     # Valida límite diario + asigna usuario
└── Validator/
    ├── DailyRankingLimit.php
    ├── DailyRankingLimitValidator.php
    ├── RankingDateRange.php
    └── RankingDateRangeValidator.php
```

---

## Entidades

### `User`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | `ulid` (PK) | Clave primaria interna |
| `accountNumber` | `string(12)` (unique) | Número anónimo de 12 dígitos (usado en el login) |
| `password` | `string` | Contraseña hasheada (bcrypt) |
| `createdAt` | `DateTimeImmutable` | Fecha de registro |

### `Employee`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | `ulid` (PK) | Clave primaria |
| `name` | `string(255)` | Nombre del empleado |
| `createdAt` | `DateTimeImmutable` | Fecha de alta |

### `Ranking`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | `ulid` (PK) | Clave primaria |
| `user` | `ManyToOne(User)` | Usuario que emite la valoración |
| `employee` | `ManyToOne(Employee)` | Empleado valorado |
| `score` | `int` (0–10) | Puntuación |
| `createdAt` | `DateTimeImmutable` | Fecha de creación (inmutable) |

### `EmployeeStats` *(recurso virtual, sin tabla DB)*

| Campo | Tipo | Descripción |
|---|---|---|
| `employeeId` | `ulid` | ID del empleado |
| `employeeName` | `string` | Nombre del empleado |
| `totalScore` | `int` | Suma de todas las puntuaciones |
| `rankingCount` | `int` | Número total de rankings |
| `averageScore` | `float` | Media score/ranking |
| `heatmap` | `HeatmapEntry[]` | Todos los días del rango con su media y cantidad |

---

## API Endpoints

### Autenticación

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| `POST` | `/api/register` | Crear cuenta anónima | ❌ |
| `POST` | `/api/auth` | Obtener JWT | ❌ |

#### `POST /api/register`

```json
// Request
{
  "password": "mi_contraseña_segura"
}

// Response 201
{
  "accountNumber": "847392019384",
  "message": "Cuenta creada. Guarda tu número de cuenta, no podrás recuperarlo."
}
```

#### `POST /api/auth`

```json
// Request
{
  "account_number": "847392019384",
  "password": "mi_contraseña_segura"
}

// Response 200
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJS..."
}
```

---

### Empleados

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| `GET` | `/api/employees` | Listar todos los empleados con estadísticas básicas | ✅ JWT |

#### `GET /api/employees`

```json
// Response 200
[
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
]
```

> Los campos `totalRankings` y `averageScore` se calculan mediante una consulta DQL agregada en `EmployeeRepository`, no se persisten en base de datos.

---

### Rankings

| Método | Ruta | Descripción | Auth |
|---|---|---|---|
| `GET` | `/api/rankings` | Listar rankings del usuario autenticado | ✅ JWT |
| `POST` | `/api/rankings` | Crear un nuevo ranking | ✅ JWT |

#### `GET /api/rankings`

| Query param | Tipo | Requerido | Default | Descripción |
|---|---|---|---|---|
| `startDate` | `YYYY-MM-DD` | ✅ | — | Fecha inicio del rango |
| `endDate` | `YYYY-MM-DD` | ❌ | Hoy | Fecha fin del rango |

> ⚠️ Entre `startDate` y `endDate` no pueden pasar más de **3 meses**. Si se supera, se devuelve `HTTP 422`.

```json
// GET /api/rankings?startDate=2026-03-01
[
  {
    "id": "01J9Z4X2Y5ABCDEFGHIJKLMNOP",
    "employee": { "id": "01J9Z4X2Y5ABCDEFGHIJKLMNOQ", "name": "Carlos López" },
    "score": 8,
    "createdAt": "2026-03-15T10:30:00+00:00"
  }
]
```

#### `POST /api/rankings`

```json
// Request
{
  "employee": "/api/employees/01J9Z4X2Y5ABCDEFGHIJKLMNOQ",
  "score": 8
}

// Response 201
{
  "id": "01J9Z4X2Y5ABCDEFGHIJKLMNOP",
  "employee": { "id": "01J9Z4X2Y5ABCDEFGHIJKLMNOQ", "name": "Carlos López" },
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
| `GET` | `/api/employees/{id}/stats` | Estadísticas completas de un empleado | ✅ JWT |

| Query param | Tipo | Requerido | Default | Descripción |
|---|---|---|---|---|
| `startDate` | `YYYY-MM-DD` | ✅ | — | Fecha inicio del rango |
| `endDate` | `YYYY-MM-DD` | ❌ | Hoy | Fecha fin del rango |

> ⚠️ Misma restricción de 3 meses que en rankings.  
> El heatmap incluye **todos los días del rango**, incluso los que no tienen rankings (con `avgScore: 0.0` y `rankingCount: 0`).

```json
// GET /api/employees/01J9Z4X2Y5ABCDEFGHIJKLMNOQ/stats?startDate=2026-03-01&endDate=2026-03-05
{
  "employeeId": "01J9Z4X2Y5ABCDEFGHIJKLMNOQ",
  "employeeName": "Carlos López",
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

---

## Reglas de negocio

1. **Anonimato total**: el número de cuenta de 12 dígitos se genera aleatoriamente. No hay mecanismo de recuperación de cuenta ni de contraseña.
2. **Sin datos personales**: la API no almacena nombre, email ni ningún dato identificativo del usuario.
3. **Límite diario de rankings**: máximo **5 rankings por usuario por día natural**. El mismo empleado puede valorarse más de una vez dentro de ese límite.
4. **Rankings inmutables**: una vez creado, un ranking no puede ser modificado ni eliminado.
5. **Visibilidad restringida**: un usuario solo puede consultar **sus propios rankings** (filtrado automático por el usuario autenticado).
6. **Gestión de empleados offline**: los empleados solo pueden crearse/modificarse mediante el comando de consola `app:employee:create`. No existe endpoint de escritura para empleados.
7. **Rango de fechas máximo**: la diferencia entre `startDate` y `endDate` no puede superar los **3 meses** (92 días).
8. **Estadísticas computadas**: las estadísticas de empleado no se persisten en base de datos; se calculan mediante consultas DQL en `EmployeeStatsProvider` en cada petición.

---

## Comandos útiles

```bash
# Crear un empleado
ddev exec bin/console app:employee:create "Nombre Apellido"

# Generar/regenerar claves JWT
ddev exec bin/console lexik:jwt:generate-keypair --overwrite

# Crear y ejecutar migraciones
ddev exec bin/console doctrine:migrations:diff
ddev exec bin/console doctrine:migrations:migrate

# Ejecutar tests
ddev exec bin/phpunit

# Abrir consola PostgreSQL
ddev psql

# Ver logs del servidor
ddev logs
```

---

## Dependencias principales

```json
{
  "require": {
    "php": "^8.4",
    "api-platform/core": "^3.3",
    "doctrine/dbal": "^3.9",
    "doctrine/orm": "^3.3",
    "lexik/jwt-authentication-bundle": "^3.1",
    "symfony/flex": "^2",
    "symfony/framework-bundle": "^7.2",
    "symfony/password-hasher": "^7.2",
    "symfony/security-bundle": "^7.2",
    "symfony/serializer": "^7.2",
    "symfony/validator": "^7.2"
  },
  "require-dev": {
    "doctrine/doctrine-fixtures-bundle": "^3.6",
    "symfony/maker-bundle": "^1.62",
    "symfony/test-pack": "^1.1"
  }
}
```
