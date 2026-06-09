# Despliegue con Docker

> El desarrollo local de este proyecto se realiza con **ddev** (ver instrucciones en el [README](../README.md)). Los archivos de Docker están pensados para despliegues en producción y para validar la imagen de producción en local.

El stack de contenedores usa [FrankenPHP](https://frankenphp.dev/) + Caddy, basado en [dunglas/symfony-docker](https://github.com/dunglas/symfony-docker).

---

## Deploy local (prueba de la imagen de producción)

`compose.override.yaml` se aplica automáticamente cuando no se especifica fichero de compose. Monta el código fuente como volumen, activa el hot reload de FrankenPHP y habilita Xdebug.

```bash
# Construir la imagen de desarrollo
docker compose build

# Arrancar (aplica compose.yaml + compose.override.yaml automáticamente)
docker compose up --wait
```

La aplicación estará disponible en `https://localhost`.

### Xdebug

Por defecto Xdebug arranca en modo `develop`. Para activar el step debugging:

```bash
XDEBUG_MODE=develop,debug docker compose up --wait
```

---

## Deploy en producción

Para producción hay que pasar explícitamente los dos ficheros de compose:

```bash
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache
```

### Con dominio (HTTPS automático)

Caddy obtiene un certificado TLS de Let's Encrypt automáticamente si `SERVER_NAME` es un nombre de dominio válido con DNS apuntando al servidor.

```bash
SERVER_NAME=tu-dominio.com \
APP_SECRET=<secreto-seguro> \
CADDY_MERCURE_JWT_SECRET=<secreto-jwt-seguro> \
POSTGRES_PASSWORD=<contraseña-segura> \
DEFAULT_URI=https://tu-dominio.com \
docker compose -f compose.yaml -f compose.prod.yaml up --wait
```

### Sin dominio (HTTP)

Cuando no hay dominio, usa `SERVER_NAME=:80` para que Caddy escuche en el puerto 80 del contenedor sin TLS.

```bash
SERVER_NAME=:80 \
APP_SECRET=<secreto-seguro> \
CADDY_MERCURE_JWT_SECRET=<secreto-jwt-seguro> \
POSTGRES_PASSWORD=<contraseña-segura> \
DEFAULT_URI=http://<ip-del-servidor> \
docker compose -f compose.yaml -f compose.prod.yaml up --wait
```

### Sin dominio con puertos custom en el host

`SERVER_NAME` controla el puerto en que Caddy escucha **dentro del contenedor**. `HTTP_PORT` y `HTTPS_PORT` controlan únicamente el puerto publicado **en el host**. Son independientes: no pongas el puerto custom en `SERVER_NAME`.

```bash
# Accesible en http://localhost:4280 y https://localhost:4243
SERVER_NAME=:80 \
APP_SECRET=<secreto-seguro> \
CADDY_MERCURE_JWT_SECRET=<secreto-jwt-seguro> \
POSTGRES_PASSWORD=<contraseña-segura> \
DEFAULT_URI=http://localhost:4280 \
HTTP_PORT=4280 \
HTTPS_PORT=4243 \
HTTP3_PORT=4243 \
docker compose -f compose.yaml -f compose.prod.yaml up --wait
```

> Si pusieras `SERVER_NAME=:4280`, Caddy intentaría escuchar en el puerto 4280 del contenedor, pero Docker enruta el tráfico al puerto 80 del contenedor → respuesta vacía.

---

## Variables de entorno

### Build-time

Estas variables se pasan en tiempo de compilación (`docker compose build`).

| Variable | Default | Descripción |
|---|---|---|
| `SYMFONY_VERSION` | última estable | Versión de Symfony a instalar (ej: `7.4.*`) |
| `STABILITY` | `stable` | Estabilidad del paquete: `dev`, `alpha`, `beta`, `RC`, `stable` |

### Runtime — Aplicación

| Variable | Obligatoria en prod | Default | Descripción |
|---|---|---|---|
| `APP_SECRET` | Sí | — | Clave secreta de Symfony. Generar con `openssl rand -hex 32` |
| `APP_ENV` | No | `prod` en prod, `dev` en override | Entorno de Symfony |

### Runtime — Servidor (Caddy / FrankenPHP)

| Variable | Default | Descripción |
|---|---|---|
| `SERVER_NAME` | `localhost` | Hostname que Caddy escucha dentro del contenedor. Con dominio: `tu-dominio.com`. Sin dominio/TLS: `:80` |
| `HTTP_PORT` | `80` | Puerto HTTP publicado en el host |
| `HTTPS_PORT` | `443` | Puerto HTTPS publicado en el host |
| `HTTP3_PORT` | `443` | Puerto HTTP/3 publicado en el host (UDP) |
| `FRANKENPHP_CONFIG` | — | Directivas extra para el bloque global de FrankenPHP |
| `FRANKENPHP_WORKER_CONFIG` | — | Config del worker (el override de dev lo pone a `watch` para hot reload) |
| `FRANKENPHP_SITE_CONFIG` | — | Directivas extra del sitio en el Caddyfile |
| `CADDY_GLOBAL_OPTIONS` | — | Opciones del bloque global de Caddy |
| `CADDY_EXTRA_CONFIG` | — | Snippets o named-routes extra de Caddy |
| `CADDY_SERVER_EXTRA_DIRECTIVES` | — | Directivas extra del servidor en el Caddyfile |
| `CADDY_SERVER_LOG_OPTIONS` | — | Opciones de log del servidor Caddy |

### Runtime — Mercure

| Variable | Default | Descripción |
|---|---|---|
| `CADDY_MERCURE_JWT_SECRET` | `!ChangeThisMercureHubJWTSecretKey!` | Secreto base para publisher y subscriber JWT. **Cambiar en producción** |
| `MERCURE_PUBLISHER_JWT_KEY` | Derivado de `CADDY_MERCURE_JWT_SECRET` | JWT key para publicadores (sobreescribe el valor derivado) |
| `MERCURE_PUBLISHER_JWT_ALG` | `HS256` | Algoritmo JWT para publicadores |
| `MERCURE_SUBSCRIBER_JWT_KEY` | Derivado de `CADDY_MERCURE_JWT_SECRET` | JWT key para suscriptores (sobreescribe el valor derivado) |
| `MERCURE_SUBSCRIBER_JWT_ALG` | `HS256` | Algoritmo JWT para suscriptores |
| `MERCURE_EXTRA_DIRECTIVES` | — | Directivas Mercure extra (el override de dev añade `demo`) |
| `MERCURE_PUBLIC_URL` | `https://{SERVER_NAME}:{HTTPS_PORT}/.well-known/mercure` | URL pública del hub Mercure |

### Runtime — Base de datos (PostgreSQL)

| Variable | Default | Descripción |
|---|---|---|
| `POSTGRES_VERSION` | `18` | Versión de la imagen PostgreSQL |
| `POSTGRES_DB` | `app` | Nombre de la base de datos |
| `POSTGRES_USER` | `app` | Usuario de la base de datos |
| `POSTGRES_PASSWORD` | **obligatoria en prod** | Sin default en `compose.prod.yaml`: el arranque falla si no se pasa |
| `POSTGRES_CHARSET` | `utf8` | Charset de la conexión |

> `DEFAULT_URI` también está declarada en `compose.prod.yaml` con default `https://example.com`. Pásala explícitamente para que las URLs generadas por Symfony apunten al dominio correcto.

### Runtime — Xdebug (solo imagen de desarrollo)

| Variable | Default | Descripción |
|---|---|---|
| `XDEBUG_MODE` | `develop` | Modo Xdebug. Usar `develop,debug` para step debugging |
