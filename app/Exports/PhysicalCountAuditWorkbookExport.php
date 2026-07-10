<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PhysicalCountAuditWorkbookExport implements WithMultipleSheets
{
    protected Collection $users;

    public function __construct(
        protected array $payload,
        protected array $filters,
        protected array $filterLabels,
        protected string $branchName
    ) {
        $entries = collect($this->payload['entries'] ?? []);
        $auditParticipants = collect($this->payload['audits'] ?? [])
            ->flatMap(fn ($audit) => $audit->participants ?? collect());

        $this->users = $entries
            ->pluck('user')
            ->filter()
            ->merge($auditParticipants)
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    public function sheets(): array
    {
        $sheets = [
            new PhysicalCountDashboardSheet($this->payload, $this->filterLabels, $this->branchName),
            new PhysicalCountConcentratedSheet($this->payload, $this->users),
        ];

        foreach ($this->users as $user) {
            $sheets[] = new PhysicalCountUserSheet($this->payload, $user);
        }

        return $sheets;
    }
}
