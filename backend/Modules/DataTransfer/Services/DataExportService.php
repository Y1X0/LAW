<?php

namespace Modules\DataTransfer\Services;

use Modules\Attendance\Models\AttendanceRecord;
use Modules\HR\Models\Employee;
use Modules\Leave\Models\LeaveRequest;
use Modules\Legal\Models\Client;
use Modules\Legal\Models\LegalCase;
use Modules\Payroll\Models\PayrollItem;

/**
 * يبني مجموعات التصدير (رؤوس + صفوف) لكل كيان. رؤوس بمفاتيح الأعمدة (لا تسميات عربية)
 * ليكون التصدير/الاستيراد متسقاً (المرحلة 2 تُطابق على نفس المفاتيح). قراءة فقط.
 */
class DataExportService
{
    /** الموظفون — الحقول المالية/البنكية تُدرَج فقط بصلاحية عرض الراتب (اتساقاً مع القناع). */
    public function employees(bool $canViewSalary): array
    {
        $headers = ['employee_no', 'national_id', 'full_name_ar', 'full_name_en', 'branch', 'department', 'job_title', 'status', 'hire_date'];
        if ($canViewSalary) {
            array_push($headers, 'basic_salary', 'bank_name', 'bank_account');
        }

        $rows = Employee::with(['branch:id,name', 'department:id,name'])->orderBy('id')->get()
            ->map(function (Employee $e) use ($canViewSalary) {
                $row = [
                    $e->employee_no, $e->national_id, $e->full_name_ar, $e->full_name_en,
                    $e->branch?->name, $e->department?->name, $e->job_title, $e->status,
                    $e->hire_date?->format('Y-m-d'),
                ];
                if ($canViewSalary) {
                    array_push($row, $e->basic_salary, $e->bank_name, $e->bank_account);
                }

                return $row;
            })->all();

        return ['title' => 'employees', 'headers' => $headers, 'rows' => $rows];
    }

    public function attendance(): array
    {
        $headers = ['employee_no', 'employee', 'work_date', 'check_in', 'check_out', 'status', 'late_minutes', 'worked_minutes', 'overtime_minutes'];

        $rows = AttendanceRecord::with('employee:id,employee_no,full_name_ar')->orderByDesc('work_date')->get()
            ->map(fn (AttendanceRecord $r) => [
                $r->employee?->employee_no, $r->employee?->full_name_ar,
                $r->work_date?->format('Y-m-d'),
                $r->check_in?->format('Y-m-d H:i'), $r->check_out?->format('Y-m-d H:i'),
                $r->status, $r->late_minutes, $r->worked_minutes, $r->overtime_minutes,
            ])->all();

        return ['title' => 'attendance', 'headers' => $headers, 'rows' => $rows];
    }

    public function leaveRequests(): array
    {
        $headers = ['employee_no', 'employee', 'leave_type', 'start_date', 'end_date', 'days', 'status'];

        $rows = LeaveRequest::with(['employee:id,employee_no,full_name_ar', 'leaveType:id,name'])->orderByDesc('start_date')->get()
            ->map(fn (LeaveRequest $l) => [
                $l->employee?->employee_no, $l->employee?->full_name_ar, $l->leaveType?->name,
                $l->start_date?->format('Y-m-d'), $l->end_date?->format('Y-m-d'),
                $l->days, $l->status,
            ])->all();

        return ['title' => 'leave-requests', 'headers' => $headers, 'rows' => $rows];
    }

    /** بنود الرواتب — محمية بصلاحية payroll.view (وهي صلاحية تحمل الرواتب). */
    public function payrollItems(): array
    {
        $headers = ['payroll_run_id', 'employee_no', 'employee', 'currency', 'basic_salary', 'allowances_total', 'deductions_total', 'gross_amount', 'net_amount'];

        $rows = PayrollItem::with('employee:id,employee_no,full_name_ar')->orderBy('payroll_run_id')->orderBy('id')->get()
            ->map(fn (PayrollItem $p) => [
                $p->payroll_run_id, $p->employee?->employee_no, $p->employee?->full_name_ar,
                $p->currency, $p->basic_salary, $p->allowances_total, $p->deductions_total,
                $p->gross_amount, $p->net_amount,
            ])->all();

        return ['title' => 'payroll-items', 'headers' => $headers, 'rows' => $rows];
    }

    public function clients(): array
    {
        $headers = ['name', 'type', 'phone', 'email', 'national_id', 'status'];

        $rows = Client::orderBy('id')->get()
            ->map(fn (Client $c) => [$c->name, $c->type, $c->phone, $c->email, $c->national_id, $c->status])->all();

        return ['title' => 'clients', 'headers' => $headers, 'rows' => $rows];
    }

    public function cases(): array
    {
        $headers = ['internal_number', 'title', 'client', 'case_type', 'status', 'opened_date'];

        $rows = LegalCase::with('client:id,name')->orderBy('id')->get()
            ->map(fn (LegalCase $c) => [
                $c->internal_number, $c->title, $c->client?->name, $c->case_type, $c->status,
                $c->opened_date?->format('Y-m-d'),
            ])->all();

        return ['title' => 'cases', 'headers' => $headers, 'rows' => $rows];
    }
}
