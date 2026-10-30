#!/bin/sh
# Apply only the three additive inventory migrations, from the reviewed revision.
set -eu
umask 077
mkdir -p '/home/u199109938/cron-super-kay/inventory-deploy-20261030'
cd '/home/u199109938/domains/super-kay.com.mx/public_html'
[ ! -f '/home/u199109938/cron-super-kay/inventory-deploy-20261030/migrations.done' ] || { echo 'Inventory migrations already completed'; exit 0; }
PHPBIN="$(command -v php)"
"$PHPBIN" -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();if(PHP_VERSION_ID<80300)throw new RuntimeException("PHP 8.3 required"); if(Illuminate\Support\Facades\DB::connection()->getDatabaseName()!=="u199109938_Super_Kay")throw new RuntimeException("Wrong target database"); echo "PHP ".PHP_VERSION."; target database verified\n";'
curl --fail --silent --show-error --connect-timeout 10 --max-time 30 --proto '=https' --tlsv1.2 'https://raw.githubusercontent.com/DevOxilive/OXI-VENTAS/36fa10153da8f77873acb39dec7c77a23334a405/database/migrations/2026_10_29_000100_add_catalog_version_to_products.php' -o '/home/u199109938/cron-super-kay/inventory-deploy-20261030/2026_10_29_000100_add_catalog_version_to_products.php'
printf '%s\n' 'a8acd5e2e8316b27d18d556ace998a6bfda7bbb74d3ea12ced8f9ad57bf7bad0  /home/u199109938/cron-super-kay/inventory-deploy-20261030/2026_10_29_000100_add_catalog_version_to_products.php' | sha256sum -c -
curl --fail --silent --show-error --connect-timeout 10 --max-time 30 --proto '=https' --tlsv1.2 'https://raw.githubusercontent.com/DevOxilive/OXI-VENTAS/36fa10153da8f77873acb39dec7c77a23334a405/database/migrations/2026_10_29_030000_create_inventory_operations_table.php' -o '/home/u199109938/cron-super-kay/inventory-deploy-20261030/2026_10_29_030000_create_inventory_operations_table.php'
printf '%s\n' 'bfaa13dbf7eb6b0beed90b4281b09a1dc4c4bd5edbfffa7199fe9585cafcfda7  /home/u199109938/cron-super-kay/inventory-deploy-20261030/2026_10_29_030000_create_inventory_operations_table.php' | sha256sum -c -
curl --fail --silent --show-error --connect-timeout 10 --max-time 30 --proto '=https' --tlsv1.2 'https://raw.githubusercontent.com/DevOxilive/OXI-VENTAS/36fa10153da8f77873acb39dec7c77a23334a405/database/migrations/2026_10_29_040000_create_inventory_tasks_table.php' -o '/home/u199109938/cron-super-kay/inventory-deploy-20261030/2026_10_29_040000_create_inventory_tasks_table.php'
printf '%s\n' '8f043b40b3032a31ef452a4725089d60f1c8fac3b0c067c0b2476f588ed13ef0  /home/u199109938/cron-super-kay/inventory-deploy-20261030/2026_10_29_040000_create_inventory_tasks_table.php' | sha256sum -c -
"$PHPBIN" artisan migrate --realpath --path='/home/u199109938/cron-super-kay/inventory-deploy-20261030/2026_10_29_000100_add_catalog_version_to_products.php' --force --no-interaction
"$PHPBIN" artisan migrate --realpath --path='/home/u199109938/cron-super-kay/inventory-deploy-20261030/2026_10_29_030000_create_inventory_operations_table.php' --force --no-interaction
"$PHPBIN" artisan migrate --realpath --path='/home/u199109938/cron-super-kay/inventory-deploy-20261030/2026_10_29_040000_create_inventory_tasks_table.php' --force --no-interaction
"$PHPBIN" -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();if(!Illuminate\Support\Facades\Schema::hasColumn("products","catalog_version")||!Illuminate\Support\Facades\Schema::hasTable("inventory_operations")||!Illuminate\Support\Facades\Schema::hasTable("inventory_tasks"))throw new RuntimeException("Incomplete schema");echo "Inventory schema ready\n";'
touch '/home/u199109938/cron-super-kay/inventory-deploy-20261030/migrations.done'
echo INVENTORY_MIGRATIONS_COMPLETED
