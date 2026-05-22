<a href="{{ route('budgets.show', $model) }}"
   class="btn btn-info btn-sm">
  <i class="fa fa-eye"></i> Detail
</a>

<a href="{{ route('budgets.edit', $model) }}"
   class="btn btn-warning btn-sm">
  <i class="fa fa-edit"></i> Edit
</a>

<a href="{{ route('budgets.destroy', $model) }}" class="btn btn-danger btn-sm"
   onclick="event.preventDefault(); if(confirm('Yakin menghapus anggaran ini? Aktivitas anggaran juga akan ikut terhapus.')) document.getElementById('delete-budget-{{ $model->id }}').submit();">
  <i class="fa fa-trash"></i> Hapus
</a>
<form id="delete-budget-{{ $model->id }}" action="{{ route('budgets.destroy', $model) }}" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>
