<?php

namespace App\Http\Controllers\Entity;

use App\Exports\EntityReportExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitPeriodRequest;
use App\Models\FinanceEntity;
use App\Services\EntityReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EntityReportController extends Controller
{
    public function __construct(private readonly EntityReportService $reports) {}

    public function index(ProfitPeriodRequest $request, FinanceEntity $financeEntity): View
    {
        [$from, $to] = $request->range();
        $report = $this->reports->report($financeEntity, $from, $to);

        return view('entity.reports.index', [
            'entity' => $financeEntity,
            'report' => $report,
            'from' => $report['from'],
            'to' => $report['to'],
            'title' => 'Laporan',
        ]);
    }

    public function excel(ProfitPeriodRequest $request, FinanceEntity $financeEntity): BinaryFileResponse
    {
        [$from, $to] = $request->range();
        $report = $this->reports->report($financeEntity, $from, $to);
        $filename = 'laporan-'.$financeEntity->slug.'-'.now()->toDateString().'.xlsx';

        return Excel::download(new EntityReportExport($report), $filename);
    }

    public function pdf(ProfitPeriodRequest $request, FinanceEntity $financeEntity): Response
    {
        [$from, $to] = $request->range();
        $report = $this->reports->report($financeEntity, $from, $to);
        $pdf = Pdf::loadView('entity.reports.pdf', [
            'entity' => $financeEntity,
            'report' => $report,
        ]);

        return $pdf->download('laporan-'.$financeEntity->slug.'-'.now()->toDateString().'.pdf');
    }
}
