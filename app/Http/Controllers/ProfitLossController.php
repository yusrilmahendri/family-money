<?php

namespace App\Http\Controllers;

use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\Category;
use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProfitLossController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) ($request->get('year') ?: now()->year);
        $month = $request->get('month'); // null = sepanjang tahun
        $categoryId = $request->get('category_id'); // null = semua

        $periodLabel = $month
            ? Carbon::create($year, (int) $month, 1)->translatedFormat('F Y')
            : 'Sepanjang Tahun '.$year;

        $rows = Category::orderBy('name')->get()
            ->when($categoryId, fn ($c) => $c->where('id', $categoryId))
            ->map(function (Category $cat) use ($year, $month) {
                $incomeQuery = Income::where('category_id', $cat->id)->whereYear('income_date', $year);
                if ($month) {
                    $incomeQuery->whereMonth('income_date', $month);
                }
                $pendapatan = (float) $incomeQuery->sum('amount');

                $budgetIds = Budget::where('category_id', $cat->id)->pluck('id');
                $activityQuery = BudgetActivity::whereIn('budget_id', $budgetIds)
                    ->whereYear('activity_date', $year);
                if ($month) {
                    $activityQuery->whereMonth('activity_date', $month);
                }
                $biaya = (float) $activityQuery->sum('amount');

                return [
                    'category_id' => $cat->id,
                    'name' => $cat->name,
                    'pendapatan' => $pendapatan,
                    'biaya' => $biaya,
                    'laba' => $pendapatan - $biaya,
                    'margin' => $pendapatan > 0 ? (($pendapatan - $biaya) / $pendapatan) * 100 : null,
                ];
            })
            ->filter(fn ($r) => $r['pendapatan'] > 0 || $r['biaya'] > 0)
            ->values();

        $totalPendapatan = (float) $rows->sum('pendapatan');
        $totalBiaya = (float) $rows->sum('biaya');
        $totalLaba = $totalPendapatan - $totalBiaya;

        // Tren 12 bulan tahun ini (untuk grafik)
        $tren = [];
        for ($m = 1; $m <= 12; $m++) {
            $incomeQ = Income::whereYear('income_date', $year)->whereMonth('income_date', $m);
            $actQ = BudgetActivity::whereYear('activity_date', $year)->whereMonth('activity_date', $m);
            if ($categoryId) {
                $incomeQ->where('category_id', $categoryId);
                $actQ->whereIn('budget_id', Budget::where('category_id', $categoryId)->pluck('id'));
            }
            $pmas = (float) $incomeQ->sum('amount');
            $pkel = (float) $actQ->sum('amount');
            $tren[] = [
                'label' => Carbon::create($year, $m, 1)->translatedFormat('M'),
                'pendapatan' => $pmas,
                'biaya' => $pkel,
                'laba' => $pmas - $pkel,
            ];
        }

        return view('profit_loss.index', [
            'title' => 'Laporan Laba / Rugi',
            'year' => $year,
            'month' => $month,
            'category_id' => $categoryId,
            'period_label' => $periodLabel,
            'categories' => Category::orderBy('name')->get(),
            'rows' => $rows,
            'total_pendapatan' => $totalPendapatan,
            'total_biaya' => $totalBiaya,
            'total_laba' => $totalLaba,
            'tren' => $tren,
            'available_years' => $this->availableYears(),
        ]);
    }

    private function availableYears(): array
    {
        $years = collect();
        $years = $years->merge(Income::selectRaw('YEAR(income_date) as y')->distinct()->pluck('y'));
        $years = $years->merge(BudgetActivity::selectRaw('YEAR(activity_date) as y')->distinct()->pluck('y'));
        $years = $years->push((int) now()->year)->unique()->sort()->values()->all();

        return array_map('intval', $years);
    }
}
