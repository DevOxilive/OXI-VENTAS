# Matriz de normalización de fechas - Super-Kay

Fuente analizada: `u199109938_Super_Kay (2).sql`, exportado el 7 de octubre de 2026 a las 17:38:40 UTC.

## Regla objetivo

- La aplicación y cada conexión MySQL/MariaDB guardan instantes en UTC.
- La interfaz, tickets, PDF, Excel y filtros operativos usan `America/Mexico_City`.
- Los valores `date` sin hora no se desplazan.

## Hallazgos confirmados

| Tabla y columna | Filas observadas | Estado en el respaldo | Acción de normalización |
| --- | ---: | --- | --- |
| `sales.date` | 29 | UTC correcto. Ejemplo: `2026-10-06 14:02:12` equivale a `08:02:12` local. | No mover. Convertir solo al mostrar. |
| `employee_credit_payments.paid_at` | 3 | UTC correcto. | No mover. Convertir solo al mostrar. |
| `cash_register_closures.period_end` | 2 | UTC correcto. | No mover. Convertir solo al mostrar. |
| `cash_register_closures.period_start` | 2 | El corte `id=1` inicia en `2026-08-26 00:00:00`; para medianoche local debe ser `06:00:00` UTC. El corte `id=2` enlaza correctamente con el final anterior. | Candidato único: `id=1`, sumar 6 horas después de respaldo y vista previa. |
| `sales.created_at`, `sales.updated_at` | 29, ids `3..31` | Hora local escrita directamente; está 6 horas detrás de `sales.date`. | Candidatos a sumar 6 horas, pero solo en la migración posterior y con IDs recalculados al cierre. |
| `sale_details.created_at`, `sale_details.updated_at` | 46 | Mismo patrón que su venta relacionada. | Candidatos solo cuando su `sale_id` coincida con la matriz de ventas. |
| `employee_credit_charges.created_at`, `updated_at` | 12, ids `2..13` | Mismo patrón que `sales.date` de la venta vinculada. | Candidatos a sumar 6 horas, con relación validada. |
| `stock_movements.created_at`, `updated_at` | 46 vinculados a ventas | Los vinculados están 21,600 segundos detrás de `sales.date`. Hay 314 movimientos no vinculados que no se pueden corregir por inferencia. | Corregir solo los 46 vinculados y verificados. Los otros quedan en revisión, nunca en `UPDATE` masivo. |
| `system_audits.occurred_at`, `created_at`, `updated_at` | 1,951 | Las auditorías de ventas coinciden con la hora local directa, pero no todas tienen relación estructurada con una venta. | No mover automáticamente. Generar lista exacta por correlación y revisar antes de aplicar. |

## Exclusiones obligatorias

Nunca sumar seis horas a columnas `date` sin hora, importes, cantidades, folios, claves foráneas, ni a los eventos ya confirmados UTC: `sales.date`, `employee_credit_payments.paid_at` y `cash_register_closures.period_end`.

## Requisitos antes de una corrección histórica

1. Hacer un respaldo final al terminar el día y registrar su hash.
2. Activar mantenimiento y crear un segundo respaldo justo antes de ejecutar.
3. Recalcular esta matriz contra ese respaldo final; los IDs y conteos actuales solo describen la copia del 7 de octubre.
4. Generar una vista previa con `id`, valor original y valor propuesto.
5. Aplicar únicamente filas aprobadas dentro de una transacción y guardar un manifiesto de reversión.
6. Verificar importes, relaciones venta-movimiento, créditos y cortes antes de reabrir.
