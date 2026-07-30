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
            new PhysicalCountControlSheet($this->payload, $this->filterLabels, $this->branchName),
            new PhysicalCountConcentratedSheet($this->payload, $this->users, $this->mainSheetTitle, $this->statusFilter),
        ];

        $rounds = collect($this->payload['rounds'] ?? [])->sortBy([
            ['physical_count_id', 'asc'],
            ['round_number', 'asc'],
        ])->values();

        if ($rounds->isNotEmpty()) {
            $sheets[] = new PhysicalCountRoundsComparisonSheet($this->payload);

            foreach ($rounds as $round) {
                $audit = collect($this->payload['audits'] ?? [])->firstWhere('id', $round->physical_count_id);
                $baseTitle = sprintf(
                    'R%d %s %s',
                    $round->round_number,
                    $round->type === 'original' ? 'Original' : 'Reapertura',
                    $audit?->folio ? mb_substr($audit->folio, -4) : ''
                );
                $sheets[] = new PhysicalCountRoundSheet(
                    $round,
                    $this->payload,
                    $this->safeSheetTitle($baseTitle)
                );
            }
        }

        array_push(
            $sheets,
            new PhysicalCountConsolidatedCountsSheet($this->payload),
            new PhysicalCountPendingSheet($this->payload),
            new PhysicalCountDifferencesSheet($this->payload),
            new PhysicalCountAuditSummarySheet($this->payload),
            new PhysicalCountBranchSummarySheet($this->payload),
            new PhysicalCountCategorySummarySheet($this->payload),
        );

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
