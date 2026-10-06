<?php

namespace App\Http\Controllers\Ventas;

use App\Events\CreditAccountChanged;
use App\Events\CustomerChanged;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\EmployeeCreditAccount;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = TablePagination::resolvePerPage($request);

        $customers = Customer::query()
            ->with('creditAccount:id,customer_id,credit_limit,credit_balance')
            ->withCount('sales')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('name', 'like', $like)
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like]);
                });
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Customer $customer) => $this->mapCustomer($customer));

        return Inertia::render('Ventas/Customers', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $customer = DB::transaction(function () use ($data) {
            $customer = Customer::create([
                'name' => $this->fullName($data),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
            ]);
            EmployeeCreditAccount::create([
                'customer_id' => $customer->id,
                'credit_limit' => round((float) $data['credit_limit'], 2),
                'active' => true,
            ]);

            return $customer;
        }, 3);

        broadcast(new CustomerChanged('created', $customer->id))->toOthers();

        return back()->with('success', 'Cliente creado correctamente.');
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $this->validated($request);
        $account = DB::transaction(function () use ($customer, $data) {
            $customer->update([
                'name' => $this->fullName($data),
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
            ]);
            return $customer->creditAccount()->updateOrCreate(
                ['customer_id' => $customer->id],
                ['credit_limit' => round((float) $data['credit_limit'], 2), 'active' => true],
            );
        }, 3);

        broadcast(new CustomerChanged('updated', $customer->id))->toOthers();
        broadcast(new CreditAccountChanged('customer_updated', $account->id))->toOthers();

        return back()->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Customer $customer)
    {
        $account = $customer->creditAccount()->first();
        $pendingBalance = $account
            ? max(0, (float) $account->charges()->where('status', 'open')->sum('outstanding_amount') - (float) $account->credit_balance)
            : 0;

        if ($pendingBalance > 0) {
            throw ValidationException::withMessages([
                'customer' => 'No puedes eliminar al cliente mientras tenga un adeudo pendiente.',
            ]);
        }

        $customerId = $customer->id;
        $customer->delete();
        broadcast(new CustomerChanged('deleted', $customerId))->toOthers();

        return back()->with('success', 'Cliente eliminado correctamente.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'credit_limit' => ['required', 'numeric', 'gt:0', 'max:999999.99'],
        ]);
    }

    private function fullName(array $data): string
    {
        return trim($data['first_name'].' '.$data['last_name']);
    }

    private function mapCustomer(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'first_name' => $customer->first_name ?: $customer->name,
            'last_name' => $customer->last_name,
            'credit_limit' => $customer->creditAccount?->credit_limit === null
                ? null
                : (float) $customer->creditAccount->credit_limit,
            'credit_limit_label' => $customer->creditAccount?->credit_limit === null
                ? 'Sin límite'
                : '$'.number_format((float) $customer->creditAccount->credit_limit, 2),
            'sales_count' => (int) $customer->sales_count,
            'created_at_label' => optional($customer->created_at)->format('d/m/Y'),
        ];
    }
}
