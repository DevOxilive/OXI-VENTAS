<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\PurchaseReport;
use App\Models\TicketTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Single source of truth for records that can be recovered from the global trash.
 * Financial and operational history (sales, cash closures and physical audits) is
 * deliberately excluded and must be handled with its own status lifecycle.
 */
final class TrashRegistry
{
    private const RESOURCES = [
        'users' => ['model' => User::class, 'label' => 'Usuarios', 'searchable' => ['name', 'email']],
        'employees' => ['model' => Employee::class, 'label' => 'Empleados', 'searchable' => ['first_name', 'last_name', 'email']],
        'branches' => ['model' => Branch::class, 'label' => 'Sucursales', 'searchable' => ['name', 'slug']],
        'customers' => ['model' => Customer::class, 'label' => 'Clientes', 'searchable' => ['name', 'email']],
        'products' => ['model' => Product::class, 'label' => 'Productos', 'searchable' => ['name']],
        'categories' => ['model' => Category::class, 'label' => 'Categorías', 'searchable' => ['name']],
        'purchase-reports' => ['model' => PurchaseReport::class, 'label' => 'Listas de compra', 'searchable' => ['folio']],
        'ticket-templates' => ['model' => TicketTemplate::class, 'label' => 'Plantillas de ticket', 'searchable' => ['name', 'slug']],
    ];

    public static function resources(): array
    {
        return collect(self::RESOURCES)
            ->map(fn (array $resource, string $key) => [
                'key' => $key,
                'label' => $resource['label'],
                'retention_days' => TrashRetentionPolicy::days($key),
            ])
            ->values()
            ->all();
    }

    /** @return array<class-string<Model>> */
    public static function modelClasses(): array
    {
        return array_values(array_column(self::RESOURCES, 'model'));
    }

    public static function query(string $resource): Builder
    {
        $model = self::modelClass($resource);

        return $model::onlyTrashed();
    }

    public static function find(string $resource, int $id): Model
    {
        return self::query($resource)->findOrFail($id);
    }

    public static function label(string $resource): string
    {
        return self::resource($resource)['label'];
    }

    public static function searchableColumns(string $resource): array
    {
        return self::resource($resource)['searchable'];
    }

    public static function recordLabel(Model $model): string
    {
        foreach (['name', 'email', 'folio', 'slug', 'id'] as $attribute) {
            if (filled($model->getAttribute($attribute))) {
                return (string) $model->getAttribute($attribute);
            }
        }

        return class_basename($model) . ' #' . $model->getKey();
    }

    public static function keyForModel(Model $model): ?string
    {
        foreach (self::RESOURCES as $key => $resource) {
            if ($model instanceof $resource['model']) {
                return $key;
            }
        }

        return null;
    }

    private static function modelClass(string $resource): string
    {
        return self::resource($resource)['model'];
    }

    private static function resource(string $resource): array
    {
        if (!isset(self::RESOURCES[$resource])) {
            throw new InvalidArgumentException('Recurso de papelera no registrado.');
        }

        return self::RESOURCES[$resource];
    }
}
