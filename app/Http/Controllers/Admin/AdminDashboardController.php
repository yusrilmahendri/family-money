<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FinanceEntityType;
use App\Http\Controllers\Controller;
use App\Models\FinanceEntity;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'title' => 'Admin Dashboard',
            'totalEntities' => FinanceEntity::query()->count(),
            'familyCount' => FinanceEntity::query()->where('type', FinanceEntityType::FAMILY)->count(),
            'businessCount' => FinanceEntity::query()->where('type', FinanceEntityType::BUSINESS)->count(),
            'activeCount' => FinanceEntity::query()->where('is_active', true)->count(),
            'inactiveCount' => FinanceEntity::query()->where('is_active', false)->count(),
        ]);
    }
}
