<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FinanceEntityType;
use App\Http\Controllers\Controller;
use App\Models\FinanceEntity;
use App\Services\EntityReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminReportController extends Controller
{
    public function __construct(private readonly EntityReportService $reports) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'finance_entity_id' => ['nullable', 'integer', 'exists:finance_entities,id'],
            'type' => ['nullable', 'in:FAMILY,BUSINESS'],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'required_with:from', 'after_or_equal:from'],
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        $entities = FinanceEntity::query()
            ->when($validated['finance_entity_id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($validated['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $rows = $entities->map(fn (FinanceEntity $entity) => $this->reports->report($entity, $from, $to));
        $familyRows = $rows->where('entity_type', FinanceEntityType::FAMILY->value)->values();
        $businessRows = $rows->where('entity_type', FinanceEntityType::BUSINESS->value)->values();

        return view('admin.reports.index', [
            'title' => 'Laporan Konsolidasi',
            'entities' => FinanceEntity::query()->orderBy('name')->get(['id', 'name', 'type', 'public_id']),
            'rows' => $rows,
            'familyRows' => $familyRows,
            'businessRows' => $businessRows,
            'filters' => [
                'finance_entity_id' => $validated['finance_entity_id'] ?? '',
                'type' => $validated['type'] ?? '',
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }
}
