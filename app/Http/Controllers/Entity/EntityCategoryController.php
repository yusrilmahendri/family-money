<?php

namespace App\Http\Controllers\Entity;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\FinanceEntity;
use App\Support\FinanceOwnership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EntityCategoryController extends Controller
{
    public function index(FinanceEntity $financeEntity): View
    {
        return view('entity.categories.index', [
            'entity' => $financeEntity,
            'categories' => $financeEntity->categories()->orderBy('name')->get(),
            'title' => 'Kategori',
        ]);
    }

    public function create(FinanceEntity $financeEntity): View
    {
        return view('entity.categories.create', [
            'entity' => $financeEntity,
            'title' => 'Tambah Kategori',
        ]);
    }

    public function store(Request $request, FinanceEntity $financeEntity): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where('finance_entity_id', $financeEntity->id),
            ],
            'finance_entity_id' => ['prohibited'],
        ]);

        $financeEntity->categories()->create([
            'name' => $validated['name'],
            'context' => FinanceOwnership::contextFor($financeEntity),
        ]);

        return redirect()->route('entity.categories.index', $financeEntity)->with('success', 'Kategori disimpan.');
    }

    public function edit(FinanceEntity $financeEntity, Category $category): View
    {
        $this->owned($financeEntity, $category);

        return view('entity.categories.edit', [
            'entity' => $financeEntity,
            'category' => $category,
            'title' => 'Edit Kategori',
        ]);
    }

    public function update(Request $request, FinanceEntity $financeEntity, Category $category): RedirectResponse
    {
        $this->owned($financeEntity, $category);
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name')->where('finance_entity_id', $financeEntity->id)->ignore($category),
            ],
            'finance_entity_id' => ['prohibited'],
        ]);

        $category->update([
            'name' => $validated['name'],
            'context' => FinanceOwnership::contextFor($financeEntity),
        ]);

        return redirect()->route('entity.categories.index', $financeEntity)->with('success', 'Kategori diperbarui.');
    }

    public function destroy(FinanceEntity $financeEntity, Category $category): RedirectResponse
    {
        $this->owned($financeEntity, $category);
        $category->delete();

        return redirect()->route('entity.categories.index', $financeEntity)->with('success', 'Kategori dihapus.');
    }

    private function owned(FinanceEntity $entity, Category $category): void
    {
        abort_unless((int) $category->finance_entity_id === (int) $entity->id, 404);
    }
}
