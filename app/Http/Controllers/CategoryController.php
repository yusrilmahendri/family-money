<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\FinanceContext;
use App\Services\FinanceContextService;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CategoryController extends Controller
{
    public function data()
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }
        $categories = Category::forContext(FinanceContext::USAHA_KEBUN)
            ->orderBy('name', 'asc');

        return DataTables::of($categories)
            ->addColumn('action', 'category.action')
            ->addIndexColumn()
            ->rawColumns(['action'])
            ->toJson();
    }

    public function index()
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }

        return view('category.index', [
            'title' => 'Jenis Usaha',
        ]);
    }

    public function create()
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }

        return view('category.create', [
            'title' => 'Tambah Jenis Usaha',
        ]);
    }

    public function store(Request $request)
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }
        $context = FinanceContext::USAHA_KEBUN;

        try {
            $validatedData = $request->validate([
                'name' => [
                    'required',
                    'max:255',
                    \Illuminate\Validation\Rule::unique('categories', 'name')->where('context', $context),
                ],
            ], [
                'name.unique' => 'Maaf, jenis usaha sudah terdaftar!',
                'name.required' => 'Nama jenis usaha tidak boleh kosong!',
            ]);

            $validatedData['context'] = $context;
            $category = Category::create($validatedData);

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'category' => $category,
                    'message' => 'Jenis usaha berhasil ditambahkan!',
                ]);
            }

            return redirect()->route('categories.index')->with('success', 'Jenis usaha berhasil ditambahkan!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->validator->errors()->first('name'),
                ], 422);
            }
            throw $e;
        }
    }

    public function show(string $id)
    {
        //
    }

    public function edit(Category $category)
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }
        return view('category.edit', [
            'title' => 'Ubah Jenis Usaha',
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }
        $validatedData = $request->validate([
            'name' => [
                'required',
                'max:255',
                \Illuminate\Validation\Rule::unique('categories', 'name')
                    ->where('context', $category->context)
                    ->ignore($category->id),
            ],
        ], [
            'name.unique' => 'Maaf, jenis usaha sudah terdaftar!',
            'name.required' => 'Nama jenis usaha tidak boleh kosong!',
        ]);

        $category->update($validatedData);

        return redirect()->route('categories.index')->with('info', 'Jenis usaha berhasil diperbarui!');
    }

    public function destroy(Category $category)
    {
        if ($r = FinanceContextService::guardFarm()) {
            return $r;
        }
        $category->delete();

        return redirect()->route('categories.index')->with('danger', 'Jenis usaha berhasil dihapus!');
    }
}
