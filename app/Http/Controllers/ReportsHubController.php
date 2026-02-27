<?php

namespace App\Http\Controllers;

use App\ReportsHub\Contracts\Report;
use App\ReportsHub\ReportRegistry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsHubController extends Controller
{
    public function __construct(protected ReportRegistry $registry)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $reports = collect($this->registry->all());

        if ($q !== '') {
            $qq = mb_strtolower($q);
            $reports = $reports->filter(function (Report $r) use ($qq) {
                $hay = mb_strtolower($r->module() . ' ' . $r->name() . ' ' . ((string) $r->description()));
                return str_contains($hay, $qq);
            });
        }

        $grouped = $reports
            ->sortBy(fn (Report $r) => $r->module() . '|' . $r->name())
            ->groupBy(fn (Report $r) => $r->module())
            ->toArray();

        return view('reports_hub.index', [
            'grouped' => $grouped,
            'q' => $q,
        ]);
    }

    public function show(Request $request, string $key)
    {
        $report = $this->getReportOrFail($key);

        // Validate filters from GET query
        $filters = $request->validate($report->rules());

        $query = $report->query($filters);

        $perPage = (int) $request->query('per_page', 50);
        $perPage = max(10, min(200, $perPage));

        $rows = $query->paginate($perPage)->withQueryString();

        $totals = $report->totals($report->query($filters), $filters);

        return view('reports_hub.tabular', [
            'report' => $report,
            'filters' => $filters,
            'filterDefs' => $report->filters($request),
            'columns' => $report->columns(),
            'rows' => $rows,
            'totals' => $totals,
        ]);
    }

    public function print(Request $request, string $key)
    {
        $report = $this->getReportOrFail($key);
        $filters = $request->validate($report->rules());

        $query = $report->query($filters);

        $rows = $query->get();

        $totals = $report->totals($report->query($filters), $filters);

        return view('reports_hub.tabular_export', [
            'report' => $report,
            'filters' => $filters,
            'filterDefs' => $report->filters($request),
            'columns' => $report->columns(),
            'rows' => $rows,
            'totals' => $totals,
            'exportType' => 'print',
        ]);
    }

    public function pdf(Request $request, string $key)
    {
        $report = $this->getReportOrFail($key);
        $filters = $request->validate($report->rules());

        $query = $report->query($filters);

        // Guardrail: PDFs with huge row counts become very heavy (Dompdf).
        $maxRows = 2000;
        $count = (clone $query)->count();
        if ($count > $maxRows) {
            return redirect()
                ->route('reports-hub.show', $report->key())
                ->withInput()
                ->with('warning', "Too many rows ({$count}) for PDF. Please narrow filters or export CSV.");
        }

        $rows = $query->get();

        $totals = $report->totals($report->query($filters), $filters);

        $data = [
            'report' => $report,
            'filters' => $filters,
            'filterDefs' => $report->filters($request),
            'columns' => $report->columns(),
            'rows' => $rows,
            'totals' => $totals,
            'exportType' => 'pdf',
        ];

        $pdf = Pdf::loadView('reports_hub.tabular_export', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download($report->filename($filters, 'pdf'));
    }

    public function csv(Request $request, string $key): StreamedResponse
    {
        $report = $this->getReportOrFail($key);
        $filters = $request->validate($report->rules());

        $query = $report->query($filters);

        $columns = $report->columns();
        $headers = array_map(fn ($c) => (string) ($c['label'] ?? ''), $columns);

        $filename = $report->filename($filters, 'csv');

        return response()->streamDownload(function () use ($query, $report, $columns, $headers) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM for Excel
            fwrite($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, $headers);

            foreach ($query->cursor() as $row) {
                $line = [];
                foreach ($columns as $col) {
                    $val = $report->resolveValue($col, $row, true);
                    // Normalize new lines for CSV
                    if (is_string($val)) {
                        $val = preg_replace("/\r\n|\r|\n/", ' ', $val);
                    }
                    $line[] = $val;
                }
                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function getReportOrFail(string $key): Report
    {
        $report = $this->registry->find($key);
        abort_if(! $report, 404, 'Report not found');
        return $report;
    }
}
