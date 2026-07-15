# OXI-VENTAS

Proyecto Laravel 12 + Inertia + Vue 3 para operación interna de OXI-VENTAS.

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

## Qué hace `npm run dev:full`

El comando prepara el entorno y luego levanta el flujo normal de desarrollo:

- crea `.env` desde `.env.example` si no existe
- genera `APP_KEY` si falta
- levanta Laravel en `127.0.0.1:8000`
- levanta Reverb
- levanta Vite en modo desarrollo

## Qué hace `npm run dev:pwa`

Este modo se usa solo para pruebas instalables y para compartir la app por túnel:

- prepara el entorno igual que `dev:full`
- levanta Laravel en `127.0.0.1:8000`
- levanta Reverb
- compila Vite en modo `build --watch`
- abre un Cloudflare Tunnel para pruebas en móviles y otros equipos

## Codificación de archivos

- Todo archivo de texto debe guardarse como UTF-8 sin BOM y con saltos de línea `LF`.
- La configuración compartida de VS Code está en `.vscode/settings.json`.
- `npm run check:encoding` detecta archivos inválidos, marcas BOM y señales comunes de texto corrompido.
- `npm run build` ejecuta esta revisión antes de compilar la interfaz.
- En PowerShell evita leer o escribir código sin indicar UTF-8 cuando uses comandos que puedan cambiar la codificación.

## Notas del entorno

- `.env.example` asume MySQL local en `127.0.0.1:3306`, base `oxiVentas`, usuario `root` y contraseña vacía
- si alguien en el equipo usa otra configuración de MySQL, entonces sí necesitará ajustar su `.env`
- para compartir la app en teléfono o tableta, usa la URL `https://...trycloudflare.com` que aparece al ejecutar `npm run dev:pwa`
