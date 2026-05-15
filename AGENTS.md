# AGENTS.md — Employee Rankings API

Guía para agentes de IA que trabajen en este proyecto. Lee este archivo completo antes de generar o modificar código.

---

## Contexto del proyecto

API REST anónima para la valoración de empleados. Construida con **Symfony 7**, **API Platform 3** y **PHP 8.4** sobre **PostgreSQL 16**. El entorno local usa **ddev**.

Los usuarios se identifican con un número aleatorio de 12 dígitos y una contraseña (sin datos personales). Cada usuario puede emitir hasta 5 rankings por día (UTC). Los rankings son inmutables.

---

## Arquitectura y patrones

### API Platform 3 — Patrones obligatorios

- Usar **PHP Attributes** para todas las configuraciones de API Platform. **Nunca** YAML ni XML.
- Las operaciones se declaran con `#[ApiResource]` en la clase de la entidad o del recurso virtual.
- Para lógica personalizada en escritura: implementar `ProcessorInterface` en `src/State/Processor/`.
- Para recursos de solo lectura personalizados: implementar `ProviderInterface` en `src/State/Provider/`.
- Los filtros de colección se aplican con `#[ApiFilter]` o mediante un `ProviderInterface` que procesa `$context['filters']`.

### Doctrine — Convenciones

- Usar **PHP Attributes** para el mapeo ORM. **Nunca** XML ni YAML.
- Todas las fechas se almacenan como `DateTimeImmutable` en UTC.
- Claves foráneas con `nullable: false` salvo que se especifique lo contrario.
- Los campos calculados (totalRankings, averageScore) **no son columnas**; se obtienen con DQL en el repositorio.

### Seguridad

- Un único rol: `ROLE_USER`. Todos los usuarios autenticados tienen los mismos permisos.
- Los endpoints de registro (`/api/register`) y login (`/api/auth`) son públicos.
- El resto de endpoints **requieren JWT válido**.
- El usuario autenticado se obtiene con `$this->security->getUser()` o inyectando `Security`.
- El filtrado automático de rankings por usuario autenticado se implementa con una **Doctrine Extension** (`QueryCollectionExtensionInterface` + `QueryItemExtensionInterface`).

---

## Entidades

### `src/Entity/User.php`

```php
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/register',
            processor: UserRegistrationProcessor::class,
            // sin JWT
        ),
    ],
    normalizationContext: ['groups' => ['user:read']],
    denormalizationContext: ['groups' => ['user:write']],
)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use Symfony\Component\Uid\Ulid;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private ?Ulid $id = null;

    #[ORM\Column(length: 12, unique: true)]
    private string $accountNumber; // 12 dígitos aleatorios

    #[ORM\Column]
    private string $password;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;
}
```

- `getUserIdentifier()` debe devolver `accountNumber` (no el email).
- El `accountNumber` se genera en `UserRegistrationProcessor`, **no en la entidad**.

### `src/Entity/Employee.php`

```php
#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(), // GET /api/employees
    ],
    normalizationContext: ['groups' => ['employee:read']],
    security: "is_granted('ROLE_USER')",
)]
class Employee
{
    use Symfony\Component\Uid\Ulid;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private ?Ulid $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;
}
```

- **No hay** operación `Post`, `Put`, `Patch` ni `Delete` en esta entidad.
- Los campos `totalRankings` y `averageScore` se añaden como propiedades no mapeadas, populadas desde el repositorio.

### `src/Entity/Ranking.php`

```php
#[ORM\Entity(repositoryClass: RankingRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(), // GET /api/rankings
        new Post(),          // POST /api/rankings
    ],
    normalizationContext: ['groups' => ['ranking:read']],
    denormalizationContext: ['groups' => ['ranking:write']],
    security: "is_granted('ROLE_USER')",
)]
class Ranking
{
    use Symfony\Component\Uid\Ulid;

    #[ORM\Id]
    #[ORM\Column(type: 'ulid', unique: true)]
    private ?Ulid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user; // asignado automáticamente en RankingCreateProcessor

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Employee $employee;

    #[Assert\Range(min: 0, max: 10)]
    #[ORM\Column(type: 'smallint')]
    private int $score;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;
}
```

- `createdAt` se fija en `RankingCreateProcessor` como `new DateTimeImmutable()`. **No acepta valor del cliente.**
- `user` se fija en `RankingCreateProcessor` con el usuario autenticado. **No acepta valor del cliente.**

---

## Recurso virtual: `EmployeeStats`

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
        public readonly int    $employeeId,
        public readonly string $employeeName,
        public readonly int    $totalScore,
        public readonly int    $rankingCount,
        public readonly float  $averageScore,
        /** @var HeatmapEntry[] */
        public readonly array  $heatmap,
    ) {}
}
```

### `src/DataTransferObject/HeatmapEntry.php`

```php
final class HeatmapEntry
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
2. Leer `startDate` y `endDate` de `$context['filters']`. Si `endDate` es null → hoy UTC.
3. Validar que el rango no supere 3 meses; si supera, lanzar `UnprocessableEntityHttpException`.
4. Llamar a `RankingRepository::findStatsForEmployee($employeeId, $startDate, $endDate)`.
5. Generar el heatmap iterando **cada día del rango** con `DatePeriod`.
6. Retornar instancia de `EmployeeStats`.

---

## State Processors

### `UserRegistrationProcessor`

1. Generar `accountNumber`: `str_pad(random_int(0, 999999999999), 12, '0', STR_PAD_LEFT)`.
2. Hashear la contraseña con `UserPasswordHasherInterface`.
3. Persistir con `EntityManagerInterface`.
4. **Response**: `['accountNumber' => ..., 'message' => '...']` — NOT la entidad User.

### `RankingCreateProcessor`

1. Obtener el usuario autenticado via `Security::getUser()`.
2. Contar rankings del usuario en el día natural UTC actual (consulta en `RankingRepository`).
3. Si el conteo ≥ 5: lanzar `UnprocessableEntityHttpException('Límite diario de 5 rankings alcanzado.')`.
4. Asignar `$ranking->setUser($user)` y `$ranking->setCreatedAt(new DateTimeImmutable())`.
5. Persistir y hacer flush.

---

## Repositorios

### `RankingRepository`

Métodos requeridos:

```php
// Cuenta rankings del usuario en el día natural UTC
public function countTodayByUser(User $user): int;

// Rankigs del usuario con filtro de fechas (para GET /api/rankings)
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

Crear `src/Doctrine/RankingUserExtension.php` implementando:
- `QueryCollectionExtensionInterface`
- `QueryItemExtensionInterface`

Lógica: si la clase raíz del query es `Ranking` y hay usuario autenticado, añadir `AND o.user = :current_user`.

---

## Validación del rango de fechas

Crear un constraint reutilizable `#[RankingDateRange]` que se aplica como validación en los filtros de los endpoints `GET /api/rankings` y `GET /api/employees/{id}/stats`.

Regla: `endDate - startDate <= 92 days` (3 meses aprox).

---

## Comando de consola

### `src/Command/CreateEmployeeCommand.php`

```
php bin/console app:employee:create "Nombre del Empleado"
```

- Argumento: `name` (requerido).
- Crea y persiste un `Employee`.
- Imprime el ID asignado tras la creación.

---

## Seguridad — `config/packages/security.yaml`

```yaml
security:
  password_hashers:
    App\Entity\User: bcrypt

  providers:
    app_user_provider:
      entity:
        class: App\Entity\User
        property: accountNumber    # login por número de cuenta

  firewalls:
    login:
      pattern: ^/api/auth
      stateless: true
      json_login:
        check_path: /api/auth
        username_path: account_number
        password_path: password
        success_handler: lexik_jwt_authentication.handler.authentication_success
        failure_handler: lexik_jwt_authentication.handler.authentication_failure

    register:
      pattern: ^/api/register
      stateless: true
      security: false

    api:
      pattern: ^/api
      stateless: true
      jwt: ~

  access_control:
    - { path: ^/api/register, roles: PUBLIC_ACCESS }
    - { path: ^/api/auth,     roles: PUBLIC_ACCESS }
    - { path: ^/api/docs,     roles: PUBLIC_ACCESS }
    - { path: ^/api,          roles: ROLE_USER }
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

# Generar migración tras cambios en entidades
ddev exec bin/console doctrine:migrations:diff

# Aplicar migraciones
ddev exec bin/console doctrine:migrations:migrate

# Regenerar claves JWT
ddev exec bin/console lexik:jwt:generate-keypair --overwrite

# Crear empleado
ddev exec bin/console app:employee:create "Ana García"

# Tests
ddev exec bin/phpunit

# Consola PostgreSQL
ddev psql
```

---

## Errores comunes a evitar

| Error | Solución |
|---|---|
| Exponer `user` o `password` en la respuesta | Usar grupos de serialización; no incluir esos campos en `ranking:read` ni `user:read` |
| Aceptar `user` o `createdAt` del cliente en POST /rankings | Ignorar esos campos en el DTO/denormalization y asignarlos siempre en el Processor |
| Olvidar el filtrado automático por usuario en rankings | La `RankingUserExtension` debe actuar en **colección e ítem** |
| Calcular fechas en timezone local | Usar siempre `new DateTimeZone('UTC')` explícitamente |
| Generar el heatmap solo con días que tienen datos | Usar `DatePeriod` para iterar cada día del rango y rellenar con 0 los días sin datos |
| No validar el rango de 3 meses en el Provider de stats | Validar también en `EmployeeStatsProvider`, no solo en el endpoint de rankings |
| Procesar fechas en PHP en vez de con funciones DQL custom | Mantener DQL estándar; el agrupado y formateo de fechas se hace con `DateTimeInterface::format()` en PHP |
