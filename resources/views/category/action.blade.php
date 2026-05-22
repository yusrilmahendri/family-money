<a href="{{ route('categories.edit', $model->id) }}"
   class="btn btn-warning btn-sm">
  <i class="fa fa-edit"></i> Edit
</a>

<a href="{{ route('categories.destroy', $model->id) }}" class="btn btn-danger btn-sm"
   onclick="event.preventDefault(); if(confirm('Yakin ingin menghapus jenis usaha ini?')) document.getElementById('delete-category-{{ $model->id }}').submit();">
  <i class="fa fa-trash"></i> Hapus
</a>
<form id="delete-category-{{ $model->id }}" action="{{ route('categories.destroy', $model->id) }}" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>
