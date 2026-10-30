# Paso 4: pendientes de inventario en segundo plano

## Resultado

Las altas, cambios de catálogo, entradas, salidas, distribuciones y ajustes de lotes guardan sus avisos pendientes en `inventory_tasks`, dentro de la misma transacción que los datos. Si esa operación se revierte, también se revierten los pendientes. El navegador recibe la confirmación sin esperar la conexión con el servicio de avisos ni el índice de búsqueda.

Las tareas son independientes: actualizar stock en otras pantallas, avisar un cambio de producto, informar actividad, actualizar búsqueda y avisar a las pantallas de auditoría/papelera. Un fallo del servicio de avisos no bloquea el resto ni repite el movimiento de existencias. La auditoría se sigue guardando al realizar la operación; únicamente su aviso se difiere.

La depuración de productos retirados también se programa como pendiente para su fecha de retención ya existente (180 días). No se cambia ese plazo: el servicio vuelve a comprobar que el producto siga retirado y que el plazo haya vencido. Una restauración conserva el producto.

## Archivos y responsabilidades

- `app/Services/InventoryOutbox.php` registra lo pendiente. Guarda también quién realizó la acción; el proceso posterior no necesita una sesión de esa persona.
- `app/Services/InventoryTaskProcessor.php` reserva una tarea durante un tiempo limitado, entrega fuera de la transacción de reserva y confirma el resultado. Los reintentos usan el estado actual del producto y sus existencias.
- `app/Services/InventoryBackgroundContext.php`, `app/Observers/InventoryProductSearchObserver.php` y `app/Observers/ProductSearchRelationObserver.php` desvían la indexación de las operaciones de inventario hacia pendientes. Fuera de ese contexto se conserva el funcionamiento de Scout.
- `app/Console/Commands/WorkInventoryTasks.php` procesa pendientes con límites de tiempo y cantidad.
- `app/Console/Commands/InspectInventoryTasks.php` muestra cantidades, antigüedad y errores por identificador; permite reactivar una tarea agotada concreta.
- `app/Models/InventoryTask.php` y `database/migrations/2026_10_29_040000_create_inventory_tasks_table.php` conservan estados, intentos y reservas.
- `config/inventory-background.php` define ocho intentos y reserva de cinco minutos de forma predeterminada. Los reintentos esperan 5, 15, 60, 180, 600, 1800 y 3600 segundos.

No se cambian ventas ni sus reglas de negocio. Las llamadas del servicio de stock asociadas a una venta o anulación conservan su comportamiento anterior. Las modificaciones compartidas de auditoría e indexación se limitan al contexto o rutas de inventario correspondientes.

## Seguimiento y recuperación

```sh
# Consultar estado. No muestra nombres, correos, códigos ni contenido de las tareas.
php artisan inventory:pending

# Atender hasta 500 tareas disponibles, durante un máximo aproximado de 45 segundos.
php artisan inventory:work --limit=500 --max-time=45

# Reactivar solo una tarea agotada, usando un ID obtenido en la consulta anterior.
php artisan inventory:pending --retry=123
```

El límite de tiempo se comprueba entre tareas; una petición externa que ya está en curso puede extenderlo. El proceso limita sus conexiones externas y la reserva debe superar sus tiempos máximos. Si se interrumpe después de reservar, otro proceso podrá recuperar el pendiente cuando venza la reserva. Si agota los intentos, se conserva como `failed`, sin borrar la evidencia.

La entrega es al menos una vez: un corte después de enviar un aviso y antes de confirmar su tarea puede repetir ese aviso. Nunca vuelve a ejecutar una entrada, salida o ajuste. Los navegadores siguen consultando el estado vigente y agrupando actualizaciones, como en el paso 3. Una tarea de búsqueda completada confirma el envío al motor; el motor externo puede continuar su propia indexación después.

Los pendientes completados se conservan para seguimiento. Su política de archivo/retención debe acordarse con el volumen real; no hay eliminación automática de pendientes ni fallidos.

## Instalación y límites

El procedimiento completo, con las cuatro etapas y el orden de activación, está en `docs/inventory-rollout.md`. Subir esta implementación a GitHub no activa por sí solo el nuevo proceso de Hostinger.

El índice externo puede quedar atrasado mientras no se ejecute el procesador. Las búsquedas exactas de códigos admitidos conservan su consulta directa a SQL. No se debe habilitar el nuevo código sin crear las tres tablas/columnas de las migraciones y configurar el procesamiento.

## Validación local

Se comprobaron 14 escenarios nuevos del paso 4, con 93 verificaciones, en la base MySQL aislada. Cubren confirmación sin red en HTTP, reversión conjunta, reintentos, continuidad de otros pendientes, reservas vencidas, rechazo de una confirmación tardía, actor original, indexación del estado vigente, retiro del índice, comportamiento fuera de inventario, límite de trabajo, fallo de persistencia del aviso de auditoría y restauración antes de depuración.

La batería de las etapas 1–3 también pasó: 109 pruebas PHP y 640 verificaciones. Las 42 pruebas de interfaz y la compilación Vite aprobaron. Las pruebas usan transporte simulado y no acreditan la entrega real en Hostinger ni la configuración de su cron.

El defecto independiente que recorta silenciosamente los códigos largos sigue pendiente; no se modificó su regla dentro del paso 4. La inspección original y los datos de producción permanecen en el laboratorio privado, fuera de Git.
