<a href="{{ route('budgets.edit', $model) }}" 
   class="btn btn-warning">
  <img class="img-fluid" src="{{ asset('images/edit.png') }}" alt="">
  Edit
</a>

<a href="{{ route('budgets.destroy', $model) }}" class="btn btn-danger btn-sm" id="delete"
   onclick="event.preventDefault(); document.getElementById('delete-budget-{{ $model->id }}').submit();">
  Hapus
</a>
<form id="delete-budget-{{ $model->id }}" action="{{ route('budgets.destroy', $model) }}" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>