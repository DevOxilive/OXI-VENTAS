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
        $auditParticipants = collect($this->payload['audits'] ?? [])
            ->flatMap(fn ($audit) => $audit->participants ?? collect());
        $selectedUserIds = collect($this->filters['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $this->statusFilter = $this->filters['status'] ?: null;
        $this->mainSheetTitle = $this->statusSheetTitle($this->statusFilter) ?? 'Concentrado';

        $this->users = $entries
            ->pluck('user')
            ->filter()
            ->merge($auditParticipants)
            ->when($selectedUserIds->isNotEmpty(), fn ($users) => $users
                ->filter(fn ($user) => $selectedUserIds->contains((int) $user->id)))
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    public function sheets(): array
    {
        $sheets = [
            new PhysicalCountDashboardSheet($this->payload, $this->filterLabels, $this->branchName, $this->mainSheetTitle),
            new PhysicalCountBranchSummarySheet($this->payload),
            new PhysicalCountAuditSummarySheet($this->payload),
            new PhysicalCountDifferencesSheet($this->payload),
            new PhysicalCountConcentratedSheet($this->payload, $this->users, $this->mainSheetTitle, $this->statusFilter),
        ];

        if ($this->statusFilter === null) {
            $sheets[] = new PhysicalCountConcentratedSheet($this->payload, $this->users, 'Macheado', 'matched');
            $sheets[] = new PhysicalCountConcentratedSheet($this->payload, $this->users, 'Faltante', 'missing');
            $sheets[] = new PhysicalCountConcentratedSheet($this->payload, $this->users, 'Sobrante', 'surplus');
            $sheets[] = new PhysicalCountConcentratedSheet($this->payload, $this->users, 'No encontrado', 'not_found');
        }

        foreach ($this->users as $user) {
            $sheets[] = new PhysicalCountUserSheet($this->payload, $user);
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
