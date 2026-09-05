# Busqueda unificada de productos

El sistema usa `ProductSearchService` como una sola entrada para buscar productos desde inventario, ventas, auditorias, tablero y reportes. El indice contiene nombres, codigos, categorias, departamentos, sucursales y prefijos derivados del catalogo. No agrega expresiones coloquiales por su cuenta.

## Comportamiento

- Cada palabra escrita debe coincidir con algun dato del mismo producto.
- Los prefijos se calculan desde tres letras para permitir consultas como `cha 855`, `coc 600` y `submari` en cualquier posicion.
- Meilisearch admite errores ortograficos en palabras, pero los numeros y codigos de barras se comparan sin tolerancia a errores.
- Los codigos de barras completos de 8 a 14 digitos se resuelven primero en MySQL.
- Las terminaciones numericas de codigos se aceptan desde cinco digitos. Por ejemplo, `67890` encuentra `1234567890` sin tratar la terminacion como alias.
- Los fragmentos de lote se aceptan desde cuatro caracteres cuando la pantalla habilita busqueda por lotes. Los lotes se resuelven en MySQL para respetar sucursal, estado y existencia.
- Cuando una terminacion o fragmento coincide con un codigo o lote real, esa coincidencia operativa tiene prioridad sobre resultados textuales parecidos.
- Las consultas pueden combinar datos del producto con identificadores operativos, por ejemplo `coca CK924`.
- Si Meilisearch no responde, la aplicacion conserva una busqueda de respaldo en MySQL.

## Configuracion

Las variables estan documentadas en `.env.example`:

```dotenv
SCOUT_DRIVER=meilisearch
SCOUT_PREFIX=super_kay_
SCOUT_QUEUE=true
MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=
PRODUCT_SEARCH_FALLBACK=true
PRODUCT_SEARCH_MAX_RESULTS=10000
PRODUCT_SEARCH_MIN_BARCODE_SUFFIX=5
PRODUCT_SEARCH_MIN_LOT_FRAGMENT=4
```

En produccion, Meilisearch debe ejecutarse como un servicio privado y `MEILISEARCH_KEY` debe contener una llave segura. No se debe publicar el puerto 7700 directamente en Internet.

## Preparacion del indice

Despues de instalar o cambiar la configuracion:

```shell
php artisan migrate --force
php artisan config:cache
php artisan scout:sync-index-settings
php artisan scout:import "App\Models\Product"
php artisan queue:restart
```

La aplicacion actualiza automaticamente el indice cuando cambia un producto, codigo, asignacion de sucursal, categoria o departamento. Si se usa `SCOUT_QUEUE=true`, el trabajador de colas debe estar activo.

Las migraciones de esta funcionalidad crean `products.search_aliases` y los indices de apoyo para `branch_products.barcode` y `product_batches.lot_number`. No se debe omitir `php artisan migrate --force` al publicar el cambio.

Los codigos generales incluyen sus terminaciones en el documento de Meilisearch. Los codigos particulares de sucursal y los lotes se verifican adicionalmente en MySQL para evitar coincidencias de otra sucursal. Cuando se cambie la longitud minima de terminaciones es necesario sincronizar la configuracion y volver a importar `App\Models\Product`.

Las pantallas de productos e inventario por sucursal, la seleccion de productos para movimientos y la busqueda durante auditorias habilitan lotes activos con existencia. Los reportes de movimientos permiten tambien coincidencias con lotes historicos.

## Alias manuales

Cada producto guarda sus alias manuales en `products.search_aliases`. Se administran desde el campo **Alias de búsqueda** del modal de productos. Al guardar el producto, Scout actualiza ese documento en Meilisearch automaticamente; no hace falta reconstruir todo el indice.

El archivo `resources/search/product_aliases.json` queda reservado para reglas generales, por categoria o cargas masivas. Cuando se modifica ese archivo si es necesario importar de nuevo el indice completo.

Ejemplo de estructura:

```json
{
  "version": 1,
  "global": {
    "coca cola": ["coca", "coke"]
  },
  "categories": {
    "Galletas": ["galle"]
  },
  "products": {
    "7500000000000": ["nombre corto"]
  }
}
```

## Servicio local de desarrollo

En esta computadora, la edicion Community se ejecuta en `127.0.0.1:7700`, guarda sus datos en `C:\laragon\data\meilisearch-oxi-ventas` y sus bitacoras en `C:\laragon\log`. Es software autohospedado; no requiere Meilisearch Cloud ni otro servicio de pago.

El iniciador comprueba primero si el motor ya esta disponible, selecciona la version Community mas reciente instalada y evita abrir una segunda instancia:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\scripts\start-meilisearch.ps1
```

En esta computadora también se registra como tarea de inicio de sesión con el nombre `OXI-VENTAS Meilisearch`, para que vuelva a levantarse después de reiniciar Windows.

La comprobacion de salud es:

```powershell
Invoke-RestMethod http://127.0.0.1:7700/health
```

## Lista de comprobacion para el servidor

Antes de publicar, el servidor debe tener la edicion Community de Meilisearch ejecutandose como servicio privado y un trabajador de colas administrado por el sistema. El archivo `.env` del servidor debe definir `SCOUT_DRIVER`, `SCOUT_PREFIX`, `SCOUT_QUEUE`, `MEILISEARCH_HOST`, `MEILISEARCH_KEY` y las variables `PRODUCT_SEARCH_*` mostradas arriba. La llave es un secreto del servidor y nunca debe guardarse en Git.

Despues de recibir el codigo:

```shell
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan scout:sync-index-settings
php artisan scout:import "App\Models\Product"
php artisan queue:restart
php artisan optimize
```

Al terminar, se debe comparar la cantidad de productos de MySQL con la cantidad indexada y comprobar la salud del servicio en `MEILISEARCH_HOST/health`. La importacion completa es obligatoria en este cambio porque el documento de busqueda ahora contiene `barcode_suffixes`.
