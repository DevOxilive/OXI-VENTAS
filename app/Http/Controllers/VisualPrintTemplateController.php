<?php

namespace App\Http\Controllers;

use App\Models\TicketTemplate;
use App\Support\VisualPrintLayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VisualPrintTemplateController extends Controller
{
    private function authorizeKind(Request $request, string $kind, bool $write = false): void
    {
        abort_unless(in_array($kind, VisualPrintLayout::KINDS, true), 404);
        $prefix = match ($kind) {
            'label' => 'systems.labels',
            'closure' => 'systems.cash-closure-tickets',
            default => 'systems.tickets',
        };
        abort_unless($request->user()?->hasPermission($prefix.'.update') || (!$write && $request->user()?->hasPermission($prefix.'.view')), 403);
    }

    private function payload(?TicketTemplate $record): array
    {
        return ['name' => $record?->name, 'layout' => $record?->settings['draft'] ?? null,
            'active' => (bool) $record?->is_active, 'published' => $record?->settings['published'] ?? null,
            'revision' => VisualPrintLayout::revision($record)];
    }

    public function show(Request $request, string $kind)
    {
        $this->authorizeKind($request, $kind);
        return Inertia::render('Printers/VisualEditor', ['kind' => $kind, 'saved' => $this->payload(VisualPrintLayout::record($kind))]);
    }

    public function save(Request $request, string $kind)
    {
        $this->authorizeKind($request, $kind, true);
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'layout' => ['required', 'array'], 'revision' => ['present', 'nullable', 'string']]);
        $layout = VisualPrintLayout::validate($data['layout']);
        $record = DB::transaction(function () use ($kind, $data, $layout) {
            // The unique slug also protects the first concurrent creation.
            $record = TicketTemplate::query()->where('slug', 'visual-v2-'.$kind)->lockForUpdate()->first();
            $this->checkRevision($record, (string) ($data['revision'] ?? ''));
            if (!$record) $record = new TicketTemplate(['slug' => 'visual-v2-'.$kind, 'is_active' => false]);
            $record->name = $data['name'];
            $record->settings = ['draft' => $layout, 'published' => $record->settings['published'] ?? null];
            $record->save();
            return $record->fresh();
        });
        return response()->json($this->payload($record));
    }

    public function activate(Request $request, string $kind)
    {
        $this->authorizeKind($request, $kind, true);
        $data = $request->validate(['active' => ['required', 'boolean'], 'revision' => ['required', 'string']]);
        $record = DB::transaction(function () use ($kind, $data) {
            $record = TicketTemplate::query()->where('slug', 'visual-v2-'.$kind)->lockForUpdate()->firstOrFail();
            $this->checkRevision($record, $data['revision']);
            if ($data['active']) {
                $draft = VisualPrintLayout::validate($record->settings['draft'] ?? []);
                $record->settings = [...$record->settings, 'published' => $draft];
            }
            $record->is_active = $data['active'];
            $record->save();
            return $record->fresh();
        });
        return response()->json($this->payload($record));
    }

    private function checkRevision(?TicketTemplate $record, string $revision): void
    {
        if (!hash_equals(VisualPrintLayout::revision($record), $revision)) {
            throw ValidationException::withMessages(['revision' => 'Este diseño cambió en otra sesión. Exporta tu borrador y recarga antes de guardar.']);
        }
    }
}
