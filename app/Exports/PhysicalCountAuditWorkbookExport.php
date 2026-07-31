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
        $this->statusFilter = null;
        $this->mainSheetTitle = 'Concentrado';

        $visibleEntryKeys = collect($this->payload['reportRows'] ?? [])
            ->where('row_type', 'counted')
            ->map(fn (array $row) => ($row['physical_count_id'] ?? null) . ':' . ($row['branch_product_id'] ?? null))
            ->filter(fn (string $key) => ! str_starts_with($key, ':') && ! str_ends_with($key, ':'))
            ->unique()
            ->values();

        $visibleEntries = $visibleEntryKeys->isEmpty()
            ? collect()
            : $entries->filter(fn ($entry) => $visibleEntryKeys->contains($entry->physical_count_id . ':' . $entry->branch_product_id));
        $this->payload['entries'] = $visibleEntries->values();

        $entryUsers = $visibleEntries
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();
        $auditParticipants = collect($this->payload['audits'] ?? [])
            ->flatMap(fn ($audit) => $audit->participants ?? [])
            ->filter()
            ->unique('id')
            ->values();
        $selectedUserIds = collect($this->filters['audit_filters'] ?? [])
            ->flatMap(fn ($configuration) => $configuration['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $availableUsers = $auditParticipants
            ->merge($entryUsers)
            ->unique('id')
            ->values();

        $this->users = $selectedUserIds->isEmpty()
            ? $availableUsers
            : $availableUsers->filter(fn ($user) => $selectedUserIds->contains((int) $user->id))->values();
    }

    public function sheets(): array
    {
        $sheets = [
            new PhysicalCountDashboardSheet($this->payload, $this->filterLabels, $this->branchName, $this->mainSheetTitle, $this->users, $this->statusFilter),
            new PhysicalCountConcentratedSheet($this->payload, $this->users, $this->mainSheetTitle, $this->statusFilter),
        ];

        $selectedResults = collect($this->filters['selected_results'] ?? []);
        foreach (['matched', 'missing', 'surplus'] as $result) {
            if ($selectedResults->contains($result)) {
                $sheets[] = new PhysicalCountConcentratedSheet(
                    $this->payload,
                    $this->users,
                    $this->statusSheetTitle($result),
                    $result
                );
            }
        }
        if ($selectedResults->contains('not_found')) {
            $sheets[] = new PhysicalCountPendingSheet($this->payload);
        }

        $lotAuditIds = collect($this->filters['audit_filters'] ?? [])
            ->filter(fn ($configuration) => (bool) ($configuration['include_lots'] ?? false))
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values();
        if ($lotAuditIds->isNotEmpty()) {
            $sheets[] = new PhysicalCountLotsSheet($this->payload, $lotAuditIds);
        }

        $usedTitles = collect($sheets)->map(fn ($sheet) => mb_strtolower($sheet->title()))->all();

        foreach ($this->users as $user) {
            $baseTitle = $this->safeSheetTitle((string) $user->name);
            $title = $baseTitle;
            $suffix = 2;

            while (in_array(mb_strtolower($title), $usedTitles, true)) {
                $suffixText = ' ' . $suffix++;
                $title = mb_substr($baseTitle, 0, 31 - mb_strlen($suffixText)) . $suffixText;
            }

            $usedTitles[] = mb_strtolower($title);
            $sheets[] = new PhysicalCountUserSheet($this->payload, $user, $title);
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

    protected function safeSheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\\/?*\\[\\]:]/', '', $title);

        return mb_substr(trim((string) $title) ?: 'Usuario', 0, 31);
    }
}
