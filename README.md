# OXI-VENTAS

Proyecto Laravel 12 + Inertia + Vue 3 para operacion interna de OXI-VENTAS.

## Requisitos

- PHP 8.3+
- Composer
- Node.js + npm
- MySQL
- Laragon o un stack equivalente en Windows
- `cloudflared` instalado globalmente, o el binario local en `tools/cloudflared.exe`

## Primer arranque

```bash
composer install
npm ci
```

Crear la base de datos local `oxiVentas` en MySQL.

Luego correr:

```bash
php artisan migrate --seed
npm run dev:full
```

## Que hace `npm run dev:full`

El comando prepara el entorno y luego levanta el flujo normal de desarrollo:

- crea `.env` desde `.env.example` si no existe
- genera `APP_KEY` si falta
- levanta Laravel en `127.0.0.1:8000`
- levanta Reverb
- levanta Vite en modo desarrollo

## Que hace `npm run dev:pwa`

Este modo se usa solo para pruebas instalables y para compartir la app por tunnel:

- prepara el entorno igual que `dev:full`
- levanta Laravel en `127.0.0.1:8000`
- levanta Reverb
- compila Vite en modo `build --watch`
- abre un Cloudflare Tunnel para pruebas en mobile y otros equipos

## Notas del entorno

- `.env.example` asume MySQL local en `127.0.0.1:3306`, base `oxiVentas`, usuario `root` y password vacio
- si alguien en el equipo usa otra configuracion de MySQL, entonces si necesitara ajustar su `.env`
- para compartir la app en telefono o tablet, usa la URL `https://...trycloudflare.com` que aparece al correr `npm run dev:pwa`
