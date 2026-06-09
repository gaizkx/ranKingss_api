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

## Documentación

- [Despliegue con Docker (producción)](docs/deploy.md)

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

La API estará disponible en `https://rankingss.ddev.site/api`.  
La documentación interactiva (Swagger UI) en `https://rankingss.ddev.site/docs`.

---

## Variables de entorno

No es necesario crear `.env.local`. El entorno ddev inyecta automáticamente las variables. Si necesitas personalizar algo, copia `.env` en `.env.local` y ajusta:

```dotenv
DATABASE_URL="postgresql://db:db@db:5432/db?serverVersion=18&charset=utf8"
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=change_me
```

---

## Comandos del proyecto

```bash
# Consola de Symfony (alias para ddev exec bin/console)
ddev console <command>

# Tests
ddev test

# Rector (PHP CS)
ddev rector

# Crear un empleado
ddev console app:employee:create "Nombre Apellido"

# Generar/regenerar claves JWT
ddev console lexik:jwt:generate-keypair --overwrite

# Crear y ejecutar migraciones
ddev console doctrine:migrations:diff
ddev console doctrine:migrations:migrate

# Abrir consola PostgreSQL
ddev psql

# Ver logs del servidor
ddev logs
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

## Estructura del proyecto

```
src/
├── ApiResource/
│   ├── EmployeeListItem.php          # DTO para GET /employees
│   ├── EmployeeStats.php             # Recurso virtual (sin entidad DB)
│   ├── Ping.php                      # Recurso para GET /ping
│   └── Register.php                  # DTO para POST /register
├── Command/
│   └── CreateEmployeeCommand.php     # app:employee:create
├── DataFixtures/
│   └── AppFixtures.php
├── DataTransferObject/
│   └── HeatmapEntry.php              # {date, avgScore, rankingCount}
├── Doctrine/
│   └── RankingUserExtension.php      # Filtra rankings por usuario autenticado
├── Entity/
│   ├── Employee.php
│   ├── Ranking.php
│   ├── UlidIdTrait.php
│   └── User.php
├── Repository/
│   ├── EmployeeRepository.php
│   ├── RankingRepository.php         # Consultas de estadísticas y filtros
│   └── UserRepository.php
├── Security/
│   └── OptionalJWTAuthenticator.php  # Permite JWT opcional en /ping
├── State/
│   ├── PingProvider.php
│   ├── RegisterProcessor.php         # Crea usuario + hashea contraseña
│   ├── Processor/
│   │   └── RankingCreateProcessor.php # Valida límite diario + asigna usuario
│   └── Provider/
│       ├── EmployeeCollectionProvider.php  # Lista empleados con stats
│       └── EmployeeStatsProvider.php       # Calcula estadísticas en tiempo real
└── Validator/
    ├── RankingDateRange.php
    └── RankingDateRangeValidator.php
tests/
├── Command/
│   └── CreateEmployeeCommandTest.php
├── Repository/
│   ├── EmployeeRepositoryTest.php
│   ├── RankingRepositoryTest.php
│   ├── RepositoryTestCase.php        # Trait con helpers para tests
│   └── UserRepositoryTest.php
├── EmployeeStatsTest.php
├── EmployeeTest.php
├── PingTest.php
├── RankingTest.php
├── RegisterTest.php
└── bootstrap.php
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

---

## Reglas de negocio

1. **Anonimato total**: el número de cuenta de 12 dígitos se proporciona en el registro. No hay mecanismo de recuperación de cuenta ni de contraseña.
2. **Sin datos personales**: la API no almacena nombre, email ni ningún dato identificativo del usuario.
3. **Límite diario de rankings**: máximo **5 rankings por usuario por día natural**. El mismo empleado puede valorarse más de una vez dentro de ese límite.
4. **Rankings inmutables**: una vez creado, un ranking no puede ser modificado ni eliminado.
5. **Visibilidad restringida**: un usuario solo puede consultar **sus propios rankings** (filtrado automático por el usuario autenticado).
6. **Gestión de empleados offline**: los empleados solo pueden crearse mediante el comando de consola `app:employee:create`. No existe endpoint de escritura para empleados.
7. **Rango de fechas máximo**: la diferencia entre `startDate` y `endDate` no puede superar los **3 meses** (92 días).
8. **Estadísticas computadas**: las estadísticas de empleado no se persisten en base de datos; se calculan mediante consultas DQL en cada petición.
