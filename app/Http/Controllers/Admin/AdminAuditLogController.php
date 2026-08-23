<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\AuditActorType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Budget;
use App\Models\BudgetActivity;
use App\Models\BusinessCapitalContribution;
use App\Models\DebtPayment;
use App\Models\FinanceAccount;
use App\Models\FinanceEntity;
use App\Models\FinanceEntityAccessToken;
use App\Models\FinanceTransfer;
use App\Models\GoalContribution;
use App\Models\Income;
use App\Models\OwnerWithdrawal;
use App\Models\ProfitDistribution;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('financeEntity')
            ->when($request->filled('finance_entity_id'), function ($query) use ($request): void {
                $query->where('finance_entity_id', $request->integer('finance_entity_id'));
            })
            ->when($request->filled('actor_type'), function ($query) use ($request): void {
                $query->where('actor_type', $request->string('actor_type'));
            })
            ->when($request->filled('action'), function ($query) use ($request): void {
                $query->where('action', $request->string('action'));
            })
            ->when($request->filled('auditable_type'), function ($query) use ($request): void {
                $query->where('auditable_type', $request->string('auditable_type'));
            })
            ->when($request->filled('from'), function ($query) use ($request): void {
                $query->whereDate('created_at', '>=', $request->date('from'));
            })
            ->when($request->filled('to'), function ($query) use ($request): void {
                $query->whereDate('created_at', '<=', $request->date('to'));
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $adminNames = User::query()
            ->whereIn('id', $logs->getCollection()->where('actor_type', AuditActorType::ADMIN)->pluck('actor_id')->filter())
            ->pluck('name', 'id');

        return view('admin.audit-logs.index', [
            'title' => 'Audit Logs',
            'logs' => $logs,
            'adminNames' => $adminNames,
            'entities' => FinanceEntity::query()->orderBy('name')->get(['id', 'name', 'public_id', 'type']),
            'actorTypes' => AuditActorType::cases(),
            'actions' => AuditAction::cases(),
            'auditableTypes' => $this->auditableTypes(),
            'filters' => $request->only(['finance_entity_id', 'actor_type', 'action', 'auditable_type', 'from', 'to']),
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $auditLog->load('financeEntity');

        return view('admin.audit-logs.show', [
            'title' => 'Audit Log #'.$auditLog->id,
            'log' => $auditLog,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function auditableTypes(): array
    {
        $types = [
            FinanceEntity::class,
            FinanceEntityAccessToken::class,
            FinanceAccount::class,
            Transaction::class,
            Income::class,
            Budget::class,
            BudgetActivity::class,
            DebtPayment::class,
            GoalContribution::class,
            FinanceTransfer::class,
            BusinessCapitalContribution::class,
            OwnerWithdrawal::class,
            ProfitDistribution::class,
            Receivable::class,
            ReceivablePayment::class,
        ];

        return collect($types)
            ->mapWithKeys(fn (string $class) => [$class => class_basename($class)])
            ->all();
    }
}
