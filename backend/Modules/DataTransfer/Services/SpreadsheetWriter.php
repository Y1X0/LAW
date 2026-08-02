<?php

namespace Modules\DataTransfer\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * كاتب Excel عام (.xlsx) — يبثّ الملف للتنزيل. لا منطق أعمال هنا؛ فقط تحويل
 * (رؤوس + صفوف) إلى ملف. RTL افتراضياً لأن النظام عربي.
 */
class SpreadsheetWriter
{
    /**
     * @param  string[]  $headers
     * @param  array<int, array<int, scalar|null>>  $rows
     */
    public function download(string $filename, string $sheetTitle, array $headers, array $rows): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31) ?: 'Sheet1');
        $sheet->setRightToLeft(true);

        $sheet->fromArray($headers, null, 'A1');
        if ($rows !== []) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer, $spreadsheet) {
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
