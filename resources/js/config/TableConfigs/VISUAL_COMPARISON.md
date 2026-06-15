# Comparativa Visual: Antes vs Después

## 🔴 ARQUITECTURA ANTIGUA (Con Duplicación)

```
┌─────────────────────────────────────────────────────────────────┐
│                        Products.vue (Página)                     │
│  - Maneja filtros                                               │
│  - Maneja acciones                                              │
│  - Renderiza 2 componentes                                      │
└─────────────┬──────────────────────────────────────────────────┘
              │
              ├─────────────────────┬──────────────────────────
              │                     │
              ▼                     ▼
    ┌──────────────────┐   ┌──────────────────┐
    │  ProductTable.vue│   │ProductMobileCards│
    │  (145 líneas)    │   │    (82 líneas)   │
    │                  │   │                  │
    │ ✓ Table HTML     │   │ ✓ Cards (div)    │
    │ ✓ Badges         │   │ ✓ Badges        │
    │ ✓ Moneda         │   │ ✓ Moneda        │
    │ ✓ Permisos       │   │ ✓ Permisos      │
    │ ✓ Acciones       │   │ ✓ Acciones      │
    │                  │   │                  │
    │ ✗ DUPLICACIÓN    │   │ ✗ DUPLICACIÓN   │
    └──────────────────┘   └──────────────────┘

PROBLEMAS:
❌ 227 líneas de código
❌ Lógica duplicada (badges, moneda, permisos)
❌ Cambios = modificar 2 archivos
❌ Inconsistencias visuales posibles
❌ Cambios responsivos = actualizar ambos
```

---

## 🟢 ARQUITECTURA NUEVA (GlobalTable)

```
┌─────────────────────────────────────────────────────────────┐
│                   Products.vue (Página)                      │
│  - Maneja filtros                                            │
│  - Maneja acciones                                           │
│  - Renderiza GlobalTable (¡UN solo componente!)             │
└─────────────┬────────────────────────────────────────────────┘
              │
              ├──────────────────────────────────────────────
              │
              │          + productTableConfig
              ▼          (80 líneas, reutilizable)
    ┌────────────────────────────────┐
    │     GlobalTable.vue            │
    │  (Component Wrapper)           │
    │                                │
    │  - Props: items, columns,      │
    │    actions, filters            │
    │  - Emits: @action, @row-click  │
    └────────┬───────────────────────┘
             │
       ┌─────┴────────────────────────────────┐
       │                                      │
       ▼                                      ▼
┌──────────────────┐                ┌──────────────────┐
│  TableDesktop.vue│                │ TableMobile.vue  │
│   (HTML Table)   │                │   (Cards)        │
│                  │                │                  │
│ Renderiza si:    │                │ Renderiza si:    │
│ - md:block       │                │ - md:hidden      │
│ - Desktop        │                │ - Mobile         │
└──────────────────┘                └──────────────────┘

UTILIDADES COMPARTIDAS:
├─ tableFormatters.js
│  ├─ formatCurrency()
│  ├─ formatDate()
│  ├─ formatBadge()
│  └─ etc...
│
├─ tableStatusClasses.js
│  └─ getStatusClasses() → colores Tailwind
│
└─ useTableConfig.js
   └─ Validaciones y utilidades

BENEFICIOS:
✅ ~40 líneas en Products.vue
✅ Config reutilizable (80 líneas)
✅ Una fuente de verdad
✅ Cambios = 1 archivo
✅ Diseño consistente garantizado
✅ Responsivo automático
✅ 82% menos código
```

---

## 📊 Flujo de Datos: Viejo vs Nuevo

### ANTES (Viejo)

```
┌──────────────────┐
│ products: Array  │
└────────┬─────────┘
         │
         ├─────────────────────────────────────┐
         │                                     │
         ▼                                     ▼
  [Desktop Table]                       [Mobile Cards]
  - Renderiza 8 cols  ✗ REPETIR LÓGICA  - Renderiza 4 fields
  - Badges en cols 2  ✗ DUPLICADO       - Badges en header
  - Moneda en cols 6-7✗ INCONSISTENTE   - Moneda en grid
  - Acciones 8        ✗ PERMISOS x2     - Acciones en footer
  - Permisos hardcode ✗ x2 veces        - Permisos hardcode
  
Cambiar un badge = Editar 2 archivos ❌
```

### DESPUÉS (Nuevo)

```
┌────────────────────────────────────────────────┐
│ productTableConfig.js                          │
│ ┌────────────────────────────────────────────┐ │
│ │ const columns = [                          │ │
│ │   { key: 'price', format: 'currency' },   │ │
│ │   { key: 'status', format: 'badge' },     │ │
│ │   ...                                      │ │
│ │ ]                                          │ │
│ │ const actions = [...]                      │ │
│ │ const mobileCardHeaderField = 'name'       │ │
│ └────────────────────────────────────────────┘ │
└────────────────────────────────────────────────┘
                      │
                      ▼
         ┌────────────────────────┐
         │  GlobalTable.vue       │
         │ (Una fuente de verdad) │
         │  :items                │
         │  :columns    ◄─────────│─ UN SOLO CONFIG
         │  :actions              │
         │  :mobile...            │
         └────────────────────────┘
                      │
         ┌────────────┴──────────────┐
         │                           │
         ▼                           ▼
   [Desktop Table]            [Mobile Cards]
   (Renderiza auto)          (Renderiza auto)
   - Usa columns[0]          - Usa mobileLabel
   - Usa formatos            - Usa formatos
   - Aplica acciones         - Aplica acciones
   - Respeta permisos        - Respeta permisos
   
Cambiar un badge = Editar 1 archivo ✅
```

---

## 🎯 Casos de Uso Recomendados

### Caso 1: Tabla Simple (USO DIRECTO)

```vue
<!-- Products.vue - Patrón INLINE -->
<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'

const columns = [
  { key: 'name', label: 'Nombre' },
  { key: 'price', label: 'Precio', format: 'currency' },
  { key: 'status', label: 'Estado', format: 'badge' }
]

const actions = [
  { id: 'edit', label: 'Editar', icon: 'edit' }
]

function handleAction({ action, row }) {
  if (action === 'edit') editProduct(row)
}
</script>

<template>
  <GlobalTable
    :items="products"
    :columns="columns"
    :actions="actions"
    mobile-card-header-field="name"
    @action="handleAction"
  />
</template>

✅ VENTAJAS:
- Código en un lugar
- Fácil de entender
- Sin abstracción innecesaria
```

### Caso 2: Tabla Compleja (CON CONFIG EXTERNA)

```javascript
// Config/TableConfigs/productTableConfig.js
export const productTableConfig = {
  columns: [
    { key: 'name', label: 'Producto', mobileSecondary: true },
    { key: 'price', label: 'Precio', format: 'currency' },
    { key: 'image', label: 'Foto', format: 'image' },
    // ... más columnas
  ],
  actions: [
    { id: 'view', label: 'Ver', icon: 'visibility', permission: 'products.view' },
    { id: 'edit', label: 'Editar', icon: 'edit', permission: 'products.edit' },
    { id: 'delete', label: 'Eliminar', icon: 'delete', permission: 'products.delete' }
  ],
  mobileCardHeaderField: 'name',
  noDataMessage: 'No hay productos'
}
```

```vue
<!-- Products.vue - Patrón CON CONFIG -->
<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { productTableConfig } from '@/Config/TableConfigs/productTableConfig'

function handleAction({ action, row }) {
  if (action === 'view') viewProduct(row)
  if (action === 'edit') editProduct(row)
  if (action === 'delete') deleteProduct(row)
}
</script>

<template>
  <GlobalTable
    :items="products"
    v-bind="productTableConfig"
    @action="handleAction"
  />
</template>

✅ VENTAJAS:
- Config reutilizable
- Página limpia
- Fácil de mantener
- Puedes usar en múltiples páginas
```

### Caso 3: Tabla Reutilizable (COMPONENTE WRAPPER)

```vue
<!-- ProductTableWrapper.vue - Patrón WRAPPER (solo si REALMENTE lo necesitas) -->
<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { productTableConfig } from '@/Config/TableConfigs/productTableConfig'

defineProps(['products', 'loading'])
defineEmits(['action'])

function handleAction(event) {
  // Lógica personalizada aquí si la necesitas
  emit('action', event)
}
</script>

<template>
  <GlobalTable
    :items="products"
    v-bind="productTableConfig"
    :loading="loading"
    @action="handleAction"
  />
</template>

⚠️ ADVERTENCIA:
- Solo usa si lo necesitas REALMENTE
- En 90% de casos es innecesario
- Agrega una capa extra de indirección
```

---

## 📝 Checklist: Migración Paso a Paso

```
Para cada tabla (ProductTable, InventoryTable, etc):

☐ PASO 1: Analizar
   ├─ Identificar columnas
   ├─ Identificar acciones
   ├─ Identificar filtros
   └─ Identificar formatos especiales

☐ PASO 2: Crear Configuración
   ├─ Opción A: Inline si es simple
   │   └─ Código directo en la página
   └─ Opción B: Archivo si es complejo
       └─ Config/TableConfigs/{nombreTableConfig}.js

☐ PASO 3: Actualizar Página
   ├─ Remover imports de tabla antigua
   ├─ Agregar import de GlobalTable
   ├─ Agregar import de config (si aplica)
   ├─ Crear handleAction()
   └─ Reemplazar template

☐ PASO 4: Verificar
   ├─ Desktop: ¿tabla aparece?
   ├─ Mobile: ¿cards aparecen?
   ├─ Acciones: ¿funcionan?
   ├─ Permisos: ¿se respetan?
   └─ Filtros: ¿siguen funcionando?

☐ PASO 5: Limpiar
   ├─ rm ComponenteTableAntiga.vue
   ├─ rm ComponenteMobileCardsAntiga.vue
   └─ Verificar: grep -r "ComponenteAntiga"

☐ PASO 6: Documentar
   └─ Agregar comentario en config si es necesario
```

---

## 🚀 Orden Recomendado de Migración

```
1. ✅ EmployeeTable ............. COMPLETADO
2. ⬜ ProductTable .............. SIGUIENTE
3. ⬜ InventoryTable ............ DESPUÉS
4. ⬜ PurchaseReportTable ....... DESPUÉS
5. ⬜ Audits/CountEntriesTable .. DESPUÉS
6. ⬜ etc...

Total estimado: 30-45 minutos para migrar TODO
```

---

## 📚 Archivos de Referencia

```
GlobalTable Documentation:
├─ /Components/Tables/README.md .............. Documentación completa
├─ /Components/Tables/IMPLEMENTATION_GUIDE.js  Guía de implementación
├─ /Components/Tables/examples.js ........... Ejemplos de config
└─ /Components/Tables/demoData.js .......... Datos para testing

Tu nueva estructura:
├─ /Config/TableConfigs/ ................... Configuraciones modales
│  └─ productTableConfig.js
│  └─ employeeTableConfig.js (futuro)
│  └─ etc...
└─ /Config/TableConfigs/EXAMPLE_MIGRATION.js Este archivo
```
