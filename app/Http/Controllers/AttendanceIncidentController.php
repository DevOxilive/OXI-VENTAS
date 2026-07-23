<?php

namespace App\Http\Controllers;

use App\Events\AttendanceChanged;
use App\Models\AttendanceIncident;
use App\Models\Employee;
use App\Services\SystemAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttendanceIncidentController extends Controller
{
    public function __construct(private readonly SystemAuditService $audit) {}

    public function index()
    {
        return Inertia::render('HumanResources/AttendanceIncidents', ['incidents' => AttendanceIncident::query()->with(['employee','authorizedBy'])->latest('incident_date')->paginate(30), 'employees' => Employee::query()->where('employment_status', '!=', 'Inactivo')->orderBy('first_name')->get(['id','first_name','last_name'])->map(fn ($employee) => ['value' => $employee->id, 'label' => trim($employee->first_name.' '.$employee->last_name)])]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['employee_id' => ['required','exists:employees,id'], 'incident_date' => ['required','date'], 'incident_time' => ['nullable','date_format:H:i'], 'estimated_arrival_at' => ['nullable','date_format:H:i'], 'reason' => ['required','string','max:2000']]);
        $data['type'] = 'attendance';
        $incident = AttendanceIncident::create($data);
        $this->audit->record('attendance_incident', 'create', 'success', $request, ['record_type' => AttendanceIncident::class, 'record_id' => $incident->id, 'record_label' => $incident->type]);
        broadcast(new AttendanceChanged(0, 'incident_created', $request->user()->id));
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
        $this->audit->record('attendance_incident', 'review', 'success', $request, ['record_type' => AttendanceIncident::class, 'record_id' => $attendanceIncident->id, 'record_label' => $attendanceIncident->type]);
        broadcast(new AttendanceChanged(0, 'incident_'.$data['status'], $request->user()->id));
        return back()->with('success', 'Incidencia actualizada correctamente.');
    }

    public function update(Request $request, AttendanceIncident $attendanceIncident)
    {
        abort_if($attendanceIncident->status !== 'pending', 422, 'Solo se pueden editar incidencias pendientes.');
        $data = $request->validate(['employee_id' => ['required','exists:employees,id'], 'incident_date' => ['required','date'], 'estimated_arrival_at' => ['nullable','date_format:H:i'], 'reason' => ['required','string','max:2000']]);
        $attendanceIncident->update($data);
        broadcast(new AttendanceChanged(0, 'incident_updated', $request->user()->id));
        return back()->with('success', 'Incidencia actualizada correctamente.');
    }

    public function destroy(Request $request, AttendanceIncident $attendanceIncident)
    {
        abort_if($attendanceIncident->status !== 'pending', 422, 'Solo se pueden eliminar incidencias pendientes.');
        $attendanceIncident->delete();
        broadcast(new AttendanceChanged(0, 'incident_deleted', $request->user()->id));
        return back()->with('success', 'Incidencia eliminada correctamente.');
    }
}
