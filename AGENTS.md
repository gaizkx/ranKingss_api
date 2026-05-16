# AGENTS.md — Employee Rankings API

Guía para agentes de IA que trabajen en este proyecto. Lee este archivo completo antes de generar o modificar código.

---

## Contexto del proyecto

API REST anónima para la valoración de empleados. Construida con **Symfony 7.4**, **API Platform 4** y **PHP 8.4** sobre **PostgreSQL 18**. El entorno local usa **ddev**.

Los usuarios se identifican con un número de cuenta de 12 dígitos y una contraseña (sin datos personales). El número de cuenta lo proporciona el cliente en el registro. Cada usuario puede emitir hasta 5 rankings por día. Los rankings son inmutables.

---

## Arquitectura y patrones

### API Platform 4 — Patrones obligatorios

- Usar **PHP Attributes** para todas las configuraciones de API Platform. **Nunca** YAML ni XML.
- Las operaciones se declaran con `#[ApiResource]` en la clase de la entidad o del recurso virtual.
- Para lógica personalizada en escritura: implementar `ProcessorInterface` en `src/State/Processor/`.
- Para recursos de solo lectura personalizados: implementar `ProviderInterface` en `src/State/Provider/`.
- Los filtros de colección se aplican con `#[ApiFilter]` o mediante un `ProviderInterface` que procesa `$context['filters']`.

### Doctrine — Convenciones

- Usar **PHP Attributes** para el mapeo ORM. **Nunca** XML ni YAML.
- Todas las fechas se almacenan como `DateTimeImmutable`.
- Claves foráneas con `nullable: false` salvo que se especifique lo contrario.
- Los campos calculados (totalRankings, averageScore) **no son columnas**; se obtienen con DQL en el repositorio.

### Seguridad

- Un único rol: `ROLE_USER`. Todos los usuarios autenticados tienen los mismos permisos.
- Los endpoints de registro (`/register`) y login (`/auth`) son públicos.
- El resto de endpoints **requieren JWT válido**.
- El usuario autenticado se obtiene con `$this->security->getUser()` o inyectando `Security`.
- El filtrado automático de rankings por usuario autenticado se implementa con una **Doctrine Extension** (`QueryCollectionExtensionInterface` + `QueryItemExtensionInterface`).

---

## Entidades

### `src/Entity/User.php`

```php
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use UlidIdTrait {
        __construct as private __generateId;
    }

    #[ORM\Column(length: 12, unique: true)]
    private string $accountNumber;

    #[ORM\Column]
    private string $password;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;
}
```

- `getUserIdentifier()` debe devolver `accountNumber` (no el email).
- **No tiene** `#[ApiResource]`. El registro se maneja mediante un recurso virtual `Register` (ver sección `State Processors`).
- `createdAt` se fija en el constructor de la entidad.

### `src/Entity/Employee.php`

```php
#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            provider: EmployeeCollectionProvider::class,
            output: EmployeeListItem::class,
        ),
        new Get(
            uriTemplate: '/employees/{id}/stats',
            provider: EmployeeStatsProvider::class,
            output: EmployeeStats::class,
        ),
        new Get(
            normalizationContext: ['groups' => ['employee:read']],
        ),
    ],
    paginationEnabled: false,
    security: "is_granted('ROLE_USER')",
)]
class Employee
{
    use UlidIdTrait {
        __construct as private __generateId;
    }

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;
}
```

- **No hay** operación `Post`, `Put`, `Patch` ni `Delete` en esta entidad.
- La colección (`GET /employees`) usa `EmployeeCollectionProvider` y devuelve una lista de `EmployeeListItem` con `totalRankings` y `averageScore`.
- El endpoint `/employees/{id}/stats` es un recurso virtual con `EmployeeStatsProvider`.

### `src/Entity/Ranking.php`

```php
#[ORM\Entity(repositoryClass: RankingRepository::class)]
class Ranking
{
    use UlidIdTrait {
        __construct as private __generateId;
    }

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user; // asignado automáticamente en RankingCreateProcessor

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Employee $employee;

    #[ORM\Column(type: 'smallint')]
    private int $score;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;
}
```

- `createdAt` se fija en `RankingCreateProcessor` como `new DateTimeImmutable()`. **No acepta valor del cliente.**
- `user` se fija en `RankingCreateProcessor` con el usuario autenticado. **No acepta valor del cliente.**

---

## Recursos de API

### `src/ApiResource/EmployeeStats.php`

```php
#[ApiResource(
    uriTemplate: '/employees/{id}/stats',
    operations: [new Get()],
    provider: EmployeeStatsProvider::class,
    security: "is_granted('ROLE_USER')",
)]
class EmployeeStats
{
    public function __construct(
        public readonly string   $id,
        public readonly string   $name,
        public readonly int      $totalScore,
        public readonly int      $rankingCount,
        public readonly float    $averageScore,
        /** @var HeatmapEntry[] */
        public readonly array    $heatmap,
    ) {}
}
```

### `src/ApiResource/EmployeeListItem.php`

```php
final readonly class EmployeeListItem
{
    public function __construct(
        public Ulid  $id,
        public string $name,
        public int    $totalRankings,
        public float  $averageScore,
    ) {}
}
```

### `src/ApiResource/Register.php`

```php
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/register',
            processor: RegisterProcessor::class,
            output: false,
        ),
    ],
)]
class Register
{
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 12)]
    public string $accountNumber;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    public string $password;
}
```

### `src/ApiResource/Ping.php`

```php
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/ping',
            provider: PingProvider::class,
        ),
    ],
)]
class Ping
{
    public string $status = 'ok';
    public ?string $user_id = null;
}
```

### `src/DataTransferObject/HeatmapEntry.php`

```php
final readonly class HeatmapEntry
{
    public function __construct(
        public readonly string $date,        // formato Y-m-d
        public readonly float  $avgScore,
        public readonly int    $rankingCount,
    ) {}
}
```

### `src/State/Provider/EmployeeStatsProvider.php`

Lógica de `provide()`:
1. Extraer `id` de `$uriVariables`.
2. Leer `startDate` y `endDate` de `$context['filters']`. Si `endDate` es null → hoy. Si `startDate` es null → hace 92 días.
3. Validar que el rango no supere 92 días; si supera, lanzar `UnprocessableEntityHttpException`.
4. Validar que el empleado exista; si no, lanzar `NotFoundHttpException`.
5. Llamar a `RankingRepository::findStatsForEmployee($employeeId, $startDate, $endDate)`.
6. Generar el heatmap iterando **cada día del rango** con `DatePeriod`, rellenando con 0 los días sin datos.
7. Retornar instancia de `EmployeeStats`.

---

## State Processors

### `RegisterProcessor`

1. Recibir `accountNumber` (12 dígitos) y `password` del cuerpo de la petición vía `src/ApiResource/Register.php`.
2. Buscar si el `accountNumber` ya existe; si existe, lanzar `ConflictHttpException`.
3. Hashear la contraseña con `UserPasswordHasherInterface`.
4. Persistir con `EntityManagerInterface`.
5. **Response**: `204 No Content` — no devuelve la entidad User.

### `RankingCreateProcessor`

1. Obtener el usuario autenticado via `Security::getUser()`.
2. Contar rankings del usuario en el día natural actual (consulta en `RankingRepository`).
3. Si el conteo ≥ 5: lanzar `UnprocessableEntityHttpException('Límite diario de 5 rankings alcanzado.')`.
4. Asignar `$ranking->setUser($user)` y `$ranking->setCreatedAt(new DateTimeImmutable())`.
5. Persistir y hacer flush.

---

## Repositorios

### `RankingRepository`

Métodos requeridos:

```php
// Cuenta rankings del usuario en el día natural
public function countTodayByUser(User $user): int;

// Rankings del usuario con filtro de fechas (para GET /rankings)
public function findByUserAndDateRange(User $user, DateTimeImmutable $from, DateTimeImmutable $to): array;

// Estadísticas agregadas para EmployeeStatsProvider
public function findStatsForEmployee(Ulid $employeeId, DateTimeImmutable $from, DateTimeImmutable $to): array;
// Retorna: ['totalScore' => int, 'rankingCount' => int, 'byDate' => [['date' => 'Y-m-d', 'avgScore' => float, 'count' => int]]]
```

### `EmployeeRepository`

Métodos requeridos:

```php
// Lista empleados con estadísticas globales (sin filtro de fecha)
public function findAllWithStats(): array;
// Cada elemento: ['id', 'name', 'totalRankings', 'averageScore']
// Implementado con DQL GROUP BY + LEFT JOIN sobre Ranking
```

---

## Filtrado automático de Rankings por usuario

`src/Doctrine/RankingUserExtension.php` implementando:
- `QueryCollectionExtensionInterface`
- `QueryItemExtensionInterface`

Lógica: si la clase raíz del query es `Ranking` y hay usuario autenticado, añadir `AND o.user = :current_user`.

Además aplica filtros de fecha (`startDate`, `endDate`) desde `$context['filters']` y valida el rango máximo de 92 días.

---

## Validación del rango de fechas

Constraint `#[RankingDateRange]` en `src/Validator/RankingDateRange.php`.
Regla: `endDate - startDate <= 92 days` (3 meses aprox).
Aunque la validación también se realiza directamente en `RankingUserExtension` y `EmployeeStatsProvider`.

---

## Comando de consola

### `src/Command/CreateEmployeeCommand.php`

```
ddev console app:employee:create "Nombre del Empleado"
```

- Argumento: `name` (requerido).
- Crea y persiste un `Employee`.
- Imprime el ID asignado tras la creación.

---

## Seguridad — `config/packages/security.yaml`

```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: accountNumber

    firewalls:
        dev:
            pattern: ^/(_profiler|_wdt|assets|build)/
            security: false
        main:
            lazy: true
            provider: app_user_provider
            stateless: true
            json_login:
                check_path: /auth
                username_path: account
                password_path: password
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure
            custom_authenticators:
                - App\Security\OptionalJWTAuthenticator

    access_control:
        - { path: ^/$, roles: PUBLIC_ACCESS }
        - { path: ^/docs, roles: PUBLIC_ACCESS }
        - { path: ^/contexts, roles: PUBLIC_ACCESS }
        - { path: ^/auth, roles: PUBLIC_ACCESS }
        - { path: ^/register, roles: PUBLIC_ACCESS }
        - { path: ^/ping, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

---

## Convenciones de código

- **PHP 8.4**: usar constructor property promotion, readonly properties, enums y typed properties siempre que sea posible.
- **Strict types**: `declare(strict_types=1)` en todos los ficheros PHP.
- **Serialization groups**: usar grupos de serialización explícitos (`ranking:read`, `ranking:write`, `employee:read`, `user:read`). **Nunca** exponer campos sin grupo.
- **Inmutabilidad**: `createdAt` siempre es `DateTimeImmutable`. Los setters de campos que no deben cambiar después de la creación no deben existir o deben ser `private`.
- **No exponer** el campo `user` ni `password` en responses de API (excluir de grupos de normalización).
- **No hay paginación**: las colecciones devuelven todos los resultados. Configurar `pagination_enabled: false` en `api_platform.yaml` o a nivel de operación.

---

## Comandos de desarrollo frecuentes

```bash
# Levantar el entorno
ddev start

# Instalar dependencias
ddev composer install

# Consola de Symfony
ddev console <command>

# Generar migración tras cambios en entidades
ddev console doctrine:migrations:diff

# Aplicar migraciones
ddev console doctrine:migrations:migrate

# Regenerar claves JWT
ddev console lexik:jwt:generate-keypair --overwrite

# Crear empleado
ddev console app:employee:create "Ana García"

# Tests
ddev test

# Rector (PHP CS)
ddev rector

# Consola PostgreSQL
ddev psql
```

---

## Tests

### Preparar la base de datos de test

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
```

---

## Errores comunes a evitar

| Error | Solución |
|---|---|
| Exponer `user` o `password` en la respuesta | Usar grupos de serialización; no incluir esos campos en `ranking:read` ni `user:read` |
| Aceptar `user` o `createdAt` del cliente en POST /rankings | Ignorar esos campos en el DTO/denormalization y asignarlos siempre en el Processor |
| Olvidar el filtrado automático por usuario en rankings | La `RankingUserExtension` debe actuar en **colección e ítem** |
| Generar el heatmap solo con días que tienen datos | Usar `DatePeriod` para iterar cada día del rango y rellenar con 0 los días sin datos |
| No validar el rango de 3 meses en el Provider de stats | Validar también en `EmployeeStatsProvider`, no solo en `RankingUserExtension` |
| Olvidar que el login usa `/auth` (no `/api/auth`) | El endpoint de login es `POST /auth` con `account` y `password` en el body JSON |
| Tener la base de datos de test sin migrar | Ejecutar `ddev console doctrine:database:create --env=test` y `ddev console doctrine:migrations:migrate -n --env=test` |
| Usar `ddev exec bin/console` en vez del alias | Usar `ddev console <command>` |
