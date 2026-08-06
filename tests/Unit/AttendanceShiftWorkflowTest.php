<?php

namespace Tests\Unit;

use Tests\TestCase;

class AttendanceShiftWorkflowTest extends TestCase
{
    public function test_attendance_records_keep_their_shift_context(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_05_000400_add_shift_context_to_attendance_records.php'));
        $model = file_get_contents(app_path('Models/AttendanceRecord.php'));

        $this->assertStringContainsString('attendance_schedule_assignment_id', $migration);
        $this->assertStringContainsString('shift_label', $migration);
        $this->assertStringContainsString('shift_order', $model);
        $this->assertStringContainsString('scheduleAssignment()', $model);
    }

    public function test_each_shift_requires_the_complete_attendance_sequence(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AttendanceController.php'));
        $frontend = file_get_contents(resource_path('js/Pages/Systems/Attendance.vue'));

        $this->assertStringContainsString("['check_in', 'meal_start', 'meal_end', 'check_out']", $controller);
        $this->assertStringContainsString('assertNextShiftRecord', $controller);
        $this->assertStringContainsString('attendance_schedule_assignment_id', $frontend);
        $this->assertStringContainsString('attendanceShifts', $frontend);
    }

    public function test_schedule_assignments_are_limited_to_sales_and_use_named_schedules(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/AttendanceScheduleAssignmentController.php'));
        $frontend = file_get_contents(resource_path('js/Pages/HumanResources/AttendanceScheduleAssignments.vue'));

        $this->assertStringContainsString("whereIn('name', ['Ventas', 'Vendedor'])", $controller);
        $this->assertStringContainsString('attendance_schedule_ids', $controller);
        $this->assertStringContainsString('resequenceEmployeeShifts', $controller);
        $this->assertStringNotContainsString('Orden del turno', $frontend);
        $this->assertStringContainsString('toggleSchedule', $frontend);
    }

    public function test_attendance_and_pos_support_every_assigned_branch(): void
    {
        $attendanceController = file_get_contents(app_path('Http/Controllers/AttendanceController.php'));
        $attendanceFrontend = file_get_contents(resource_path('js/Pages/Systems/Attendance.vue'));
        $salesController = file_get_contents(app_path('Http/Controllers/Ventas/SalesController.php'));

        $this->assertStringContainsString('attendance_branch_id', $attendanceController);
        $this->assertStringContainsString('Completa las cuatro asistencias', $attendanceController);
        $this->assertStringContainsString('attendanceBranches', $attendanceFrontend);
        $this->assertStringContainsString('Horario actual', $attendanceFrontend);
        $this->assertStringContainsString("! \$request->filled('branch') && \$allowedBranches->count() > 1", $salesController);
    }
}
