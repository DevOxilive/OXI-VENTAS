# 📡 Flujo de Datos: Backend → Frontend → Tabla

## Explicación Completa del Flujo

### 1️⃣ BACKEND (PHP - ProductController.php)

**Ubicación:** `./app/Http/Controllers/Inventory/ProductController.php:17-100`

**Paso 1: Consulta Base de Datos**
```php
$query = BranchProduct::query()
    ->with([
        'branch:id,name,slug',
        'product:id,name,image,description,category_id,cost,sale_price,unit,active,created_at',
        'product.category:id,name',  // ← Carga la relación de categoría
        'product.barcodes:id,product_id,code',
    ])
    ->where('branch_id', $branch->id)
```

**Paso 2: Transforma Datos (línea 52-91)**
```php
$productsDB->getCollection()->transform(function ($item) {
    $product = $item->product;
    
    return [
        // Campos que devuelve:
        'id' => $product?->id,
        'barcode' => $product?->barcodes?->first()?->code ?? 'Sin código',
        'name' => $product?->name ?? 'Producto sin nombre',
        'image' => $product?->image,
        'category' => $product?->category?->name ?? 'Sin categoría', // ← AQUÍ
        'category_id' => $product?->category_id,
        'unit' => $product?->unit ?? '',
        'cost' => $product?->cost ?? 0,
        'price' => $product?->sale_price ?? 0,
        'sale_price' => $product?->sale_price ?? 0,
        // ... más campos
    ];
});
```

**Paso 3: Envía a Frontend**
```php
return Inertia::render('Inventory/Home', [
    'productsDB' => $productsDB,  // ← Los datos transformados van aquí
]);
```

**Estructura final de datos que llega a Frontend:**
```javascript
{
  id: 1,
  barcode: "123456789",
  name: "Laptop Dell",
  image: "url-a-imagen",
  category: "Electrónica",          // ← Campo correcto
  category_id: 5,
  unit: "Pieza",
  cost: 799.99,
  price: 999.99,
  // ... más campos
}
```

---

### 2️⃣ FRONTEND (Vue - Pages/Inventory/Home.vue)

**Ubicación:** `./resources/js/Pages/Inventory/Home.vue`

**Recibe datos del backend:**
```vue
<script setup>
const props = defineProps({
  productsDB: {
    type: [Array, Object],  // ← Datos desde ProductController
    default: () => ({ data: [] })
  }
})

const products = computed(() => props.productsDB?.data ?? [])
// products = [ { id: 1, barcode: "...", name: "...", category: "...", ... } ]
</script>

<template>
  <!-- Pasa los datos a ProductTable -->
  <ProductTable :products="products" @view="openViewModal" ... />
</template>
```

---

### 3️⃣ TABLA (Vue - Components/Inventory/ProductTable.vue)

**Ubicación:** `./resources/js/Components/Inventory/ProductTable.vue`

**Recibe datos y configuración:**
```vue
<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { productTableConfig } from '@/Config/TableConfigs/productTableConfig'

defineProps({
  products: Array  // ← Datos del backend vienen aquí
})

// productTableConfig define cómo mostrar los datos
</script>

<template>
  <GlobalTable
    :items="products"                    <!-- Los datos (array de objetos) -->
    v-bind="productTableConfig"          <!-- La configuración de cómo mostrarlos -->
    @action="handleTableAction"
  />
</template>
```

---

### 4️⃣ CONFIGURACIÓN (Config/TableConfigs/productTableConfig.js)

**Ubicación:** `./resources/js/Config/TableConfigs/productTableConfig.js`

**Define qué datos mostrar y cómo:**
```javascript
export const productTableConfig = {
  columns: [
    // Cada columna dice: "toma el campo 'key' de los datos"
    
    {
      key: 'barcode',        // ← Busca en: data.barcode
      label: 'Código barras',
      format: 'text'
    },
    
    {
      key: 'category',       // ← Busca en: data.category (NO data.category_name)
      label: 'Categoría',
      format: 'badge'
    },
    
    {
      key: 'price',          // ← Busca en: data.price
      label: 'Precio venta',
      format: 'currency'
    },
    // ... más columnas
  ]
}
```

---

### 5️⃣ GLOBALTABLE (Componente - Components/Tables/GlobalTable.vue)

**Ubicación:** `./resources/js/Components/Tables/GlobalTable.vue`

**Renderiza la tabla:**
```vue
<template>
  <!-- Para cada fila en items -->
  <tr v-for="row in items">
    <!-- Para cada columna en columns -->
    <td v-for="column in columns">
      <!-- Obtiene el valor usando la clave -->
      {{ row[column.key] }}  <!-- row['category'] = "Electrónica" -->
      
      <!-- Lo formatea según column.format -->
      <span v-if="column.format === 'badge'" class="badge">
        {{ row[column.key] }}
      </span>
    </td>
  </tr>
</template>
```

---

## 🔍 El Problema que Encontramos

```
❌ ANTES (Incorrecto):
   productTableConfig.js → key: 'category_name'
   ProductController.php → devuelve: 'category'
   
   Resultado: 
   row['category_name'] = undefined  ← No existe
   Muestra: undefined ❌

✅ AHORA (Correcto):
   productTableConfig.js → key: 'category'
   ProductController.php → devuelve: 'category'
   
   Resultado:
   row['category'] = "Electrónica"  ← Correcto
   Muestra: "Electrónica" ✅
```

---

## 📋 Mapa de Campos Disponibles

Estos son los campos que devuelve ProductController y que puedes usar en productTableConfig:

```javascript
{
  // Identificadores
  id: 1,
  branch_product_id: 123,
  branch_id: 5,
  branch_slug: "branch-name",
  
  // Producto
  name: "Laptop Dell XPS",
  image: "https://...",
  unit: "Pieza",
  
  // Códigos
  barcode: "1234567890",
  barcodes: ["123", "456"],  // Array de códigos alternativos
  
  // Categoría
  category: "Electrónica",      // ← USAR ESTE (no category_name)
  category_id: 5,
  
  // Precios
  cost: 799.99,                // Precio de compra
  price: 999.99,               // Precio de venta (igual a sale_price)
  sale_price: 999.99,          // Precio de venta
  salePrice: 999.99,           // Alias de sale_price
  profit: "200.00",            // Ganancia calculada
  
  // Stock y estado
  min_stock: 10,
  status: "active",
  active: true,
  
  // Lotes y vencimiento
  tracks_batches: true,
  tracks_expiration: true,
  entry_date: "2024-06-10",
  
  // Información de rama
  branch_ids: [1, 2, 3]        // Array de sucursales donde está disponible
}
```

---

## ✅ Cómo Usar Esta Información

Cuando crees configuraciones de tabla, asegúrate de que:

1. **El `key` coincida exactamente con el campo que devuelve el backend**
   ```javascript
   // ✅ Correcto
   { key: 'category', label: 'Categoría' }
   
   // ❌ Incorrecto
   { key: 'category_name', label: 'Categoría' }
   ```

2. **Verifiques qué campos devuelve el controlador**
   - Abre `ProductController.php`
   - Busca la transformación de datos
   - Lee qué keys se devuelven en el array

3. **Cuando tengas undefined, revisa:**
   - ¿El `key` existe en el objeto?
   - ¿Escribiste el nombre correctamente (case-sensitive)?
   - ¿El backend devuelve ese campo realmente?

---

## 🔧 Para Otros Controladores

Si migras otra tabla (InventoryTable, EmployeeTable, etc.), necesitas:

1. Encontrar el controlador correspondiente
2. Buscar dónde transforma los datos
3. Leer qué keys devuelve
4. Actualizar la configuración con los keys correctos

Por ejemplo, para Employees:
- Controlador: `app/Http/Controllers/HumanResources/EmployeeController.php`
- Buscar: dónde hace `transform()` o devuelve el array
- Campos: `firstName`, `lastName`, `position`, `department`, etc.
