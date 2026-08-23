<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceIncidentController;
use App\Http\Controllers\AttendanceScheduleAssignmentController;
use App\Http\Controllers\AttendanceScheduleController;
use App\Http\Controllers\Audits\PhysicalCountController;
use App\Http\Controllers\Audits\PhysicalCountReportController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\Inventory\BranchInventoryController;
use App\Http\Controllers\Inventory\CategoryController;
use App\Http\Controllers\Inventory\GeneralPurchaseOrderController;
use App\Http\Controllers\Inventory\ProductBatchController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\PurchaseReportController;
use App\Http\Controllers\Inventory\ReportController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\QzTrayController;
use App\Http\Controllers\SystemAdministrationController;
use App\Http\Controllers\SystemAuditController;
use App\Http\Controllers\SystemRoleController;
use App\Http\Controllers\SystemTrashController;
use App\Http\Controllers\TicketTemplateController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Ventas\CashRegisterClosureController;
use App\Http\Controllers\Ventas\EmployeeCreditAccountController;
use App\Http\Controllers\Ventas\SalesController;
use App\Http\Controllers\Ventas\SalesReportController;
use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Fortify disables the package auto-routes, so register the maintained
// Laravel Passkeys routes explicitly for the attendance biometric flow.
require base_path('vendor/laravel/passkeys/routes/routes.php');

/*
|--------------------------------------------------------------------------
| PÚBLICO
|--------------------------------------------------------------------------
*/

Route::get('/pwa/manifest.webmanifest', function () {
    $manifest = [
        'id' => '/',
        'name' => 'Super-Kay',
        'short_name' => 'Super-Kay',
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#ffffff',
        'theme_color' => '#e0000f',
        'description' => 'Sistema Super-Kay instalado como app.',
        'icons' => [
            [
                'src' => '/icons/icon-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => '/icons/icon-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'any',
            ],
            [
                'src' => '/icons/maskable-512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
            [
                'src' => '/icons/maskable-192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
                'purpose' => 'maskable',
            ],
        ],
    ];

    return response()->json($manifest, 200, [
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'public, max-age=86400, stale-while-revalidate=604800',
    ]);
})->name('pwa.manifest');

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/register', function () {
    return Inertia::render('Auth/Register', [
        'roles' => DB::table('roles')
            ->whereNotIn('name', ['Administrador', 'Super Administrador'])
            ->orderBy('name')
            ->get(),

        'branches' => Branch::where('active', true)
            ->orderBy('name')
            ->get(),
    ]);
})->name('register');

/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    $usersAccess = 'permission:users.view,users.create,users.update,users.delete';
    $branchesAccess = 'permission:branches.view,branches.create,branches.update,branches.delete';
    $employeesAccess = 'permission:employees.view,employees.create,employees.update,employees.delete';
    $organizationAccess = 'permission:departments.view,departments.create,departments.update,departments.delete,positions.view,positions.create,positions.update,positions.delete';
    $salesAccess = 'permission:sales.view,sales.create,sales.update,sales.delete,sales.reports';
    $cashClosuresAccess = 'permission:sales.cash-closures.view,sales.cash-closures.create,sales.cash-closures.update,sales.cash-closures.delete,reports.cash-closures.create';
    $cashClosureReportsAccess = 'permission:reports.cash-closures.view';
    $ticketsAccess = 'permission:systems.tickets.view,systems.tickets.update';
    $cashClosureTicketsAccess = 'permission:systems.cash-closure-tickets.view,systems.cash-closure-tickets.update';
    $labelsAccess = 'permission:systems.labels.view,systems.labels.update,systems.labels.print';
    $productsAccess = 'permission:inventory.products.view,inventory.products.create,inventory.products.update,inventory.products.delete';
    $productImagesAccess = 'permission:inventory.products.view,inventory.products.create,inventory.products.update,inventory.products.delete,sales.view,sales.create,sales.purchase-lists.view,sales.purchase-lists.create,sales.purchase-orders.view,sales.purchase-orders.receive,inventory.purchase-orders.general.view,inventory.purchase-orders.general.create,inventory.purchase-orders.general.update,inventory.purchase-orders.general.complete';
    $branchInventoryAccess = 'permission:inventory.branches.view,inventory.branches.stock-in,inventory.branches.stock-out,inventory.branches.stock-adjust,inventory.branches.batches.update,inventory.branches.config.update';
    $purchaseReportsAccess = 'permission:sales.purchase-lists.view,sales.purchase-lists.create,sales.purchase-lists.update,sales.purchase-lists.delete,sales.purchase-orders.view,sales.purchase-orders.receive';
    $generalPurchaseOrdersAccess = 'permission:inventory.purchase-orders.general.view,inventory.purchase-orders.general.create,inventory.purchase-orders.general.update,inventory.purchase-orders.general.complete';
    $auditsAccess = 'permission:audits.physical-counts.count,audits.physical-counts.view-stock,audits.physical-counts.create,audits.physical-counts.close,audits.physical-counts.reopen,audits.physical-counts.finalize,audits.physical-counts.participants,audits.physical-counts.apply,audits.physical-counts.delete';
    $inventoryReportsAccess = 'permission:reports.inventory.view';
    $movementReportsAccess = 'permission:reports.movements.view';
    $reportsAccess = 'permission:reports.sales.view,reports.audits.view,reports.cash-closures.view,reports.inventory.view,reports.movements.view';

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard/productos', [DashboardController::class, 'searchProducts'])
        ->name('dashboard.products.search');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('system-administration')->name('system-administration.')->group(function () {
        Route::get('/', [SystemAdministrationController::class, 'index'])
            ->middleware('permission:system.center.access')
            ->name('index');

        Route::get('/audits', [SystemAuditController::class, 'index'])
            ->middleware('permission:system.audit.view')
            ->name('audits.index');
        Route::get('/audits/export', [SystemAuditController::class, 'export'])
            ->middleware('permission:system.audit.export')
            ->name('audits.export');

        Route::get('/roles', [SystemRoleController::class, 'index'])
            ->middleware('permission:system.roles.manage,system.permissions.manage')
            ->name('roles.index');
        Route::put('/roles/{role}', [SystemRoleController::class, 'update'])
            ->middleware('permission:system.permissions.manage')
            ->name('roles.update');

        Route::get('/trash', [SystemTrashController::class, 'index'])
            ->middleware('permission:system.trash.view')
            ->name('trash.index');
        Route::post('/trash/{resource}/{record}/restore', [SystemTrashController::class, 'restore'])
            ->middleware('permission:system.trash.restore')
            ->name('trash.restore');
        Route::post('/trash/{resource}/restore-many', [SystemTrashController::class, 'restoreMany'])
            ->middleware('permission:system.trash.restore')
            ->name('trash.restore-many');
        Route::post('/trash/{resource}/restore-all', [SystemTrashController::class, 'restoreAll'])
            ->middleware('permission:system.trash.restore')
            ->name('trash.restore-all');
        Route::delete('/trash/purge-expired', [SystemTrashController::class, 'purgeExpired'])
            ->middleware('permission:system.trash.empty')
            ->name('trash.purge-expired');
        Route::delete('/trash/{resource}/{record}/force-delete', [SystemTrashController::class, 'forceDelete'])
            ->middleware('permission:system.trash.force-delete')
            ->name('trash.force-delete');
        Route::delete('/trash/{resource}/empty', [SystemTrashController::class, 'empty'])
            ->middleware('permission:system.trash.empty')
            ->name('trash.empty');
    });

    Route::get('/qz/certificate', [QzTrayController::class, 'certificate'])
        ->middleware('permission:systems.qz.sign,sales.employee-credit.print')
        ->name('qz.certificate');

    Route::post('/qz/sign', [QzTrayController::class, 'sign'])
        ->middleware(['permission:systems.qz.sign,sales.employee-credit.print', 'idempotent'])
        ->name('qz.sign');

    /*
    |--------------------------------------------------------------------------
    | SISTEMAS - USUARIOS
    |--------------------------------------------------------------------------
    */

    Route::prefix('systems')->name('systems.')->group(function () use (
        $usersAccess,
        $ticketsAccess,
        $cashClosureTicketsAccess,
        $labelsAccess
    ) {
        Route::get('/users', [UserController::class, 'index'])
            ->middleware($usersAccess)
            ->name('users.index');

        Route::get('/attendance', [AttendanceController::class, 'index'])
            ->middleware('permission:attendance.view,attendance.register')
            ->name('attendance.index');
        Route::post('/attendance', [AttendanceController::class, 'store'])
            ->middleware(['permission:attendance.register', 'password.confirm', 'idempotent'])
            ->name('attendance.store');
        Route::post('/attendance/{attendanceRecord}/corrections', [AttendanceController::class, 'requestCorrection'])
            ->middleware(['permission:attendance.corrections.request,attendance.corrections.review', 'idempotent'])
            ->name('attendance.corrections.store');
        Route::patch('/attendance/corrections/{attendanceCorrectionRequest}', [AttendanceController::class, 'reviewCorrection'])
            ->middleware(['permission:attendance.corrections.review', 'idempotent'])
            ->name('attendance.corrections.review');
        Route::get('/attendance/export/excel', [AttendanceController::class, 'exportExcel'])
            ->middleware(['permission:attendance.export.excel', 'permission:files.export'])
            ->name('attendance.export-excel');
        Route::get('/attendance/export/pdf', [AttendanceController::class, 'exportPdf'])
            ->middleware(['permission:attendance.export.pdf', 'permission:files.export'])
            ->name('attendance.export-pdf');

        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:users.create')
            ->name('users.store');

        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.update')
            ->name('users.update');

        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete')
            ->name('users.destroy');

        Route::get('/tickets', function () {
            return redirect()->route('printers.tickets.index');
        })
            ->middleware($ticketsAccess)
            ->name('tickets.index');

        Route::put('/tickets/{ticketTemplate}', [TicketTemplateController::class, 'update'])
            ->middleware('permission:systems.tickets.update')
            ->name('tickets.update');

        Route::get('/cash-closure-tickets', [TicketTemplateController::class, 'cashClosures'])
            ->middleware($cashClosureTicketsAccess)
            ->name('cash-closure-tickets.index');

        Route::put('/cash-closure-tickets/{ticketTemplate}', [TicketTemplateController::class, 'update'])
            ->middleware('permission:systems.cash-closure-tickets.update')
            ->name('cash-closure-tickets.update');

        Route::get('/employee-credit-statement-tickets', function () {
            return redirect()->route('printers.employee-credit-statement-tickets.index');
        })
            ->middleware($ticketsAccess)
            ->name('employee-credit-statement-tickets.index');

        Route::put('/employee-credit-statement-tickets/{ticketTemplate}', [TicketTemplateController::class, 'update'])
            ->middleware('permission:systems.tickets.update')
            ->name('employee-credit-statement-tickets.update');

        Route::get('/labels', function () {
            return redirect()->route('printers.labels.index');
        })
            ->middleware($labelsAccess)
            ->name('labels.index');
    });

    /*
    |--------------------------------------------------------------------------
    | SISTEMAS - SUCURSALES
    |--------------------------------------------------------------------------
    */

    Route::get('/branches', [BranchController::class, 'index'])
        ->middleware($branchesAccess)
        ->name('branches.index');

    Route::post('/branches/geocode', [BranchController::class, 'geocode'])
        ->middleware('permission:branches.create,branches.update')
        ->name('branches.geocode');

    Route::post('/branches', [BranchController::class, 'store'])
        ->middleware('permission:branches.create')
        ->name('branches.store');

    Route::put('/branches/{branch}', [BranchController::class, 'update'])
        ->middleware('permission:branches.update')
        ->name('branches.update');

    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])
        ->middleware('permission:branches.delete')
        ->name('branches.destroy');

    /*
    |--------------------------------------------------------------------------
    | CAPITAL HUMANO - EMPLEADOS
    |--------------------------------------------------------------------------
    */

    Route::prefix('human-resources')->name('human-resources.')->group(function () use ($employeesAccess, $organizationAccess) {
        Route::get('/employees', [EmployeeController::class, 'index'])
            ->middleware($employeesAccess)
            ->name('employees.index');

        Route::post('/employees', [EmployeeController::class, 'store'])
            ->middleware('permission:employees.create')
            ->name('employees.store');

        Route::put('/employees/{id}', [EmployeeController::class, 'update'])
            ->middleware('permission:employees.update')
            ->name('employees.update');

        Route::delete('/employees/{id}', [EmployeeController::class, 'destroy'])
            ->middleware('permission:employees.delete')
            ->name('employees.destroy');

        Route::get('/employees/export-excel', [EmployeeController::class, 'exportExcel'])
            ->middleware('permission:files.export')
            ->name('employees.export-excel');

        Route::get('/departments', [DepartmentController::class, 'index'])
            ->middleware($organizationAccess)
            ->name('departments.index');

        Route::post('/departments', [DepartmentController::class, 'store'])
            ->middleware('permission:departments.create')
            ->name('departments.store');

        Route::put('/departments/{department}', [DepartmentController::class, 'update'])
            ->middleware('permission:departments.update')
            ->name('departments.update');

        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])
            ->middleware('permission:departments.delete')
            ->name('departments.destroy');

        Route::post('/positions', [PositionController::class, 'store'])
            ->middleware('permission:positions.create')
            ->name('positions.store');

        Route::put('/positions/{position}', [PositionController::class, 'update'])
            ->middleware('permission:positions.update')
            ->name('positions.update');

        Route::delete('/positions/{position}', [PositionController::class, 'destroy'])
            ->middleware('permission:positions.delete')
            ->name('positions.destroy');

        Route::get('/attendance', [AttendanceController::class, 'index'])
            ->middleware('permission:attendance.view,attendance.register,attendance.export.excel,attendance.export.pdf')
            ->name('attendance.index');
        Route::post('/attendance', [AttendanceController::class, 'store'])
            ->middleware(['permission:attendance.register', 'idempotent'])
            ->name('attendance.store');
        Route::get('/attendance/{attendanceRecord}/evidence-photo', [AttendanceController::class, 'evidencePhoto'])
            ->middleware('permission:attendance.manage')
            ->name('attendance.evidence-photo');
        Route::post('/attendance/{attendanceRecord}/corrections', [AttendanceController::class, 'requestCorrection'])
            ->middleware(['permission:attendance.corrections.request,attendance.corrections.review', 'idempotent'])
            ->name('attendance.corrections.store');
        Route::patch('/attendance/corrections/{attendanceCorrectionRequest}', [AttendanceController::class, 'reviewCorrection'])
            ->middleware(['permission:attendance.corrections.review', 'idempotent'])
            ->name('attendance.corrections.review');
        Route::get('/attendance/export/excel', [AttendanceController::class, 'exportExcel'])
            ->middleware('permission:attendance.export.excel')
            ->name('attendance.export-excel');
        Route::get('/attendance/export/pdf', [AttendanceController::class, 'exportPdf'])
            ->middleware('permission:attendance.export.pdf')
            ->name('attendance.export-pdf');
        Route::get('/attendance-table', [AttendanceController::class, 'table'])
            ->middleware('permission:attendance.view,attendance.manage,attendance.export.excel,attendance.export.pdf')
            ->name('attendance-table.index');

        Route::get('/attendance-schedules', [AttendanceScheduleController::class, 'index'])->middleware('permission:attendance.schedules.view,attendance.schedules.create,attendance.schedules.update,attendance.schedules.delete')->name('attendance-schedules.index');
        Route::post('/attendance-schedules', [AttendanceScheduleController::class, 'store'])->middleware('permission:attendance.schedules.create')->name('attendance-schedules.store');
        Route::put('/attendance-schedules/{attendanceSchedule}', [AttendanceScheduleController::class, 'update'])->middleware('permission:attendance.schedules.update')->name('attendance-schedules.update');
        Route::delete('/attendance-schedules/{attendanceSchedule}', [AttendanceScheduleController::class, 'destroy'])->middleware('permission:attendance.schedules.delete')->name('attendance-schedules.destroy');
        Route::get('/attendance-schedule-assignments', [AttendanceScheduleAssignmentController::class, 'index'])->middleware('permission:attendance.schedule-assignments.view,attendance.schedule-assignments.create,attendance.schedule-assignments.update,attendance.schedule-assignments.delete')->name('attendance-schedule-assignments.index');
        Route::post('/attendance-schedule-assignments', [AttendanceScheduleAssignmentController::class, 'store'])->middleware('permission:attendance.schedule-assignments.create')->name('attendance-schedule-assignments.store');
        Route::put('/attendance-schedule-assignments/{attendanceScheduleAssignment}', [AttendanceScheduleAssignmentController::class, 'update'])->middleware('permission:attendance.schedule-assignments.update')->name('attendance-schedule-assignments.update');
        Route::delete('/attendance-schedule-assignments/{attendanceScheduleAssignment}', [AttendanceScheduleAssignmentController::class, 'destroy'])->middleware('permission:attendance.schedule-assignments.delete')->name('attendance-schedule-assignments.destroy');
        Route::get('/attendance-incidents', [AttendanceIncidentController::class, 'index'])->middleware('permission:attendance.incidents.view,attendance.incidents.create,attendance.incidents.update,attendance.incidents.delete,attendance.incidents.approve,attendance.incidents.reject')->name('attendance-incidents.index');
        Route::post('/attendance-incidents', [AttendanceIncidentController::class, 'store'])->middleware('permission:attendance.incidents.create')->name('attendance-incidents.store');
        Route::put('/attendance-incidents/{attendanceIncident}', [AttendanceIncidentController::class, 'update'])->middleware('permission:attendance.incidents.update')->name('attendance-incidents.update');
        Route::delete('/attendance-incidents/{attendanceIncident}', [AttendanceIncidentController::class, 'destroy'])->middleware('permission:attendance.incidents.delete')->name('attendance-incidents.destroy');
        Route::patch('/attendance-incidents/{attendanceIncident}/review', [AttendanceIncidentController::class, 'review'])->middleware('permission:attendance.incidents.approve,attendance.incidents.reject')->name('attendance-incidents.review');
    });

    /*
    |--------------------------------------------------------------------------
    | VENTAS
    |--------------------------------------------------------------------------
    */

    Route::prefix('ventas')
        ->name('ventas.')
        ->group(function () use ($salesAccess, $cashClosuresAccess, $cashClosureReportsAccess, $purchaseReportsAccess) {
            Route::get('/', [SalesController::class, 'index'])
                ->middleware($salesAccess)
                ->name('home');

            Route::get('/productos/buscar', [SalesController::class, 'searchProducts'])
                ->middleware($salesAccess)
                ->name('products.search');

            Route::get('/historial', [SalesController::class, 'history'])
                ->middleware($salesAccess)
                ->name('history');

            Route::post('/', [SalesController::class, 'store'])
                ->middleware(['permission:sales.create', 'idempotent'])
                ->name('store');

            Route::post('/{sale}/cancelar', [SalesController::class, 'cancel'])
                ->middleware(['permission:sales.update', 'idempotent'])
                ->name('cancel');

            Route::get('/estados-cuenta', [EmployeeCreditAccountController::class, 'index'])
                ->middleware('permission:sales.employee-credit.view')
                ->name('employee-credit.index');
            Route::get('/estados-cuenta/{account}', [EmployeeCreditAccountController::class, 'show'])
                ->middleware('permission:sales.employee-credit.view')
                ->name('employee-credit.show');
            Route::put('/estados-cuenta/{account}/limite', [EmployeeCreditAccountController::class, 'updateLimit'])
                ->middleware('permission:sales.employee-credit.create')
                ->name('employee-credit.limit');
            Route::post('/estados-cuenta/{account}/pagos', [EmployeeCreditAccountController::class, 'pay'])
                ->middleware(['permission:sales.employee-credit.collect', 'idempotent'])
                ->name('employee-credit.pay');

            Route::get('/{sale}/ticket', [SalesController::class, 'ticket'])
                ->middleware($salesAccess)
                ->name('ticket');

            Route::get('/cortes', [CashRegisterClosureController::class, 'index'])
                ->middleware($cashClosuresAccess)
                ->name('cash-closures.index');

            Route::post('/cortes', [CashRegisterClosureController::class, 'store'])
                ->middleware(['permission:sales.cash-closures.create,reports.cash-closures.create', 'idempotent'])
                ->name('cash-closures.store');

            Route::put('/cortes/{closure}', [CashRegisterClosureController::class, 'update'])
                ->middleware('permission:sales.cash-closures.update,reports.cash-closures.update')
                ->name('cash-closures.update');

            Route::delete('/cortes/{closure}', [CashRegisterClosureController::class, 'destroy'])
                ->middleware('permission:sales.cash-closures.delete,reports.cash-closures.delete')
                ->name('cash-closures.destroy');

            Route::get('/cortes/reportes', [CashRegisterClosureController::class, 'reports'])
                ->middleware($cashClosureReportsAccess)
                ->name('cash-closures.reports');

            Route::get('/listas-de-compra', [PurchaseReportController::class, 'salesPurchaseLists'])
                ->middleware($purchaseReportsAccess)
                ->name('purchase-reports.index');

            Route::get('/ordenes-de-compra', [PurchaseReportController::class, 'salesPurchaseOrders'])
                ->middleware($purchaseReportsAccess)
                ->name('purchase-orders.index');

            Route::get('/asistencia', [AttendanceController::class, 'index'])
                ->middleware(['permission:attendance.register', 'role:Ventas,Vendedor'])
                ->name('attendance.index');

            Route::post('/asistencia', [AttendanceController::class, 'store'])
                ->middleware(['permission:attendance.register', 'role:Ventas,Vendedor', 'idempotent'])
                ->name('attendance.store');
        });

    /*
    |--------------------------------------------------------------------------
    | IMPRESORAS - TICKETS
    |--------------------------------------------------------------------------
    */

    Route::prefix('printers')->name('printers.')->group(function () use (
        $ticketsAccess,
        $cashClosureTicketsAccess,
        $labelsAccess
    ) {
        Route::get('/tickets', [TicketTemplateController::class, 'index'])
            ->middleware($ticketsAccess)
            ->name('tickets.index');

        Route::put('/tickets/{ticketTemplate}', [TicketTemplateController::class, 'update'])
            ->middleware('permission:systems.tickets.update')
            ->name('tickets.update');

        Route::get('/cash-closure-tickets', [TicketTemplateController::class, 'cashClosures'])
            ->middleware($cashClosureTicketsAccess)
            ->name('cash-closure-tickets.index');

        Route::put('/cash-closure-tickets/{ticketTemplate}', [TicketTemplateController::class, 'update'])
            ->middleware('permission:systems.cash-closure-tickets.update')
            ->name('cash-closure-tickets.update');

        Route::get('/employee-credit-statement-tickets', [TicketTemplateController::class, 'employeeCreditStatements'])
            ->middleware($ticketsAccess)
            ->name('employee-credit-statement-tickets.index');

        Route::put('/employee-credit-statement-tickets/{ticketTemplate}', [TicketTemplateController::class, 'update'])
            ->middleware('permission:systems.tickets.update')
            ->name('employee-credit-statement-tickets.update');

        Route::get('/labels', [TicketTemplateController::class, 'labels'])
            ->middleware($labelsAccess)
            ->name('labels.index');

        Route::put('/labels/{ticketTemplate}', [TicketTemplateController::class, 'updateLabel'])
            ->middleware('permission:systems.labels.update')
            ->name('labels.update');
    });

    /*
    |--------------------------------------------------------------------------
    | INVENTARIO - PRODUCTOS / CATEGORÍAS / MOVIMIENTOS
    |--------------------------------------------------------------------------
    */

    Route::prefix('inventory')->name('inventory.')->group(function () use (
        $reportsAccess,
        $productsAccess,
        $productImagesAccess,
        $branchInventoryAccess,
        $inventoryReportsAccess,
        $movementReportsAccess,
        $purchaseReportsAccess,
        $generalPurchaseOrdersAccess,
        $cashClosureReportsAccess
    ) {
        Route::get('/dashboard', fn () => redirect()->route('dashboard'))
            ->middleware($reportsAccess)
            ->name('dashboard');

        Route::get('/branches/{branch:slug}/products', [ProductController::class, 'index'])
            ->middleware($productsAccess)
            ->name('branches.products.index');

        Route::get('/branches/{branch:slug}/products/snapshots/{productId}', [ProductController::class, 'snapshot'])
            ->middleware($productsAccess)
            ->name('branches.products.snapshot');

        Route::get('/products/{product}/image', [ProductController::class, 'image'])
            ->middleware($productImagesAccess)
            ->name('products.image');

        Route::post('/branches/{branch:slug}/products', [ProductController::class, 'store'])
            ->middleware('permission:inventory.products.create')
            ->name('branches.products.store');

        Route::put('/branches/{branch:slug}/products/{product}', [ProductController::class, 'update'])
            ->middleware('permission:inventory.products.update')
            ->name('branches.products.update');

        Route::delete('/branches/{branch:slug}/products/{product}', [ProductController::class, 'destroy'])
            ->middleware('permission:inventory.products.delete')
            ->name('branches.products.destroy');

        Route::post('/categories', [CategoryController::class, 'store'])
            ->middleware('permission:inventory.products.create')
            ->name('categories.store');

        /*
        |--------------------------------------------------------------------------
        | INVENTARIO - INVENTARIO POR SUCURSAL
        |--------------------------------------------------------------------------
        */

        Route::get('/branches/{branch}/inventory', [BranchInventoryController::class, 'show'])
            ->middleware($branchInventoryAccess)
            ->name('branches.inventory');

        Route::get('/branches/{branch}/inventory/realtime-snapshot', [BranchInventoryController::class, 'realtimeSnapshot'])
            ->middleware($branchInventoryAccess)
            ->name('branches.inventory.realtime-snapshot');

        Route::post('/branch-inventory', [BranchInventoryController::class, 'store'])
            ->middleware('permission:inventory.branches.config.update')
            ->name('branch-inventory.store');

        Route::patch('/branch-inventory/{branchProduct}/config', [BranchInventoryController::class, 'updateConfig'])
            ->middleware('permission:inventory.branches.config.update')
            ->name('branch-inventory.update-config');

        Route::get('/branch-inventory/{branchProduct}/details', [BranchInventoryController::class, 'details'])
            ->middleware($branchInventoryAccess)
            ->name('branch-inventory.details');

        Route::put('/product-batches/{productBatch}', [ProductBatchController::class, 'update'])
            ->middleware('permission:inventory.branches.batches.update')
            ->name('product-batches.update');

        Route::get('/stock-movements', [StockMovementController::class, 'index'])
            ->middleware($branchInventoryAccess)
            ->name('stock-movements.index');

        Route::get('/stock-movements/products/search', [StockMovementController::class, 'searchProducts'])
            ->middleware($branchInventoryAccess)
            ->name('stock-movements.products.search');

        Route::post('/stock-movements', [StockMovementController::class, 'store'])
            ->middleware(['permission:inventory.branches.stock-in,inventory.branches.stock-out,inventory.branches.stock-adjust', 'idempotent'])
            ->name('stock-movements.store');

        Route::get('/movements', [StockMovementController::class, 'index'])
            ->middleware($branchInventoryAccess)
            ->name('movements');

        Route::get('/expirations', fn () => redirect()->route('inventory.reports.select', [
            'report' => 'inventory',
        ]))
            ->middleware($inventoryReportsAccess)
            ->name('expirations');

        Route::get('/transfers', [BranchInventoryController::class, 'landing'])
            ->middleware('permission:inventory.branches.stock-out')
            ->name('transfers');

        Route::get('/adjustments', [BranchInventoryController::class, 'landing'])
            ->middleware('permission:inventory.branches.stock-adjust')
            ->name('adjustments');

        /*
        |--------------------------------------------------------------------------
        | INVENTARIO - REPORTES DE COMPRA
        |--------------------------------------------------------------------------
        */

        Route::get('/branches/{branch}/purchase-reports', [PurchaseReportController::class, 'index'])
            ->middleware($purchaseReportsAccess)
            ->name('branches.purchase-reports.index');

        Route::get('/branches/{branch}/purchase-reports/create', [PurchaseReportController::class, 'create'])
            ->middleware('permission:sales.purchase-lists.create')
            ->name('branches.purchase-reports.create');

        Route::post('/branches/{branch}/purchase-reports', [PurchaseReportController::class, 'store'])
            ->middleware(['permission:sales.purchase-lists.create', 'idempotent'])
            ->name('branches.purchase-reports.store');

        Route::post('/branches/{branch}/purchase-reports/submit-empty', [PurchaseReportController::class, 'submitEmpty'])
            ->middleware(['permission:sales.purchase-lists.create', 'idempotent'])
            ->name('branches.purchase-reports.submit-empty');

        Route::get('/branches/{branch}/purchase-reports/{purchaseReport}', [PurchaseReportController::class, 'show'])
            ->middleware($purchaseReportsAccess)
            ->name('branches.purchase-reports.show');

        Route::put('/branches/{branch}/purchase-reports/{purchaseReport}', [PurchaseReportController::class, 'update'])
            ->middleware('permission:sales.purchase-lists.update')
            ->name('branches.purchase-reports.update');

        Route::post('/branches/{branch}/purchase-reports/{purchaseReport}/generate', [PurchaseReportController::class, 'generate'])
            ->middleware(['permission:sales.purchase-lists.update', 'idempotent'])
            ->name('branches.purchase-reports.generate');

        Route::post('/branches/{branch}/purchase-reports/{purchaseReport}/complete', [PurchaseReportController::class, 'complete'])
            ->middleware(['permission:sales.purchase-orders.receive', 'idempotent'])
            ->name('branches.purchase-reports.complete');

        Route::delete('/branches/{branch}/purchase-reports/{purchaseReport}', [PurchaseReportController::class, 'destroy'])
            ->middleware('permission:sales.purchase-lists.delete')
            ->name('branches.purchase-reports.destroy');

        /*
        |--------------------------------------------------------------------------
        | INVENTARIO - REPORTES / CADUCIDADES / TRANSFERENCIAS / AJUSTES
        |--------------------------------------------------------------------------
        */

        Route::get('/reports', [ReportController::class, 'landing'])
            ->middleware($reportsAccess)
            ->name('reports');

        Route::get('/reports/sales', [SalesReportController::class, 'global'])
            ->middleware('permission:reports.sales.view')
            ->name('reports.sales');

        Route::get('/reports/replenishment', [SalesReportController::class, 'globalReplenishment'])
            ->middleware('permission:reports.sales.view')
            ->name('reports.replenishment');

        Route::get('/reports/sales/products/excel', [SalesReportController::class, 'exportGlobalProductsExcel'])
            ->middleware('permission:reports.sales.export.excel')
            ->name('reports.sales.products.excel');

        Route::get('/reports/replenishment/excel', [SalesReportController::class, 'exportGlobalReplenishmentExcel'])
            ->middleware('permission:reports.sales.export.excel')
            ->name('reports.replenishment.excel');

        Route::get('/reports/sales/registered/excel', [SalesReportController::class, 'exportGlobalSalesExcel'])
            ->middleware('permission:reports.sales.export.excel')
            ->name('reports.sales.registered.excel');

        Route::get('/reports/sales/registered/pdf', [SalesReportController::class, 'exportGlobalSalesPdf'])
            ->middleware('permission:reports.sales.export.pdf')
            ->name('reports.sales.registered.pdf');

        Route::get('/reports/{report}', [ReportController::class, 'select'])
            ->middleware($reportsAccess)
            ->whereIn('report', ['sales', 'audits', 'cash-closures', 'inventory', 'movements'])
            ->name('reports.select');

        Route::get('/branches/{branch}/reports', [ReportController::class, 'index'])
            ->middleware($reportsAccess)
            ->name('branches.reports');

        Route::get('/branches/{branch}/reports/purchases', [PurchaseReportController::class, 'reportsIndex'])
            ->middleware($purchaseReportsAccess)
            ->name('branches.reports.purchases');

        Route::get('/branches/{branch}/reports/purchases/{purchaseReport}', [PurchaseReportController::class, 'reportOrder'])
            ->middleware($purchaseReportsAccess)
            ->name('branches.reports.purchases.show');

        Route::get('/branches/{branch}/reports/purchase-orders', [GeneralPurchaseOrderController::class, 'index'])
            ->middleware($generalPurchaseOrdersAccess)
            ->name('branches.reports.purchase-orders');

        Route::post('/branches/{branch}/reports/purchase-orders/consolidate', [GeneralPurchaseOrderController::class, 'consolidate'])
            ->middleware(['permission:inventory.purchase-orders.general.create', 'idempotent'])
            ->name('branches.reports.purchase-orders.consolidate');

        Route::get('/branches/{branch}/reports/purchase-orders/source-orders/{purchaseOrder}', [GeneralPurchaseOrderController::class, 'sourceOrder'])
            ->middleware('permission:inventory.purchase-orders.source.view')
            ->name('branches.reports.purchase-orders.source-orders.show');

        Route::post('/branches/{branch}/reports/purchase-orders/source-orders/{purchaseOrder}/transfer', [GeneralPurchaseOrderController::class, 'transferSourceOrder'])
            ->middleware(['permission:inventory.purchase-orders.source.transfer', 'idempotent'])
            ->name('branches.reports.purchase-orders.source-orders.transfer');

        Route::put('/branches/{branch}/reports/purchase-orders/source-orders/{purchaseOrder}/review', [GeneralPurchaseOrderController::class, 'reviewSourceOrder'])
            ->middleware('permission:inventory.purchase-orders.source.review')
            ->name('branches.reports.purchase-orders.source-orders.review');

        Route::put('/branches/{branch}/reports/purchase-orders/source-orders/{purchaseOrder}', [GeneralPurchaseOrderController::class, 'updateSourceOrder'])
            ->middleware('permission:inventory.purchase-orders.source.update')
            ->name('branches.reports.purchase-orders.source-orders.update');

        Route::get('/branches/{branch}/reports/purchase-orders/tracking', [GeneralPurchaseOrderController::class, 'tracking'])
            ->middleware('permission:inventory.purchase-orders.general.view')
            ->name('branches.reports.purchase-orders.tracking');

        Route::get('/branches/{branch}/reports/purchase-orders/history', [GeneralPurchaseOrderController::class, 'history'])
            ->middleware('permission:inventory.purchase-orders.general.view')
            ->name('branches.reports.purchase-orders.history');

        Route::get('/branches/{branch}/reports/purchase-orders/{generalPurchaseOrder}/capture', [GeneralPurchaseOrderController::class, 'edit'])
            ->middleware('permission:inventory.purchase-orders.general.update')
            ->name('branches.reports.purchase-orders.capture');

        Route::get('/branches/{branch}/reports/purchase-orders/{generalPurchaseOrder}', [GeneralPurchaseOrderController::class, 'show'])
            ->middleware($generalPurchaseOrdersAccess)
            ->name('branches.reports.purchase-orders.show');

        Route::put('/branches/{branch}/reports/purchase-orders/{generalPurchaseOrder}', [GeneralPurchaseOrderController::class, 'update'])
            ->middleware('permission:inventory.purchase-orders.general.update')
            ->name('branches.reports.purchase-orders.update');

        Route::post('/branches/{branch}/reports/purchase-orders/{generalPurchaseOrder}/complete', [GeneralPurchaseOrderController::class, 'complete'])
            ->middleware(['permission:inventory.purchase-orders.general.complete', 'idempotent'])
            ->name('branches.reports.purchase-orders.complete');

        Route::get('/branches/{branch}/reports/audits', function (Branch $branch) {
            return redirect()->route('audits.physical-counts.reports', [
                'branch' => $branch->slug,
            ]);
        })
            ->middleware('permission:reports.audits.view')
            ->name('branches.reports.audits');

        Route::get('/branches/{branch}/reports/cash-closures', [CashRegisterClosureController::class, 'reports'])
            ->middleware($cashClosureReportsAccess)
            ->name('branches.reports.cash-closures');

        Route::get('/branches/{branch}/reports/sales', [SalesReportController::class, 'index'])
            ->middleware('permission:reports.sales.view')
            ->name('branches.reports.sales');

        Route::get('/branches/{branch}/reports/replenishment', [SalesReportController::class, 'replenishment'])
            ->middleware('permission:reports.sales.view')
            ->name('branches.reports.replenishment');

        Route::get('/branches/{branch}/reports/sales/products/excel', [SalesReportController::class, 'exportProductsExcel'])
            ->middleware('permission:reports.sales.export.excel')
            ->name('branches.reports.sales.products.excel');

        Route::get('/branches/{branch}/reports/replenishment/excel', [SalesReportController::class, 'exportReplenishmentExcel'])
            ->middleware('permission:reports.sales.export.excel')
            ->name('branches.reports.replenishment.excel');

        Route::get('/branches/{branch}/reports/sales/registered/excel', [SalesReportController::class, 'exportSalesExcel'])
            ->middleware('permission:reports.sales.export.excel')
            ->name('branches.reports.sales.registered.excel');

        Route::get('/branches/{branch}/reports/sales/registered/pdf', [SalesReportController::class, 'exportSalesPdf'])
            ->middleware('permission:reports.sales.export.pdf')
            ->name('branches.reports.sales.registered.pdf');

        Route::get('/reports/sales/{sale}/excel', [SalesReportController::class, 'exportSaleExcel'])
            ->middleware('permission:reports.sales.export.excel')
            ->name('reports.sales.sale.excel');

        Route::get('/reports/sales/{sale}/pdf', [SalesReportController::class, 'exportSalePdf'])
            ->middleware('permission:reports.sales.export.pdf')
            ->name('reports.sales.sale.pdf');

        Route::get('/branches/{branch}/reports/inventory', [ReportController::class, 'inventory'])
            ->middleware($inventoryReportsAccess)
            ->name('branches.reports.inventory');

        Route::get('/branches/{branch}/reports/inventory/excel', [ReportController::class, 'exportExcel'])
            ->middleware('permission:reports.inventory.export.excel')
            ->name('branches.reports.inventory.excel');

        Route::get('/branches/{branch}/reports/inventory/pdf', [ReportController::class, 'exportPdf'])
            ->middleware('permission:reports.inventory.export.pdf')
            ->name('branches.reports.inventory.pdf');

        Route::get('/branches/{branch}/reports/movements', [ReportController::class, 'movements'])
            ->middleware($movementReportsAccess)
            ->name('branches.reports.movements');

        Route::get('/branches/{branch}/reports/movements/excel', [ReportController::class, 'exportMovementsExcel'])
            ->middleware('permission:reports.movements.export.excel')
            ->name('branches.reports.movements.excel');

        Route::get('/branches/{branch}/reports/movements/pdf', [ReportController::class, 'exportMovementsPdf'])
            ->middleware('permission:reports.movements.export.pdf')
            ->name('branches.reports.movements.pdf');
    });

    /*
    |--------------------------------------------------------------------------
    | INVENTARIO - INVENTARIO POR SUCURSAL
    |--------------------------------------------------------------------------
    */

    /*
    Route::prefix('inventario')->name('inventario.')->group(function () {
        // Bloque anterior conservado como referencia.
        // Las rutas activas ya fueron movidas a Route::prefix('inventory')->name('inventory.').
    });
    */

    /*
    |--------------------------------------------------------------------------
    | AUDITORÍAS - CONTEO FÍSICO
    |--------------------------------------------------------------------------
    */

    Route::prefix('audits')->name('audits.')->group(function () use ($auditsAccess) {
        Route::get('/physical-counts', [PhysicalCountController::class, 'index'])
            ->middleware($auditsAccess)
            ->name('physical-counts.index');

        Route::get('/physical-counts/reports', [PhysicalCountReportController::class, 'index'])
            ->middleware('permission:reports.audits.view')
            ->name('physical-counts.reports');

        Route::get('/physical-counts/reports/export-excel', [PhysicalCountReportController::class, 'exportExcel'])
            ->middleware('permission:reports.audits.export.excel')
            ->name('physical-counts.reports.export-excel');

        Route::get('/physical-counts/reports/export-pdf', [PhysicalCountReportController::class, 'exportPdf'])
            ->middleware('permission:reports.audits.export.pdf')
            ->name('physical-counts.reports.export-pdf');

        Route::get('/physical-count-entries/{entry}', [PhysicalCountController::class, 'showEntry'])
            ->middleware('permission:audits.physical-counts.count,audits.physical-counts.delete')
            ->name('physical-count-entries.show');

        Route::patch('/physical-count-entries/{entry}', [PhysicalCountController::class, 'updateEntry'])
            ->middleware('permission:audits.physical-counts.count')
            ->name('physical-count-entries.update');

        Route::delete('/physical-count-entries/{entry}', [PhysicalCountController::class, 'destroyEntry'])
            ->middleware('permission:audits.physical-counts.delete')
            ->name('physical-count-entries.destroy');

        Route::get('/physical-counts/{physicalCount}/search-products', [PhysicalCountController::class, 'searchProducts'])
            ->middleware('permission:audits.physical-counts.count')
            ->name('physical-counts.search-products');

        Route::get('/physical-counts/{physicalCount}', [PhysicalCountController::class, 'show'])
            ->middleware($auditsAccess)
            ->name('physical-counts.show');

        Route::post('/physical-counts', [PhysicalCountController::class, 'store'])
            ->middleware(['permission:audits.physical-counts.create', 'idempotent'])
            ->name('physical-counts.store');

        Route::put('/physical-counts/{physicalCount}', [PhysicalCountController::class, 'update'])
            ->middleware('permission:audits.physical-counts.participants')
            ->name('physical-counts.update');

        Route::delete('/physical-counts/{physicalCount}', [PhysicalCountController::class, 'destroy'])
            ->middleware('permission:audits.physical-counts.delete')
            ->name('physical-counts.destroy');

        Route::post('/physical-counts/{physicalCount}/scan', [PhysicalCountController::class, 'scan'])
            ->middleware('permission:audits.physical-counts.count')
            ->name('physical-counts.scan');

        Route::post('/physical-counts/{physicalCount}/entries', [PhysicalCountController::class, 'storeEntry'])
            ->middleware(['permission:audits.physical-counts.count', 'idempotent'])
            ->name('physical-counts.entries.store');

        Route::post('/physical-counts/{physicalCount}/batches', [PhysicalCountController::class, 'storeBatch'])
            ->middleware(['permission:audits.physical-counts.count', 'idempotent'])
            ->name('physical-counts.batches.store');

        Route::patch('/physical-counts/{physicalCount}/close', [PhysicalCountController::class, 'close'])
            ->middleware('permission:audits.physical-counts.close')
            ->name('physical-counts.close');

        Route::patch('/physical-counts/{physicalCount}/reopen', [PhysicalCountController::class, 'reopen'])
            ->middleware('permission:audits.physical-counts.reopen')
            ->name('physical-counts.reopen');

        Route::patch('/physical-counts/{physicalCount}/finalize', [PhysicalCountController::class, 'finalize'])
            ->middleware('permission:audits.physical-counts.finalize')
            ->name('physical-counts.finalize');

        Route::patch('/physical-counts/{physicalCount}/apply-adjustments', [PhysicalCountController::class, 'applyAdjustments'])
            ->middleware(['permission:audits.physical-counts.apply', 'idempotent'])
            ->name('physical-counts.apply-adjustments');

        Route::get('/physical-counts/{physicalCount}/export-excel', [PhysicalCountController::class, 'exportExcel'])
            ->middleware('permission:reports.audits.export.excel')
            ->name('physical-counts.export-excel');

        Route::get('/physical-counts/{physicalCount}/export-pdf', [PhysicalCountController::class, 'exportPdf'])
            ->middleware('permission:reports.audits.export.pdf')
            ->name('physical-counts.export-pdf');
    });
});
