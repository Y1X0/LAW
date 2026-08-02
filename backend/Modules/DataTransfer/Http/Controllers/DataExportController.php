<?php

namespace Modules\DataTransfer\Http\Controllers;

use Illuminate\Http\Request;
use Modules\DataTransfer\Services\DataExportService;
use Modules\DataTransfer\Services\SpreadsheetWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * تصدير بيانات النظام إلى Excel (.xlsx). قراءة فقط — لا تعديل. كل نقطة محمية بصلاحية
 * عرض الكيان في routes/api.php. الموظفون: الحقول المالية تُخفى بلا employees.salary.view.
 */
class DataExportController
{
    public function __construct(
        private readonly DataExportService $data,
        private readonly SpreadsheetWriter $writer,
    ) {}

    public function employees(Request $request): StreamedResponse
    {
        $canViewSalary = $request->user()?->hasPermission('employees.salary.view') ?? false;

        return $this->send($this->data->employees($canViewSalary));
    }

    public function attendance(): StreamedResponse
    {
        return $this->send($this->data->attendance());
    }

    public function leaveRequests(): StreamedResponse
    {
        return $this->send($this->data->leaveRequests());
    }

    public function payrollItems(): StreamedResponse
    {
        return $this->send($this->data->payrollItems());
    }

    public function clients(): StreamedResponse
    {
        return $this->send($this->data->clients());
    }

    public function cases(): StreamedResponse
    {
        return $this->send($this->data->cases());
    }

    /** @param  array{title:string,headers:array,rows:array}  $dataset */
    private function send(array $dataset): StreamedResponse
    {
        $filename = $dataset['title'].'-'.date('Y-m-d').'.xlsx';

        return $this->writer->download($filename, $dataset['title'], $dataset['headers'], $dataset['rows']);
    }
}
