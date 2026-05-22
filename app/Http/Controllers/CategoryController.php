<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CategoryController extends Controller
{
    public function data()
    {
        $categories = Category::orderBy('name', 'asc');

        return DataTables::of($categories)
            ->addColumn('action', 'category.action')
            ->addIndexColumn()
            ->rawColumns(['action'])
            ->toJson();
    }

    public function index()
    {
        return view('category.index', [
            'title' => 'Jenis Usaha',
        ]);
    }

    public function create()
    {
        return view('category.create', [
            'title' => 'Tambah Jenis Usaha',
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|max:255|unique:categories,name',
            ], [
                'name.unique' => 'Maaf, jenis usaha sudah terdaftar!',
                'name.required' => 'Nama jenis usaha tidak boleh kosong!',
            ]);

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
        return view('category.edit', [
            'title' => 'Ubah Jenis Usaha',
            'category' => $category,
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $validatedData = $request->validate([
            'name' => 'required|max:255|unique:categories,name,'.$category->id,
        ], [
            'name.unique' => 'Maaf, jenis usaha sudah terdaftar!',
            'name.required' => 'Nama jenis usaha tidak boleh kosong!',
        ]);

        $category->update($validatedData);

        return redirect()->route('categories.index')->with('info', 'Jenis usaha berhasil diperbarui!');
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return redirect()->route('categories.index')->with('danger', 'Jenis usaha berhasil dihapus!');
    }
}
