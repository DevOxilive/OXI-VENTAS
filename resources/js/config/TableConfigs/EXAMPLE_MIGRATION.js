/**
 * EJEMPLO PRÁCTICO: Cómo migrar ProductTable
 *
 * Este archivo muestra exactamente cómo hacerlo con ambos patrones
 */

// ============================================================================
// PATRÓN 1: CONFIGURACIÓN EN ARCHIVO EXTERNO (RECOMENDADO)
// ============================================================================

/**
 * Archivo: resources/js/Config/TableConfigs/productTableConfig.js
 *
 * Este archivo centraliza la configuración para ser reutilizable
 */

export const productTableConfig = {
  columns: [
    {
      key: 'barcode',
      label: 'Código barras',
      format: 'text',
      width: '150px',
      mobileLabel: 'Código',
      mobileDisplay: true,
      mobileSecondary: false
    },
    {
      key: 'image',
      label: 'Imagen',
      format: 'image',
      formatOptions: {
        alt: 'Producto',
        fallback: 'Sin imagen'
      },
      mobileDisplay: false
    },
    {
      key: 'name',
      label: 'Producto',
      format: 'text',
      formatOptions: {
        multiline: true
      },
      subKey: 'presentation',
      mobileLabel: 'Producto',
      mobileSecondary: true
    },
    {
      key: 'category_name',
      label: 'Categoría',
      format: 'badge',
      mobileLabel: 'Cat.',
      mobileDisplay: false
    },
    {
      key: 'unit',
      label: 'Unidad de medida',
      format: 'text',
      mobileDisplay: false
    },
    {
      key: 'cost',
      label: 'Precio inicial',
      format: 'currency',
      formatOptions: {
        decimals: 2,
        fallback: '$0.00'
      },
      mobileDisplay: false
    },
    {
      key: 'price',
      label: 'Precio venta',
      format: 'currency',
      formatOptions: {
        decimals: 2,
        fallback: '$0.00'
      },
      mobileLabel: 'Precio',
      mobileDisplay: true
    }
  ],

  actions: [
    {
      id: 'view',
      label: 'Ver',
      icon: 'visibility',
      variant: 'blue',
      permission: 'inventario.ver'
    },
    {
      id: 'edit',
      label: 'Editar',
      icon: 'edit',
      variant: 'amber',
      permission: 'inventario.editar'
    },
    {
      id: 'delete',
      label: 'Eliminar',
      icon: 'delete',
      variant: 'red',
      permission: 'inventario.eliminar'
    }
  ],

  mobileCardHeaderField: 'name',
  noDataMessage: 'No se encontraron productos'
}

// ============================================================================
// PATRÓN 2: USO EN LA PÁGINA (Products.vue)
// ============================================================================

/**
 * Archivo: resources/js/Pages/Inventory/Products.vue
 *
 * Versión NUEVA (migrada con GlobalTable)
 */

const productPageExample = `
<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { computed } from 'vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { productTableConfig } from '@/Config/TableConfigs/productTableConfig'
import { useProductActions } from '@/Composables/Inventory/useProductActions'
import { useProductFilters } from '@/Composables/Inventory/useProductFilters'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    productsDB: Array
})

const productsDB = computed(() => props.productsDB ?? [])

const {
    search,
    filteredProducts
} = useProductFilters(productsDB)

const {
    showModal,
    modalMode,
    selectedProduct,
    openCreateModal,
    openEditModal,
    openViewModal,
    closeModal,
    deleteProduct
} = useProductActions()

// Handler único para todas las acciones de la tabla
function handleTableAction({ action, row }) {
    if (action === 'view') {
        openViewModal(row)
    } else if (action === 'edit') {
        openEditModal(row)
    } else if (action === 'delete') {
        deleteProduct(row)
    }
}
</script>

<template>
    <div class="bg-[#f6f3f7] min-h-screen rounded-2xl md:rounded-3xl p-4 md:p-6">
        <h1 class="text-lg md:text-xl font-semibold text-slate-700 mb-5">
            Productos
        </h1>

        <ProductToolbar @create="openCreateModal" @excel="exportExcel" />

        <!-- UN COMPONENTE PARA TODO (tabla + mobile + acciones + permisos) -->
        <GlobalTable
            :items="filteredProducts"
            v-bind="productTableConfig"
            @action="handleTableAction"
        />

        <ProductRegisterModal
            v-if="showModal"
            :mode="modalMode"
            :productToEdit="selectedProduct"
            @close="closeModal"
        />
    </div>
</template>
`

// ============================================================================
// COMPARATIVA: ANTES vs DESPUÉS
// ============================================================================

const comparison = {
    before: {
        files: [
            'ProductTable.vue (145 líneas)',
            'ProductMobileCards.vue (82 líneas)',
        ],
        imports: [
            'import ProductTable from "@/Components/Inventory/ProductTable.vue"',
            'import ProductMobileCards from "@/Components/Inventory/ProductMobileCards.vue"'
        ],
        template: `
            <ProductTable :products="filteredProducts" @view="..." @edit="..." @delete="..." />
            <ProductMobileCards :products="filteredProducts" @view="..." @edit="..." @delete="..." />
        `,
        totalCode: '227 líneas (tabla + mobile)',
        features: {
            responsive: 'Dos componentes separados',
            permissions: 'Hardcodeado en template',
            formats: 'Lógica inline (badges, moneda, etc)',
            mobile: 'Cards manual con flex'
        }
    },

    after: {
        files: [
            'productTableConfig.js (80 líneas, reutilizable)',
            'Products.vue (usa GlobalTable)'
        ],
        imports: [
            'import GlobalTable from "@/Components/Tables/GlobalTable.vue"',
            'import { productTableConfig } from "@/Config/TableConfigs/productTableConfig"'
        ],
        template: `
            <GlobalTable
                :items="filteredProducts"
                v-bind="productTableConfig"
                @action="handleTableAction"
            />
        `,
        totalCode: '~40 líneas en Products.vue + config reutilizable',
        features: {
            responsive: 'Automático (GlobalTable lo maneja)',
            permissions: 'Automático en acciones',
            formats: 'Declarativo (format: "currency")',
            mobile: 'Automático con cards'
        }
    },

    benefits: {
        reduction: '82% menos código en la página',
        reutilizable: 'Config puede usarse en múltiples lugares',
        mantenible: 'Cambios centralizados',
        responsive: 'Automático sin duplicación',
        permisosAutomáticos: 'Sin lógica manual'
    }
}

// ============================================================================
// PASO A PASO: MIGRACIÓN REAL
// ============================================================================

const migrationSteps = {
    paso1: {
        nombre: 'CREAR archivo de configuración',
        archivo: 'resources/js/Config/TableConfigs/productTableConfig.js',
        contenido: productTableConfig,
        tiempo: '5 minutos'
    },

    paso2: {
        nombre: 'ACTUALIZAR Products.vue',
        cambios: [
            'Remover import de ProductTable y ProductMobileCards',
            'Agregar import de GlobalTable',
            'Agregar import de productTableConfig',
            'Reemplazar template con GlobalTable',
            'Crear handleTableAction() para mapear acciones'
        ],
        tiempo: '10 minutos'
    },

    paso3: {
        nombre: 'VERIFICAR funcionamiento',
        verificaciones: [
            'Abre Products en navegador',
            'Tabla debe aparecer en desktop',
            'Cards deben aparecer en mobile',
            'Acciones (Ver, Editar, Eliminar) funcionan',
            'Permisos funcionan correctamente'
        ],
        tiempo: '5 minutos'
    },

    paso4: {
        nombre: 'ELIMINAR componentes antiguos',
        comando: `
            rm ./resources/js/Components/Inventory/ProductTable.vue
            rm ./resources/js/Components/Inventory/ProductMobileCards.vue
        `,
        verificar: 'grep -r "ProductTable\\|ProductMobileCards" ./resources --include="*.vue"',
        tiempo: '1 minuto'
    }
}

// ============================================================================
// ESTRUCTURA DE CARPETAS RECOMENDADA
// ============================================================================

const folderStructure = `
resources/js/
├── Components/
│   ├── Tables/
│   │   ├── GlobalTable.vue .................. ✓ NUEVO - Componente principal
│   │   ├── TableDesktop.vue ................. ✓ NUEVO
│   │   ├── TableMobile.vue .................. ✓ NUEVO
│   │   ├── useTableConfig.js ................ ✓ NUEVO
│   │   ├── tableFormatters.js ............... ✓ NUEVO
│   │   ├── tableStatusClasses.js ............ ✓ NUEVO
│   │   ├── examples.js ...................... ✓ NUEVO
│   │   ├── demoData.js ...................... ✓ NUEVO
│   │   ├── IMPLEMENTATION_GUIDE.js .......... ✓ NUEVO (esta guía)
│   │   └── README.md ........................ ✓ NUEVO
│   │
│   ├── HumanResources/
│   │   ├── EmployeeTable.vue ................ ✓ MIGRADO (ahora usa GlobalTable)
│   │   ├── EmployeeMobileCards.vue .......... ✗ ELIMINADO
│   │   ├── EmployeeToolbar.vue ............. ✓ SE MANTIENE
│   │   └── otros...
│   │
│   └── Inventory/
│       ├── ProductTable.vue ................. ✗ A ELIMINAR (migrar primero)
│       ├── ProductMobileCards.vue ........... ✗ A ELIMINAR
│       ├── InventoryTable.vue .............. ✗ A ELIMINAR
│       ├── InventoryMobileCards.vue ........ ✗ A ELIMINAR
│       └── otros...
│
├── Config/
│   └── TableConfigs/ ........................ ✓ NUEVO
│       ├── employeeTableConfig.js .......... ✓ NUEVO
│       ├── productTableConfig.js ........... ✓ NUEVO
│       ├── inventoryTableConfig.js ......... ✓ NUEVO
│       └── README.md
│
├── Pages/
│   ├── HumanResources/
│   │   └── Employees.vue ................... ✓ ACTUALIZADO
│   │
│   └── Inventory/
│       └── Products.vue .................... ✓ A ACTUALIZAR
│
└── Composables/
    ├── HumanResources/
    │   ├── useEmployeeActions.js ........... ✓ SE MANTIENE (sin cambios)
    │   ├── useEmployeeFilters.js ........... ✓ SE MANTIENE
    │   └── useEmployeeExport.js ............ ✓ SE MANTIENE
    │
    └── Inventory/
        ├── useProductActions.js ............ ✓ SE MANTIENE
        ├── useProductFilters.js ............ ✓ SE MANTIENE
        └── otros...
`

export default {
    productTableConfig,
    productPageExample,
    comparison,
    migrationSteps,
    folderStructure
}
