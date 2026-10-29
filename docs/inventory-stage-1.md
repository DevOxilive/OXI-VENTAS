# Inventario: medición y seguridad de operaciones simultáneas

Primera etapa local iniciada el 28 de octubre de 2026. El alcance es almacén, inventario y los movimientos asociados. No se accedió a Hostinger, no se publicó código y no se cambió la conexión habitual de la aplicación.

## Copia y aislamiento

El archivo original es `C:/Users/Oxigeno/Downloads/u199109938_Super_Kay (9).sql`. Su cabecera indica generación el 23 de octubre; no permite reconstruir los incidentes del día 28. Contiene 480 productos, 491 códigos, 762 asignaciones por sucursal, 302 lotes y 665 movimientos de stock.

Se importó en una instancia MySQL 8.0.46 propia del laboratorio, enlazada únicamente a `127.0.0.1:3308`, con usuario restringido a las bases `codex_inventory_lab_20261028` y `codex_inventory_tests_20261028`. El origen del respaldo es MariaDB 11.8.9: estos resultados no acreditan igualdad de configuración ni rendimiento con producción.

La carpeta privada `.codex/inventory-lab-20261028` está ignorada por Git. Contiene los datos del laboratorio, la conexión privada, las fuentes originales para comparación y los resultados. No se copiaron el respaldo ni las credenciales a archivos versionados. Las escrituras experimentales se hicieron sobre registros nuevos identificados como LAB; los registros importados se conservaron.

## Cambios de la primera etapa

- `ProductBatchController.php` y `useBatchAdjustmentModal.js`: las sumas y restas se aplican al saldo vigente. Los metadatos se envían únicamente cuando fueron editados y se contrastan con su valor original. Las cantidades absolutas requieren saldo esperado. Cambiar cantidades exige comprobar también el estado original del lote.
- `StockMovementService.php`: los inventarios de una distribución se reservan en orden, se acotan los reintentos y se rechazan entradas a una asignación retirada. La edición de lotes reserva inventario antes que lote y deja de cargar el historial completo dentro de la transacción.
- `ProductController.php`, `Product.php`, `ProductModal.vue` y `useProductActions.js`: versión entera del catálogo además de la fecha existente. También cambia al modificar únicamente códigos o asignaciones. Se comprueba de nuevo el inventario antes de retirar una sucursal.
- `PreventDuplicateInventoryOperation.php`: con una clave de operación, el movimiento y su resultado se confirman en la misma transacción. Un reenvío recupera el resultado sin repetir el efecto; la misma clave con contenido diferente se rechaza. La integración con `PreventDuplicateSubmission.php` está limitada a las rutas de stock y lotes.
- `Home.vue`, `useBranchInventory.js` e `inventoryRealtimeState.mjs`: una consulta fallida conserva las filas, las respuestas anteriores no reemplazan las nuevas y se elimina la segunda consulta de detalle que repetía información ya recibida. Se preservan los formularios abiertos.
- Los fallos de avisos posteriores a una confirmación se reportan sin hacer pasar el guardado confirmado por un error. La entrega durable de avisos y su procesamiento en segundo plano siguen pendientes de la etapa de colas.

Se prepararon dos migraciones nuevas: `2026_10_29_000100_add_catalog_version_to_products.php` y `2026_10_29_030000_create_inventory_operations_table.php`. Se ejecutaron únicamente en el laboratorio; el código requiere ambas antes de su puesta en uso en otra base. Los formularios anteriores a la actualización deben volver a abrirse para obtener la versión vigente.

## Evidencia de concurrencia

El escenario abre cuatro capturas del mismo lote, con cuatro conexiones MySQL independientes. Después de las muestras iniciales el lote tiene 25 unidades; cada captura agrega 5.

| Resultado | Código anterior | Código actual |
|---|---:|---:|
| Saldo esperado | 45 | 45 |
| Saldo obtenido | 30 | 45 |
| Unidades perdidas | 15 | 0 |

Reenviar una de las operaciones actuales devolvió `Idempotency-Replayed: true` y conservó el saldo en 45. Esto demuestra la corrección del escenario reproducido; no reconstruye las acciones históricas de las usuarias.

## Medición inicial

`scripts/inventory-lab.php` mide controladores y serialización en proceso. No mide red, TLS, navegación, middleware global, WebSocket ni infraestructura de producción. Se tomaron cinco muestras por acción, de manera secuencial; las cifras siguientes son medianas ilustrativas, no percentiles de capacidad ni una prueba de miles de peticiones por segundo.

Las lecturas se hicieron sobre San Cristóbal, con 474 asignaciones. El detalle con más historial del respaldo corresponde a otra sucursal y contiene solo 22 movimientos. No representa todavía un historial masivo.

| Acción | Antes, ms | Actual, ms |
|---|---:|---:|
| Productos | 33.45 | 37.43 |
| Búsqueda por nombre | 222.19 | 223.96 |
| Búsqueda por código | 18.41 | 21.89 |
| Actualización de un producto | 5.35 | 5.56 |
| Página de inventario | 93.09 | 95.99 |
| Detalle con mayor historial | 21.65 | 21.97 |
| Ajuste de lote LAB | 24.13 | 35.09 |

El ajuste actual incluye el nuevo registro transaccional contra reenvíos. En la última muestra disminuyó de 30 a 20 consultas, pero su tiempo local aumentó. Esta etapa prioriza integridad y no acredita una mejora general de latencia. La búsqueda de respaldo y las estadísticas siguen siendo objetivos de la siguiente etapa.

Resultados privados de referencia:

- Antes: `.codex/inventory-lab-20261028/run-20261029033814-32584b90/metrics.json`.
- Actual: `.codex/inventory-lab-20261028/run-20261029033819-56f2c2a7/metrics.json`.

Para repetir con el laboratorio disponible:

```powershell
php scripts/inventory-lab.php --baseline --concurrency --samples=5
php scripts/inventory-lab.php --concurrency --samples=5
```

Cada ejecución agrega sus propias fixtures LAB. El script rechaza conexiones fuera de la instancia y base previstas. Requiere la configuración privada del laboratorio y las fuentes originales conservadas para el modo anterior.

## Validación

- 84 pruebas PHP y 504 aserciones en MySQL aislado, entre nuevas regresiones y pruebas existentes relacionadas con inventario, precios, stock negativo, permisos y búsqueda.
- 28 pruebas funcionales Node de interfaz aprobadas, incluyendo orden de respuestas, fallos HTTP, recuperación de formularios y claves de operación.
- Compilación Vite, codificación, seguridad de tiempo real y revisión de espacios correctas.

Para PHPUnit se utilizó una segunda base vacía con la estructura del respaldo y las migraciones necesarias. La migración histórica de clasificación requiere datos previos y no funciona sobre una base recién creada; se evitó modificarla. La copia del día 23 tampoco tenía el seguimiento de anulaciones requerido por las pruebas existentes: su migración ya disponible se aplicó únicamente a esa base vacía de pruebas, sin cambiar código de ventas.

```powershell
php vendor/phpunit/phpunit/phpunit -c .codex/inventory-lab-20261028/phpunit.xml tests/Feature/InventoryBatchConcurrencyTest.php tests/Feature/InventoryProductConcurrencyTest.php tests/Feature/InventoryOperationIdempotencyTest.php tests/Feature/InventoryMovementSafetyTest.php tests/Feature/NegativeStockTest.php tests/Feature/ProductBranchAssignmentTest.php tests/Feature/IndependentProductPricingTest.php tests/Unit/ConcurrencySafetyRegressionTest.php tests/Unit/ProductSearchServiceTest.php tests/Unit/ProductIdentifierSearchTest.php tests/Unit/InventoryPermissionRoutingTest.php
node --test tests/Frontend/*.test.mjs
npm run build
```

Falta validación con navegadores y dispositivos reales, mayores volúmenes y la configuración efectiva de producción. La siguiente etapa es optimizar consultas y búsquedas, comparando con esta referencia y preservando las garantías de integridad.
