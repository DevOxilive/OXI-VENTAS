<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PhysicalCountAuditWorkbookExport implements WithMultipleSheets
{
    protected Collection $users;
    protected ?string $statusFilter;
    protected string $mainSheetTitle;

    public function __construct(
        protected array $payload,
        protected array $filters,
        protected array $filterLabels,
        protected string $branchName
    ) {
        $entries = collect($this->payload['entries'] ?? []);
        $selectedUserIds = collect($this->filters['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $this->statusFilter = ($this->filters['status'] ?? '') ?: null;
        $this->mainSheetTitle = $this->statusSheetTitle($this->statusFilter) ?? 'Concentrado';

        $visibleEntryKeys = collect($this->payload['reportRows'] ?? [])
            ->where('row_type', 'counted')
            ->map(fn (array $row) => ($row['physical_count_id'] ?? null) . ':' . ($row['branch_product_id'] ?? null))
            ->filter(fn (string $key) => ! str_starts_with($key, ':') && ! str_ends_with($key, ':'))
            ->unique()
            ->values();

        $visibleEntries = $visibleEntryKeys->isEmpty()
            ? collect()
            : $entries->filter(fn ($entry) => $visibleEntryKeys->contains($entry->physical_count_id . ':' . $entry->branch_product_id));

        $this->users = $visibleEntries
            ->pluck('user')
            ->filter()
            ->when($selectedUserIds->isNotEmpty(), fn ($users) => $users
                ->filter(fn ($user) => $selectedUserIds->contains((int) $user->id)))
            ->unique('id')
            ->values();
    }

    public function sheets(): array
    {
        $sheets = [
            new PhysicalCountDashboardSheet($this->payload, $this->filterLabels, $this->branchName, $this->mainSheetTitle, $this->users, $this->statusFilter),
            new PhysicalCountConcentratedSheet($this->payload, $this->users, $this->mainSheetTitle, $this->statusFilter),
        ];

        foreach ($this->users as $user) {
            $sheets[] = new PhysicalCountUserSheet($this->payload, $user, $this->mainSheetTitle);
        }

        return $sheets;
    }

    protected function statusSheetTitle(?string $status): ?string
    {
        return match ($status) {
            'matched' => 'Macheado',
            'missing' => 'Faltante',
            'surplus' => 'Sobrante',
            'not_found' => 'No encontrado',
            default => null,
        };
    }
}
