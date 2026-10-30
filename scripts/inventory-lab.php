<?php

/**
 * Laboratorio local: php scripts/inventory-lab.php [--baseline] [--concurrency] [--samples=5]
 * Lee la copia privada del catálogo y escribe únicamente fixtures LAB nuevas.
 * Los tiempos son de controlador + serialización, sin red ni servidor HTTP público.
 */

declare(strict_types=1);

use App\Http\Controllers\Inventory\BranchInventoryController;
use App\Http\Controllers\Inventory\ProductBatchController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Middleware\PreventDuplicateSubmission;
use App\Models\Barcode;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

try {
    if (PHP_SAPI !== 'cli') {
        throw new RuntimeException('CLI required');
    }

    $root = dirname(__DIR__);
    $private = $root.'/.codex/inventory-lab-20261028';
    $options = getopt('', ['baseline', 'stage1-baseline', 'concurrency', 'samples:', 'worker:', 'run:', 'read-branch:', 'reference-product:', 'profiles:']);
    $baseline = array_key_exists('baseline', $options);
    $stage1Baseline = array_key_exists('stage1-baseline', $options);
    $connection = json_decode(file_get_contents($private.'/connection.json'), true, flags: JSON_THROW_ON_ERROR);
    if ($connection['host'] !== '127.0.0.1' || (int) $connection['port'] !== 3308
        || $connection['database'] !== 'codex_inventory_lab_20261028') {
        throw new RuntimeException('Rejected connection outside the isolated lab');
    }

    foreach ([
        'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
        'APP_KEY' => 'base64:'.base64_encode(str_repeat('L', 32)), 'APP_URL' => 'http://localhost',
        'APP_CONFIG_CACHE' => '.codex/inventory-lab-20261028/nonexistent-lab-config.php',
        'APP_ROUTES_CACHE' => '.codex/inventory-lab-20261028/nonexistent-lab-routes.php',
        'APP_SERVICES_CACHE' => '.codex/inventory-lab-20261028/services.php',
        'APP_PACKAGES_CACHE' => '.codex/inventory-lab-20261028/packages.php',
        'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_SOCKET' => '', 'DB_HOST' => $connection['host'],
        'DB_PORT' => '3308', 'DB_DATABASE' => $connection['database'],
        'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'],
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync',
        'BROADCAST_CONNECTION' => 'null', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null',
        'SCOUT_DRIVER' => 'collection', 'SCOUT_QUEUE' => 'false',
    ] as $name => $value) {
        putenv($name.'='.$value);
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    require $root.'/vendor/autoload.php';
    if ($baseline) {
        foreach (['Models/Product.php', 'Http/Controllers/Inventory/ProductController.php', 'Http/Controllers/Inventory/ProductBatchController.php'] as $source) {
            require $private.'/baseline/app/'.$source;
        }
    }
    if ($stage1Baseline) {
        foreach (['Models/Product.php', 'Search/ProductSearchOptions.php', 'Search/ProductIdentifierSearch.php', 'Search/DatabaseProductSearch.php', 'Search/ProductSearchService.php', 'Http/Controllers/Inventory/ProductController.php', 'Http/Controllers/Inventory/BranchInventoryController.php'] as $source) {
            require $private.'/stage1-baseline/app/'.$source;
        }
    }
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    config(['broadcasting.default' => 'null', 'logging.default' => 'null', 'scout.driver' => 'collection']);
    $configured = config('database.connections.mysql');
    if ($configured['host'] !== '127.0.0.1' || (int) $configured['port'] !== 3308
        || $configured['database'] !== 'codex_inventory_lab_20261028'
        || DB::scalar('SELECT DATABASE()') !== 'codex_inventory_lab_20261028'
        || (int) DB::scalar('SELECT @@port') !== 3308) {
        throw new RuntimeException('Rejected effective database configuration');
    }

    $run = $options['run'] ?? date('YmdHis').'-'.bin2hex(random_bytes(4));
    if (! preg_match('/^\d{14}-[a-f0-9]{8}$/D', $run)) {
        throw new RuntimeException('Invalid lab run identifier');
    }
    $directory = $private.'/run-'.$run;
    if (! is_dir($directory) && ! mkdir($directory, 0700)) {
        throw new RuntimeException('Cannot create private metrics directory');
    }

    $meter = ['active' => false, 'count' => 0, 'ms' => 0.0];
    DB::listen(function ($query) use (&$meter): void {
        if ($meter['active']) {
            $meter['count']++;
            $meter['ms'] += $query->time;
        }
    });

    if (isset($options['worker'])) {
        $worker = filter_var($options['worker'], FILTER_VALIDATE_INT);
        if ($worker === false || $worker < 0 || $worker > 3) {
            throw new RuntimeException('Invalid worker');
        }
        $fixtures = json_decode(file_get_contents($directory.'/fixtures.json'), true, flags: JSON_THROW_ON_ERROR);
        verifyFixtures($fixtures, $run);
        $user = User::findOrFail($fixtures['users'][$worker]);
        $batch = ProductBatch::findOrFail($fixtures['batch']);
        $payload = $baseline
            ? legacyBatchPayload($batch, 5)
            : ['quantity_delta' => 5, 'expected_values' => ['status' => ProductBatch::STATUS_ACTIVE]];
        $key = 'lab-'.$run.'-worker-'.$worker;
        file_put_contents($directory.'/ready-'.$worker, 'ready');
        $deadline = microtime(true) + 30;
        while (! is_file($directory.'/release')) {
            if (microtime(true) > $deadline) {
                throw new RuntimeException('Worker barrier timed out');
            }
            usleep(10000);
        }

        $request = labRequest($app, $user, 'PUT', '/inventory/product-batches/'.$batch->id, $payload, $key);
        $sample = measured($meter, fn () => updateBatch($request, $batch, ! $baseline));
        $sample['worker'] = $worker;
        $sample['connection_id'] = (int) DB::scalar('SELECT CONNECTION_ID()');
        echo json_encode($sample, JSON_THROW_ON_ERROR).PHP_EOL;
        exit;
    }

    $countsBefore = tableCounts();
    $fixtures = createFixtures($run);
    file_put_contents($directory.'/fixtures.json', json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    verifyFixtures($fixtures, $run);
    $user = User::findOrFail($fixtures['users'][0]);
    $branch = Branch::where('slug', 'like', '%crist%')->where('active', true)->first()
        ?? Branch::query()->where('active', true)->orderByDesc(DB::raw('(SELECT COUNT(*) FROM branch_products WHERE branch_products.branch_id = branches.id)'))->firstOrFail();
    if (isset($options['read-branch'])) {
        $branch = Branch::whereKey((int) $options['read-branch'])->where('slug', 'like', 'lab-inventory-%')->firstOrFail();
    }
    $existing = BranchProduct::query()->where('branch_id', $branch->id)->where('status', BranchProduct::STATUS_ACTIVE)
        ->whereHas('product')
        ->when(isset($options['reference-product']), fn ($query) => $query->where('product_id', (int) $options['reference-product']))
        ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM stock_movements WHERE stock_movements.branch_product_id = branch_products.id)'))
        ->orderBy('id')->firstOrFail();
    $product = $existing->product;
    $barcode = $product->barcodes()->where('active', true)->value('code');
    $history = BranchProduct::query()->whereHas('product')
        ->orderByDesc(DB::raw('(SELECT COUNT(*) FROM stock_movements WHERE stock_movements.branch_product_id = branch_products.id)'))->orderBy('id')->firstOrFail();
    $samples = max(5, min(10, (int) ($options['samples'] ?? 5)));
    $report = [
        'run' => $run, 'mode' => $baseline ? 'baseline-three-original-classes' : ($stage1Baseline ? 'stage1-baseline' : 'current'),
        'scope' => 'Local controllers and JSON serialization; excludes global HTTP middleware, bootstrap, TLS and internet latency',
        'write_pipeline' => $baseline ? 'Original lot controller, without the new idempotency middleware' : 'Current lot controller plus inventory idempotency middleware',
        'source_hashes' => array_map(fn ($class) => hash_file('sha256', (new ReflectionClass($class))->getFileName()), [Product::class, ProductController::class, ProductBatchController::class]),
        'database' => $connection['database'], 'mysql_port' => 3308,
        'counts_before_fixtures' => $countsBefore,
        'read_workload' => ['branch_id' => $branch->id, 'branch_products' => BranchProduct::where('branch_id', $branch->id)->count(), 'reference_product_id' => $product->id, 'reference_assignment_id' => $existing->id, 'details_movement_count' => $existing->movements()->count(), 'heaviest_history_branch_id' => $history->branch_id, 'heaviest_history_movement_count' => $history->movements()->count()],
        'samples_per_action' => $samples, 'profiles' => [],
    ];
    $actions = [
        'products_index' => fn () => app(ProductController::class)->index(labRequest($app, $user, 'GET', '/inventory/branches/'.$branch->slug.'/products'), $branch),
        'products_search_name' => fn () => app(ProductController::class)->index(labRequest($app, $user, 'GET', '/inventory/branches/'.$branch->slug.'/products', ['search' => $product->name]), $branch),
        'products_search_barcode' => fn () => app(ProductController::class)->index(labRequest($app, $user, 'GET', '/inventory/branches/'.$branch->slug.'/products', ['search' => $barcode ?? '0000000000000']), $branch),
        'product_snapshot' => fn () => app(ProductController::class)->snapshot(labRequest($app, $user, 'GET', '/inventory/branches/'.$branch->slug.'/products/snapshots/'.$product->id), $branch, $product->id),
        'products_partial_search' => function () use ($app, $user, $branch, $product) {
            $request = labRequest($app, $user, 'GET', '/inventory/branches/'.$branch->slug.'/products', ['search' => $product->name]);
            $request->headers->set('X-Inertia-Partial-Component', 'Inventory/Home');
            $request->headers->set('X-Inertia-Partial-Data', 'productsDB,filters');

            return app(ProductController::class)->index($request, $branch);
        },
        'inventory_index' => fn () => app(BranchInventoryController::class)->show(labRequest($app, $user, 'GET', '/inventory/branches/'.$branch->id.'/inventory'), $branch),
        'inventory_partial_search' => function () use ($app, $user, $branch, $product) {
            $request = labRequest($app, $user, 'GET', '/inventory/branches/'.$branch->id.'/inventory', ['search' => $product->name]);
            $request->headers->set('X-Inertia-Partial-Component', 'Inventory/BranchInventory');
            $request->headers->set('X-Inertia-Partial-Data', 'branchProductsDB,filters');

            return app(BranchInventoryController::class)->show($request, $branch);
        },
        'inventory_realtime_row' => fn () => app(BranchInventoryController::class)->realtimeSnapshot(labRequest($app, $user, 'GET', '/inventory/branches/'.$branch->id.'/inventory/realtime-snapshot', ['branch_product_id' => $existing->id]), $branch),
        'inventory_details' => fn () => app(BranchInventoryController::class)->details(labRequest($app, $user, 'GET', '/inventory/branch-inventory/'.$existing->id.'/details'), BranchProduct::findOrFail($existing->id)),
        'inventory_details_heaviest_history' => fn () => app(BranchInventoryController::class)->details(labRequest($app, $user, 'GET', '/inventory/branch-inventory/'.$history->id.'/details'), BranchProduct::findOrFail($history->id)),
        'lab_lot_adjustment' => function () use ($app, $user, $fixtures, $baseline, $run) {
            $batch = ProductBatch::findOrFail($fixtures['batch']);
            $payload = $baseline ? legacyBatchPayload($batch, 1) : ['quantity_delta' => 1, 'expected_values' => ['status' => ProductBatch::STATUS_ACTIVE]];

            return updateBatch(labRequest($app, $user, 'PUT', '/inventory/product-batches/'.$batch->id, $payload, 'lab-'.$run.'-sample-'.bin2hex(random_bytes(4))), $batch, ! $baseline);
        },
    ];
    if (isset($options['profiles'])) {
        $requestedProfiles = array_values(array_unique(explode(',', (string) $options['profiles'])));
        if (array_diff($requestedProfiles, array_keys($actions)) !== []) {
            throw new RuntimeException('Unknown profile requested');
        }
        $actions = array_intersect_key($actions, array_flip($requestedProfiles));
    }
    foreach ($actions as $name => $action) {
        $series = [];
        for ($i = 0; $i < $samples; $i++) {
            $series[] = measured($meter, $action);
        }
        $times = array_column($series, 'total_ms');
        sort($times);
        $report['profiles'][$name] = ['median_ms' => $times[(int) floor(count($times) / 2)], 'max_ms' => max($times), 'samples' => $series];
        file_put_contents($directory.'/metrics-progress.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
    file_put_contents($directory.'/metrics.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    if (array_key_exists('concurrency', $options)) {
        $initial = (float) ProductBatch::findOrFail($fixtures['batch'])->quantity;
        $processes = [];
        for ($i = 0; $i < 4; $i++) {
            $command = [PHP_BINARY, '-d', 'opcache.enable_cli=0', __FILE__, '--worker='.$i, '--run='.$run];
            if ($baseline) {
                $command[] = '--baseline';
            }
            if ($stage1Baseline) {
                $command[] = '--stage1-baseline';
            }
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $directory.'/worker-'.$i.'.json', 'w'], 2 => ['file', $directory.'/worker-'.$i.'.stderr', 'w']], $pipes, $root, null, ['bypass_shell' => true]);
            if (! is_resource($process)) {
                throw new RuntimeException('Cannot start isolated worker');
            }
            fclose($pipes[0]);
            $processes[] = $process;
        }
        $deadline = microtime(true) + 30;
        while (count(glob($directory.'/ready-*')) !== 4) {
            if (microtime(true) > $deadline) {
                foreach ($processes as $process) {
                    proc_terminate($process);
                }
                throw new RuntimeException('Worker readiness timed out');
            }
            usleep(10000);
        }
        file_put_contents($directory.'/release', 'release');
        $workers = [];
        foreach ($processes as $i => $process) {
            $exit = proc_close($process);
            $workers[] = ['exit_code' => $exit, 'result' => json_decode(file_get_contents($directory.'/worker-'.$i.'.json'), true, flags: JSON_THROW_ON_ERROR)];
        }
        $final = (float) ProductBatch::findOrFail($fixtures['batch'])->quantity;
        $report['concurrency'] = ['workers' => $workers, 'requested_delta' => 20, 'initial_quantity' => $initial, 'final_quantity' => $final, 'expected_quantity' => $initial + 20, 'lost_delta' => round($initial + 20 - $final, 3), 'movement_count' => DB::table('stock_movement_batches')->where('product_batch_id', $fixtures['batch'])->count()];
        if (! $baseline) {
            $request = labRequest($app, $user, 'PUT', '/inventory/product-batches/'.$fixtures['batch'], ['quantity_delta' => 5, 'expected_values' => ['status' => ProductBatch::STATUS_ACTIVE]], 'lab-'.$run.'-worker-0');
            $replay = measured($meter, fn () => updateBatch($request, ProductBatch::findOrFail($fixtures['batch']), true));
            $report['concurrency']['replay'] = $replay + ['quantity_unchanged' => (float) ProductBatch::findOrFail($fixtures['batch'])->quantity === $final];
        }
    }

    file_put_contents($directory.'/metrics.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    echo json_encode(['metrics_path' => $directory.'/metrics.json', 'mode' => $report['mode'], 'profiles' => count($report['profiles']), 'concurrency' => $report['concurrency'] ?? null], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    // Nunca imprimir el mensaje de PDO, bindings, credenciales o contenido del catálogo.
    $error = ['error_class' => $exception::class, 'source' => basename($exception->getFile()).':'.$exception->getLine(), 'trace' => array_map(fn ($frame) => ['file' => basename($frame['file'] ?? ''), 'line' => $frame['line'] ?? null, 'function' => $frame['function']], array_slice($exception->getTrace(), 0, 5))];
    if ($exception instanceof QueryException) {
        $error['sql_template'] = $exception->getSql();
        $error['sql_state'] = $exception->errorInfo[0] ?? null;
        $error['driver_code'] = $exception->errorInfo[1] ?? null;
    }
    fwrite(STDERR, json_encode($error).PHP_EOL);
    exit(1);
}

function labRequest($app, User $user, string $method, string $path, array $data = [], ?string $key = null): Request
{
    $request = Request::create('http://localhost'.$path, $method, $data, server: ['HTTP_X_INERTIA' => 'true', 'HTTP_ACCEPT' => 'application/json', 'HTTP_REFERER' => 'http://localhost/inventory']);
    if ($key) {
        $request->headers->set('Idempotency-Key', $key);
    }
    $request->setUserResolver(fn () => $user);
    $session = $app['session']->driver();
    $session->flush();
    $session->start();
    $request->setLaravelSession($session);
    $route = new Route([$method], ltrim($path, '/'), fn () => null);
    $route->name($method === 'PUT' ? 'inventory.product-batches.update' : 'inventory.lab.read');
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);
    $app->instance('request', $request);
    Auth::setUser($user);

    return $request;
}

function measured(array &$meter, Closure $action): array
{
    $meter = ['active' => true, 'count' => 0, 'ms' => 0.0];
    $start = hrtime(true);
    $response = $action();
    if (! $response instanceof Response) {
        $response = $response->toResponse(request());
    }
    $bytes = strlen((string) $response->getContent());
    $meter['active'] = false;

    return ['total_ms' => round((hrtime(true) - $start) / 1e6, 3), 'sql_count' => $meter['count'], 'sql_ms' => round($meter['ms'], 3), 'json_bytes' => str_contains((string) $response->headers->get('Content-Type'), 'json') ? $bytes : 0, 'response_bytes' => $bytes, 'status' => $response->getStatusCode(), 'replayed' => $response->headers->get('Idempotency-Replayed') === 'true'];
}

function updateBatch(Request $request, ProductBatch $batch, bool $idempotent): Response
{
    $next = fn (Request $request) => app(ProductBatchController::class)->update($request, $batch);

    return $idempotent ? app(PreventDuplicateSubmission::class)->handle($request, $next) : $next($request);
}

function legacyBatchPayload(ProductBatch $batch, int $delta): array
{
    return [
        'lot_number' => $batch->lot_number, 'quantity' => (float) $batch->quantity + $delta,
        'status' => $batch->status, 'supplier' => $batch->supplier,
        'received_at' => $batch->received_at?->format('Y-m-d'),
        'expiration_date' => $batch->expiration_date?->format('Y-m-d'),
        'season_start_date' => $batch->season_start_date?->format('Y-m-d'),
        'season_end_date' => $batch->season_end_date?->format('Y-m-d'),
    ];
}

function tableCounts(): array
{
    $counts = [];
    foreach (['products', 'barcodes', 'branch_products', 'product_batches', 'stock_movements'] as $table) {
        $counts[$table] = DB::table($table)->count();
    }

    return $counts;
}

function createFixtures(string $run): array
{
    return Product::withoutEvents(fn () => DB::transaction(function () use ($run) {
        $branch = Branch::create(['name' => 'LAB '.$run, 'slug' => 'lab-inventory-'.$run, 'active' => true]);
        $role = Role::create(['name' => 'LAB '.$run]);
        $role->permissions()->attach(Permission::whereIn('name', ['branches.access-all', 'inventory.products.view', 'inventory.branches.view', 'inventory.branches.batches.update'])->pluck('id'));
        $users = [];
        for ($i = 0; $i < 4; $i++) {
            $user = User::create(['name' => 'LAB '.$run.' '.$i, 'email' => 'lab-'.$run.'-'.$i.'@example.invalid', 'password' => password_hash(bin2hex(random_bytes(24)), PASSWORD_BCRYPT, ['cost' => 4]), 'role_id' => $role->id, 'is_active' => true]);
            $user->branches()->attach($branch->id);
            $users[] = $user->id;
        }
        $category = Category::create(['name' => 'LAB '.$run, 'active' => true]);
        $product = Product::create(['name' => 'LAB '.$run, 'category_id' => $category->id, 'unit' => 'pza', 'inventory_unit' => 'pza', 'inventory_quantity_mode' => 'base', 'cost' => 10, 'sale_price' => 15, 'cost_per_piece' => 10, 'sale_price_per_piece' => 15, 'active' => true]);
        Barcode::create(['product_id' => $product->id, 'code' => 'LAB-'.$run, 'active' => true, 'type' => 'PRINCIPAL', 'base_quantity' => 1]);
        $assignment = BranchProduct::create(['branch_id' => $branch->id, 'product_id' => $product->id, 'stock' => 20, 'min_stock' => 0, 'status' => BranchProduct::STATUS_ACTIVE, 'tracks_batches' => true]);
        $batch = ProductBatch::create(['branch_product_id' => $assignment->id, 'lot_number' => 'LAB '.$run, 'quantity' => 20, 'initial_quantity' => 20, 'received_at' => date('Y-m-d'), 'status' => ProductBatch::STATUS_ACTIVE, 'has_real_lot' => true]);

        return ['run' => $run, 'branch' => $branch->id, 'product' => $product->id, 'assignment' => $assignment->id, 'batch' => $batch->id, 'users' => $users];
    }));
}

function verifyFixtures(array $fixtures, string $run): void
{
    if (($fixtures['run'] ?? null) !== $run
        || ! Branch::whereKey($fixtures['branch'])->where('slug', 'lab-inventory-'.$run)->exists()
        || ! Product::whereKey($fixtures['product'])->where('name', 'LAB '.$run)->exists()
        || ! BranchProduct::whereKey($fixtures['assignment'])->where('branch_id', $fixtures['branch'])->where('product_id', $fixtures['product'])->exists()
        || ! ProductBatch::whereKey($fixtures['batch'])->where('branch_product_id', $fixtures['assignment'])->where('lot_number', 'LAB '.$run)->exists()
        || User::whereIn('id', $fixtures['users'])->where('email', 'like', 'lab-'.$run.'-%@example.invalid')->count() !== 4) {
        throw new RuntimeException('Rejected non-LAB fixture');
    }
}
