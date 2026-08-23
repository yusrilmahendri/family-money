<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfitPeriodRequest;
use App\Models\FinanceEntity;
use App\Services\BusinessProfitService;
use Illuminate\View\View;

class EntityProfitLossController extends Controller
{
    public function __construct(private readonly BusinessProfitService $profits) {}

    public function index(ProfitPeriodRequest $request, FinanceEntity $financeEntity): View
    {
        [$from, $to] = $request->range();
        $summary = $this->profits->summary($financeEntity, $from, $to);

        return view('entity.profit-loss.index', [
            'entity' => $financeEntity,
            'rows' => $summary['categories']->map(fn (array $row) => [
                'name' => $row['name'],
                'pendapatan' => $row['revenue'],
                'biaya' => $row['operational_expense'],
                'laba' => $row['profit'],
            ]),
            'incomeTotal' => $summary['revenue'],
            'expenseTotal' => $summary['operational_expense'],
            'profit' => $summary['profit'],
            'isLoss' => $summary['is_loss'],
            'capitalTotal' => $summary['capital_in'],
            'withdrawalTotal' => $summary['withdrawal_out'],
            'distributedProfit' => $summary['distributed_profit'],
            'undistributedProfit' => $summary['undistributed_profit'],
            'periodLabel' => $summary['period_label'],
            'from' => $summary['from'],
            'to' => $summary['to'],
            'title' => 'Laba / Rugi',
        ]);
    }
}
