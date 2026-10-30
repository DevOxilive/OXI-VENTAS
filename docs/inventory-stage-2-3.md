# Inventario y almacén: consultas y colaboración

Segunda pasada local sobre los pasos 2 y 3, iniciada el 28 de octubre de 2026. Continúa las protecciones de integridad descritas en `inventory-stage-1.md`. El alcance incluye inventario, almacén y sus movimientos. No se accedió a Hostinger ni se publicó código.

## Paso 2: consultar menos información por operación

- `app/Http/Controllers/Inventory/ProductController.php` y `BranchInventoryController.php`: las búsquedas y actualizaciones parciales resuelven únicamente los datos solicitados. No vuelven a construir los catálogos de sucursales, categorías y productos cuando solo se necesita la página visible.
- `app/Search/DatabaseProductSearch.php`, `InventoryProductSqlPrefilter.php` y `ProductSearchOptions.php`: el respaldo de búsqueda de inventario filtra candidatos en SQL antes de construir los documentos de relevancia. Conserva nombres, acentos, alias, errores permitidos y códigos con ceros iniciales. Procesa candidatos en grupos de 250 y conserva los mejores resultados en memoria; no recorta arbitrariamente los candidatos a 500.
- `app/Search/ProductSearchService.php`: las consultas compuestas resuelven primero los identificadores y filtran juntos los términos de texto, conservando el orden de relevancia del primer término. Esta vía se usa cuando el catálogo de la sucursal cabe en el límite solicitado; para límites menores se preserva la intersección anterior. También evita repetir búsquedas de texto ya satisfechas al restringir lotes. Meilisearch conserva su vía existente; la optimización SQL opera en el respaldo de base de datos.
- `app/Services/InventoryReadService.php`: el historial se consulta por páginas de 25 movimientos, con filtros de usuario, motivo y fechas aplicados en el servidor. Los contadores describen el historial completo o filtrado, no solo la página visible. Las fechas respetan la zona horaria de la aplicación.
- `BranchInventoryController.php`: la lectura para actualizar una fila deja de adjuntar estadísticas, alertas e historial. El resumen tiene una ruta independiente; los detalles e historial se solicitan al abrir sus ventanas. Se conservan todos los lotes en el detalle, incluidos vacíos, negativos o retirados.

El filtro SQL de alias usa `JSON_TABLE` solo en versiones compatibles de MySQL y MariaDB. En versiones anteriores o desconocidas se conserva la búsqueda existente. Los cambios de búsqueda se activan expresamente desde inventario y almacén; el comportamiento de ventas no se cambia.

## Paso 3: reducir consultas durante el trabajo colaborativo

- `resources/js/utils/inventoryRealtimeBatch.mjs`, `resources/js/Pages/Inventory/Home.vue` y `resources/js/Composables/Inventory/useBranchInventory.js`: agrupan avisos recibidos durante 200 ms. Una ráfaga actualiza una vez la página visible y, en almacén, una vez el resumen. Solo existe una actualización de grupo en curso; los nuevos avisos se acumulan para una pasada posterior.
- Las actualizaciones conservan búsquedas, filtros, paginación, formularios y ventanas abiertas. Una respuesta anterior, de otra sucursal o de otro filtro no puede reemplazar el contexto vigente. Una consulta fallida conserva los datos visibles.
- `resources/js/realtime.js`: la reconexión reutiliza la conexión existente y vuelve a consultar la página actual. No crea una conexión adicional.
- `resources/js/Components/Inventory/BranchProducts/ProductMovementsModal.vue` y `useProductMovementsModal.js`: navegación y filtros del historial usan la paginación del servidor. Las demás ventanas solicitan el detalle sin historial.

Estas agrupaciones ordenan las consultas del navegador. No constituyen todavía una cola durable de trabajos del servidor. La entrega durable de avisos, los trabajos pesados en segundo plano y la capacidad de producción requieren una etapa posterior con la configuración efectiva del alojamiento.

## Laboratorio y límites

Se reutilizó la copia aislada en MySQL `127.0.0.1:3308`. Se agregaron registros sintéticos LAB para medir volumen; se preservaron los registros importados y la conexión habitual de la aplicación. Las fuentes de la primera etapa se conservaron de forma privada para comparar.

`scripts/inventory-lab.php` mide controladores y serialización, con cinco muestras secuenciales por acción. Registra producto de referencia, consultas SQL, bytes y tiempos. Permite elegir una referencia estable y perfiles concretos. Los resultados privados están en `.codex/inventory-lab-20261028`; no contienen credenciales en los archivos versionados.

Las mediciones locales excluyen red, TLS, servidor HTTP, middleware global y transporte WebSocket. No prueban miles de peticiones simultáneas ni explican por sí solas el incidente de producción. El respaldo es del 23 de octubre y no reconstruye las acciones del día 28.

## Validación

Resultados locales con cinco muestras por acción, usando las mismas referencias antes y después:

| Escenario | Primera etapa, mediana | Pasos 2 y 3, mediana |
|---|---:|---:|
| Búsqueda por nombre, catálogo LAB de 5,000 productos | 2,298 ms | 123 ms |
| Búsqueda parcial de productos, mismo catálogo | 2,215 ms | 92 ms |
| Búsqueda parcial de almacén, mismo catálogo | 2,249 ms | 95 ms |
| Abrir historial LAB de 5,000 movimientos | 1,345 ms | 46 ms |

La referencia estable del catálogo es el producto LAB 497, asignación 773, sucursal LAB 15. Las páginas parciales conservan los mismos tamaños de respuesta: 2,349 bytes para productos y 2,016 para almacén. La reducción principal de esas búsquedas es el trabajo de construcción y evaluación del catálogo, no una reducción universal del número de consultas.

El historial pasó de enviar los 5,000 movimientos en 3,513,304 bytes a enviar una página de 25 en 19,533 bytes, manteniendo el total de 5,000 y permitiendo navegar el resto. El detalle de lotes sin historial tardó 6.5 ms y devolvió 1,617 bytes. Los agregados de totales y usuarios mantienen una cantidad acotada de consultas, pero su costo SQL depende del tamaño del historial.

Evidencia privada reproducible:

- Catálogo antes: `.codex/inventory-lab-20261028/run-20261029045804-9e253b8d/metrics.json`.
- Catálogo actual: `.codex/inventory-lab-20261028/run-20261029055555-3e896eab/metrics.json`.
- Historial antes y actual: `.codex/inventory-lab-20261028/history-volume-stage1-baseline-metrics.json` y `history-volume-current-metrics.json`.
- El primer intento de comparación eligió una referencia no estable entre empates. Se corrigió el script para ordenar también por ID y registrar la referencia; esos primeros tiempos no se usan en la tabla.

Comprobaciones finales:

- 109 pruebas PHP, 640 aserciones aprobadas en la base MySQL aislada. Incluyen concurrencia, reenvíos, códigos, precios, stock negativo, permisos, búsquedas, paginación y consultas parciales.
- 42 pruebas Node aprobadas: agrupación de avisos, consultas sin superposición, filtros, cambio de sucursal, recuperación tras errores y conservación de formularios. Incluyen los componentes y funciones de colaboración, con transporte simulado.
- Compilación Vite, revisión de codificación, seguridad de tiempo real y `git diff --check` correctos. Vite informó advertencias de comentarios de una dependencia, sin impedir la compilación.

Falta la validación del uso en navegadores y dispositivos reales con varias usuarias, así como medir la configuración y las peticiones efectivas de producción. No se atribuye todavía el incidente al servicio de internet ni al alojamiento.
