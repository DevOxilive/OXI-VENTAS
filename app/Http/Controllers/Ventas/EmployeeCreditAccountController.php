<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Concerns\AuthorizesBranchAccess;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\EmployeeCreditAccount;
use App\Models\EmployeeCreditPayment;
use App\Models\PaymentMethod;
use App\Models\TicketTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EmployeeCreditAccountController extends Controller
{
    use AuthorizesBranchAccess;

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $search = trim((string) ($filters['search'] ?? ''));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $currentPaymentBranch = $this->resolvePaymentBranch($request);
        $ticketTemplate = TicketTemplate::employeeCreditStatementTemplate();

        $accounts = EmployeeCreditAccount::query()
            ->with('employee:id,first_name,last_name')
            ->withSum(['charges as balance' => fn ($query) => $query->where('status', 'open')], 'outstanding_amount')
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->whereHas('employee', function ($employeeQuery) use ($like) {
                    $employeeQuery
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like]);
                });
            })
            ->havingRaw('(balance - COALESCE(credit_balance, 0)) > 0')
            ->orderByDesc('balance')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (EmployeeCreditAccount $account) => $this->mapAccount($account));

        return Inertia::render('Ventas/EmployeeCreditAccounts', [
            'accounts' => $accounts,
            'paymentMethods' => $this->paymentMethods(),
            'currentPaymentBranch' => [
                'id' => $currentPaymentBranch->id,
                'name' => $currentPaymentBranch->name,
            ],
            'ticketTemplate' => [
                'id' => $ticketTemplate->id,
                'settings' => TicketTemplate::sanitizeSettings($ticketTemplate->settings ?? []),
            ],
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(Request $request, EmployeeCreditAccount $account)
    {
        $account->load([
            'employee:id,first_name,last_name',
            'charges' => fn ($query) => $query
                ->where('status', 'open')
                ->where('outstanding_amount', '>', 0)
                ->with([
                    'sale.details.barcode:id,product_id,code',
                    'sale.details.product:id,name',
                    'sale.details.product.barcodes:id,product_id,code',
                    'sale.branch:id,name',
                ])
                ->latest('id'),
            'payments' => fn ($query) => $query->with('paymentMethod:id,name')->latest('paid_at'),
        ]);

        return response()->json(['account' => [
            ...$this->mapAccount($account),
            'charges' => $account->charges->map(fn ($charge) => [
                'id' => $charge->id,
                'folio' => $charge->sale?->folio,
                'date' => optional($charge->sale?->date)->format('d/m/Y H:i'),
                'date_key' => optional($charge->sale?->date)->format('Y-m-d'),
                'date_label' => optional($charge->sale?->date)->format('d/m/Y'),
                'branch' => $charge->sale?->branch?->name,
                'amount' => (float) $charge->amount,
                'outstanding_amount' => (float) $charge->outstanding_amount,
                'estimated_payment_date' => optional($charge->estimated_payment_date)->format('Y-m-d'),
                'items' => $charge->sale?->details->map(fn ($detail) => [
                    'code' => $detail->barcode?->code ?? $detail->product?->barcodes?->first()?->code ?? '-',
                    'product' => $detail->product?->name,
                    'quantity' => (float) $detail->quantity,
                    'sale_unit' => $detail->sale_unit,
                    'unit_price' => (float) $detail->unit_price,
                    'subtotal' => (float) $detail->subtotal,
                ])->values(),
            ])->values(),
            'payments' => $account->payments->map(fn ($payment) => [
                'folio' => $payment->folio,
                'date' => optional($payment->paid_at)->format('d/m/Y H:i'),
                'method' => $payment->paymentMethod?->name,
                'amount' => (float) $payment->amount,
            ])->values(),
        ]]);
    }

    public function pay(Request $request, EmployeeCreditAccount $account)
    {
        $data = $request->validate([
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'selected_charge_ids' => ['required', 'array', 'min:1'],
            'selected_charge_ids.*' => ['integer', 'exists:employee_credit_charges,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'confirmed_card_payment' => ['nullable', 'boolean'],
        ]);
        $branch = $this->resolvePaymentBranch($request);
        $method = $this->paymentMethods()->firstWhere('id', (int) $data['payment_method_id']);
        if (! $method) throw ValidationException::withMessages(['payment_method_id' => 'Selecciona efectivo o tarjeta.']);
        $methodName = mb_strtolower($method->name);
        $isCard = str_contains($methodName, 'tarjeta') || str_contains($methodName, 'credito') || str_contains($methodName, 'debito') || str_contains($methodName, 'card');
        if ($isCard && ! (bool) ($data['confirmed_card_payment'] ?? false)) {
            throw ValidationException::withMessages(['confirmed_card_payment' => 'Confirma que la terminal aprobó el pago con tarjeta.']);
        }

        $payment = DB::transaction(function () use ($account, $data, $method, $branch, $request) {
            $account = EmployeeCreditAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $selectedChargeIds = collect($data['selected_charge_ids'])->map(fn ($id) => (int) $id)->unique()->values();
            $charges = $account->charges()
                ->whereIn('id', $selectedChargeIds)
                ->where('status', 'open')
                ->where('outstanding_amount', '>', 0)
                ->orderBy('created_at')
                ->lockForUpdate()
                ->get();
            if ($charges->count() !== $selectedChargeIds->count()) {
                throw ValidationException::withMessages(['selected_charge_ids' => 'Selecciona únicamente tickets pendientes de esta cuenta.']);
            }
            $balance = (float) $charges->sum('outstanding_amount');
            $amount = round((float) $data['amount'], 2);
            if (abs($amount - $balance) > 0.009) throw ValidationException::withMessages(['amount' => 'El importe debe coincidir con los tickets seleccionados.']);
            $isCash = str_contains(mb_strtolower($method->name), 'efectivo');
            $received = $isCash ? round((float) ($data['cash_received'] ?? 0), 2) : $amount;
            if ($isCash && $received < $amount) throw ValidationException::withMessages(['cash_received' => 'El efectivo recibido no cubre el abono.']);

            $payment = EmployeeCreditPayment::create([
                'folio' => 'ABO-PENDING-'.uniqid(), 'employee_credit_account_id' => $account->id,
                'branch_id' => $branch->id, 'payment_method_id' => $method->id, 'received_by_user_id' => $request->user()->id,
                'cash_box_number' => '1', 'amount' => $amount,
                'cash_received' => $received, 'change_due' => $isCash ? round($received - $amount, 2) : 0, 'paid_at' => now(),
            ]);
            $payment->update(['folio' => 'ABO-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($charges as $charge) {
                $applied = round((float) $charge->outstanding_amount, 2);
                $payment->allocations()->create(['employee_credit_charge_id' => $charge->id, 'amount' => $applied]);
                $charge->update(['outstanding_amount' => 0, 'status' => 'paid']);
            }
            return $payment;
        }, 3);

        return back()->with('success', "Abono {$payment->folio} registrado correctamente.");
    }

    public function updateLimit(Request $request, EmployeeCreditAccount $account)
    {
        $data = $request->validate([
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $account->update([
            'credit_limit' => filled($data['credit_limit'] ?? null)
                ? round((float) $data['credit_limit'], 2)
                : null,
        ]);

        return back()->with('success', 'Límite de crédito actualizado correctamente.');
    }

    private function paymentMethods()
    {
        return PaymentMethod::query()->where('active', true)->where(function ($query) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%efectivo%'])->orWhereRaw('LOWER(name) LIKE ?', ['%tarjeta%']);
        })->orderBy('id')->get(['id', 'name']);
    }

    private function mapAccount(EmployeeCreditAccount $account): array
    {
        $chargesBalance = (float) ($account->balance ?? $account->charges?->where('status', 'open')->sum('outstanding_amount') ?? 0);
        return ['id' => $account->id, 'employee' => trim(($account->employee?->first_name ?? '').' '.($account->employee?->last_name ?? '')),
            'balance' => max(0, $chargesBalance - (float) $account->credit_balance),
            'credit_balance' => (float) $account->credit_balance,
            'credit_limit' => $account->credit_limit === null ? null : (float) $account->credit_limit,
            'credit_limit_label' => $account->credit_limit === null ? 'Sin límite' : '$'.number_format((float) $account->credit_limit, 2),
            'estimated_payment_date' => optional($account->estimated_payment_date)->format('Y-m-d')];
    }

    private function resolvePaymentBranch(Request $request): Branch
    {
        $user = $request->user()->loadMissing(['role', 'branches']);
        $branchId = (int) ($request->query('branch_id') ?: $user->branch_id ?: 0);

        if ($branchId > 0) {
            $branch = Branch::query()->whereKey($branchId)->where('active', true)->first();
            if ($branch) {
                $this->abortIfUserCannotAccessBranch($request, $branch);
                return $branch;
            }
        }

        return $user->accessibleBranchesQuery()
            ->select('branches.id', 'branches.name')
            ->orderBy('branches.name')
            ->firstOrFail();
    }
}
