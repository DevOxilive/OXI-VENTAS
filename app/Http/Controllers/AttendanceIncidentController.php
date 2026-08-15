<?php

namespace App\Http\Controllers;

use App\Events\AttendanceChanged;
use App\Events\RealtimeActivityLogged;
use App\Models\AttendanceIncident;
use App\Models\Employee;
use App\Services\SystemAuditService;
use App\Support\TablePagination;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceIncidentController extends Controller
{
    public function __construct(private readonly SystemAuditService $audit) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'per_page' => ['nullable', 'integer'],
        ]);
        $filters['per_page'] = TablePagination::resolvePerPage($request);

        $incidents = AttendanceIncident::query()
            ->with([
                'employee:id,first_name,last_name',
                'authorizedBy:id,name',
                'submittedBy:id,name',
            ])
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->where('incident_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->where('incident_date', '<=', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $like = '%'.$search.'%';

                $query->whereHas('employee', function ($employeeQuery) use ($like) {
                    $employeeQuery
                        ->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like]);
                });
            })
            ->latest('incident_date')
            ->latest()
            ->paginate($filters['per_page'])
            ->through(fn (AttendanceIncident $incident) => [
                'id' => $incident->id,
                'employee_id' => $incident->employee_id,
                'employee_name' => trim(($incident->employee?->first_name ?? '').' '.($incident->employee?->last_name ?? '')),
                'incident_date' => $incident->incident_date,
                'estimated_arrival_at' => $incident->estimated_arrival_at,
                'rest_day_requested' => $incident->rest_day_requested,
                'rest_day_date' => $incident->rest_day_date,
                'make_up_date' => $incident->make_up_date,
                'reason' => $incident->reason,
                'status' => $incident->status,
                'created_at' => $incident->created_at,
                'authorized_at' => $incident->authorized_at,
                'authorized_by' => $incident->authorizedBy?->name,
                'submitted_by' => $incident->submittedBy?->name,
            ])
            ->withQueryString();

        return Inertia::render('HumanResources/AttendanceIncidents', [
            'incidents' => $incidents,
            'employees' => Employee::query()
                ->where('employment_status', '!=', 'Inactivo')
                ->orderBy('first_name')
                ->get(['id','first_name','last_name'])
                ->map(fn ($employee) => ['value' => $employee->id, 'label' => trim($employee->first_name.' '.$employee->last_name)]),
            'filters' => $filters,
            'statuses' => [
                ['value' => 'pending', 'label' => 'Pendiente'],
                ['value' => 'approved', 'label' => 'Aprobada'],
                ['value' => 'rejected', 'label' => 'Denegada'],
            ],
            'notificationSummary' => $this->notificationSummary($request),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateIncident($request);
        $data['type'] = 'attendance';
        $data['submitted_by'] = $request->user()->id;
        $incident = AttendanceIncident::create($data);
        $incident->load('employee');
        $this->audit->record('attendance_incident', 'create', 'success', $request, ['record_type' => AttendanceIncident::class, 'record_id' => $incident->id, 'record_label' => $incident->type]);
        event(new RealtimeActivityLogged(
            'Nueva incidencia pendiente de autorizacion: '.$this->employeeName($incident),
            'Capital Humano',
            'attendance_incident_created',
            $this->employeeName($incident),
        ));
        broadcast(new AttendanceChanged($incident->id, 'incident_created', $request->user()->id));
        return back()->with('success', 'Incidencia enviada para autorización.');
    }

    public function review(Request $request, AttendanceIncident $attendanceIncident)
    {
        $data = $request->validate(['status' => ['required','in:approved,rejected'], 'authorization_notes' => ['nullable','string','max:2000']]);
        $requiredPermission = $data['status'] === 'approved'
            ? 'attendance.incidents.approve'
            : 'attendance.incidents.reject';
        abort_unless($request->user()->hasPermission($requiredPermission), 403);
        $attendanceIncident->update($data + ['authorized_by' => $request->user()->id, 'authorized_at' => now()]);
        $attendanceIncident->load(['employee', 'submittedBy']);
        $this->audit->record('attendance_incident', 'review', 'success', $request, ['record_type' => AttendanceIncident::class, 'record_id' => $attendanceIncident->id, 'record_label' => $attendanceIncident->type]);
        event(new RealtimeActivityLogged(
            'Incidencia '.$this->statusLabel($data['status']).': '.$this->employeeName($attendanceIncident),
            'Capital Humano',
            'attendance_incident_'.$data['status'],
            $this->employeeName($attendanceIncident),
            [$attendanceIncident->submitted_by],
        ));
        broadcast(new AttendanceChanged($attendanceIncident->id, 'incident_'.$data['status'], $request->user()->id));
        return back()->with('success', 'Incidencia actualizada correctamente.');
    }

    public function update(Request $request, AttendanceIncident $attendanceIncident)
    {
        abort_if($attendanceIncident->status !== 'pending', 422, 'Solo se pueden editar incidencias pendientes.');
        $data = $this->validateIncident($request);
        $attendanceIncident->update($data);
        broadcast(new AttendanceChanged($attendanceIncident->id, 'incident_updated', $request->user()->id));
        return back()->with('success', 'Incidencia actualizada correctamente.');
    }

    public function destroy(Request $request, AttendanceIncident $attendanceIncident)
    {
        abort_if($attendanceIncident->status !== 'pending', 422, 'Solo se pueden eliminar incidencias pendientes.');
        $attendanceIncident->delete();
        broadcast(new AttendanceChanged($attendanceIncident->id, 'incident_deleted', $request->user()->id));
        return back()->with('success', 'Incidencia eliminada correctamente.');
    }

    private function validateIncident(Request $request): array
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'incident_date' => ['required', 'date'],
            'incident_time' => ['nullable', 'date_format:H:i'],
            'estimated_arrival_at' => ['nullable', 'date_format:H:i'],
            'rest_day_requested' => ['nullable', 'boolean'],
            'rest_day_date' => ['nullable', 'required_if_accepted:rest_day_requested', 'date'],
            'make_up_date' => ['nullable', 'required_if_accepted:rest_day_requested', 'date', 'after_or_equal:rest_day_date'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $data['rest_day_requested'] = $request->boolean('rest_day_requested');

        if (! $data['rest_day_requested']) {
            $data['rest_day_date'] = null;
            $data['make_up_date'] = null;
        }

        return $data;
    }

    private function notificationSummary(Request $request): array
    {
        $user = $request->user();
        $canReview = $user->hasPermission('attendance.incidents.approve')
            || $user->hasPermission('attendance.incidents.reject');

        $query = AttendanceIncident::query()
            ->with('employee')
            ->latest('updated_at')
            ->latest();

        if ($canReview) {
            $query->where('status', 'pending');
        } else {
            $query
                ->where('submitted_by', $user->id)
                ->whereIn('status', ['approved', 'rejected']);
        }

        $items = $query
            ->limit(8)
            ->get()
            ->map(fn (AttendanceIncident $incident) => [
                'id' => $incident->id,
                'employee_name' => $this->employeeName($incident),
                'status' => $incident->status,
                'status_label' => $this->statusLabel($incident->status),
                'incident_date' => $incident->incident_date,
                'updated_at' => $incident->updated_at,
            ]);

        return [
            'mode' => $canReview ? 'review' : 'submitted',
            'count' => $items->count(),
            'items' => $items,
        ];
    }

    private function employeeName(AttendanceIncident $incident): string
    {
        return trim(($incident->employee?->first_name ?? '').' '.($incident->employee?->last_name ?? '')) ?: 'Empleado sin nombre';
    }

    private function statusLabel(string $status): string
    {
        return [
            'pending' => 'pendiente',
            'approved' => 'aprobada',
            'rejected' => 'denegada',
        ][$status] ?? $status;
    }
}
