# Inventario y almacén: cuatro etapas y activación

Este paquete reúne cuatro etapas. La publicación del código, su instalación en Hostinger y las pruebas con lector físico son comprobaciones distintas. El respaldo SQL, credenciales, exportaciones de accesos y datos del laboratorio no forman parte de GitHub.

| Etapa | Qué cambia | Archivos principales | Qué validar |
|---|---|---|---|
| 1. Integridad | Sumar/restar sobre saldo vigente, rechazar capturas antiguas y reconocer reenvíos | `StockMovementService.php`, `ProductBatchController.php`, `ProductController.php`, `PreventDuplicateInventoryOperation.php` | Dos personas agregan stock; ambas aportaciones quedan registradas. Reenviar no duplica. |
| 2. Consultas | Filtrar productos antes de construir resultados y consultar historial por páginas | `InventoryProductSqlPrefilter.php`, `InventoryReadService.php`, controladores de productos/almacén | Misma búsqueda y resultados, páginas del historial navegables, filtros y totales correctos. |
| 3. Colaboración | Agrupar avisos y conservar el contexto de cada pantalla | `inventoryRealtimeBatch.mjs`, `Home.vue`, `useBranchInventory.js`, `realtime.js` | Las otras sesiones ven cambios conservando formularios, filtros y búsquedas; reconectar actualiza datos. |
| 4. Segundo plano | Guardar, procesar y recuperar avisos e indexación pendientes | `InventoryOutbox.php`, `InventoryTaskProcessor.php`, comandos `inventory:*` | Un fallo de avisos deja el guardado confirmado; recuperar el pendiente no vuelve a mover stock. |

Los archivos PHP de la tabla están bajo `app/Services`, `app/Search`, `app/Http/Controllers/Inventory` y `app/Http/Middleware`. Los de interfaz están bajo `resources/js`. Detalles y mediciones: `docs/inventory-stage-1.md`, `docs/inventory-stage-2-3.md` y `docs/inventory-stage-4.md`.

## Orden de instalación en Hostinger

### 1. Preparar las garantías de datos

Identificar el directorio Laravel y el ejecutable PHP 8.3 del sitio real; deben verificarse antes de ejecutar. Tener un respaldo reciente y una ventana coordinada de instalación: el nuevo código necesita las migraciones antes de recibir operaciones. Los formularios abiertos con la versión anterior deben volver a abrirse.

Tras instalar la revisión elegida y antes de reabrir operaciones, ejecutar únicamente las migraciones de este paquete con el PHP verificado:

```sh
php artisan migrate --path=database/migrations/2026_10_29_000100_add_catalog_version_to_products.php --force
php artisan migrate --path=database/migrations/2026_10_29_030000_create_inventory_operations_table.php --force
php artisan migrate --path=database/migrations/2026_10_29_040000_create_inventory_tasks_table.php --force
```

No ejecutar una migración general a ciegas: puede haber otras migraciones históricas pendientes. No revertir estas tablas mientras existan operaciones o tareas que deban conservarse. Si se necesita volver al código anterior, conservar los datos de operaciones y pendientes para una recuperación supervisada.

### 2. Verificar consultas

Confirmar que el archivo `public/build/manifest.json` y sus recursos corresponden a la revisión instalada; el flujo existente de GitHub construye esos recursos en la rama `production`. Verificar búsqueda por nombre/código, filtros, historial y totales de San Cristóbal. Registrar los tiempos del navegador y del servidor por separado.

### 3. Verificar colaboración

Usar dos sesiones con acceso a la misma sucursal. Mantener un formulario abierto mientras otra persona registra un movimiento. Comprobar que el formulario conserva sus datos y que las otras pantallas consultan el saldo vigente. Desconectar y reconectar una sesión debe recuperar el estado actual.

### 4. Activar y vigilar segundo plano

El procesador nuevo es independiente del `queue:work` existente. No basta con que la cola de Scout ya esté funcionando. Elegir una sola forma de activación:

- **Cron propio:** cada minuto, desde el directorio Laravel, ejecutar `php artisan inventory:work --limit=500 --max-time=55 --wait`. `--wait` sigue buscando pendientes cuando no hay trabajo durante ese intervalo, con pausas breves. Ajustar la ruta absoluta del ejecutable PHP y redirigir la salida a un archivo privado. El número de tareas, el tiempo disponible y la separación entre ejecuciones determinan la demora de entrega.
- **Programador Laravel existente:** `php artisan schedule:run` cada minuto. `routes/console.php` ya registra `inventory:work` con exclusión de ejecuciones superpuestas. Sin espera continua, los avisos pueden tardar hasta el siguiente minuto.

Para una entrega más inmediata puede mantenerse un proceso supervisado o ejecutarse un bucle controlado del comando. Esto exige comprobar primero qué permite el plan de alojamiento. No se asume que el cron existente de Hostinger ejecute el programador Laravel ni este comando.

Después de activarlo:

```sh
php artisan inventory:pending
php artisan inventory:work --limit=500 --max-time=45
php artisan inventory:pending
```

Comprobar que los pendientes disminuyen y que las pantallas reciben los avisos. Si aparecen tareas `failed`, corregir la conexión que falló y reintentar únicamente los IDs afectados. Una reserva abandonada se puede recuperar después de cinco minutos; no borrar filas ni reenviar movimientos para resolver avisos pendientes.

## Estado de verificación

Las etapas 1–3 y el paso 4 cuentan con pruebas locales descritas en sus informes: 123 pruebas PHP (733 verificaciones), 42 pruebas de interfaz y compilación Vite. Las pruebas de laboratorio no acreditan capacidad para miles de peticiones simultáneas ni sustituyen la prueba del servidor instalado y de los dispositivos reales.

Queda como corrección posterior el recorte de códigos largos encontrado durante la inspección. También quedan la validación del lector real, la medición completa del navegador/internet y la política de retención de pendientes completados.
