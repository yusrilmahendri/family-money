<a href="{{ route('budgets.show', $model) }}" class="btn btn-info btn-sm" style="margin-bottom: 3px;">
  <i class="fa fa-eye"></i> Detail
</a>

<a href="{{ route('budgets.edit', $model) }}" class="btn btn-warning btn-sm" style="margin-bottom: 3px;">
  <i class="fa fa-edit"></i> Edit
</a>

<a href="#" class="btn btn-danger btn-sm btn-delete-budget"
   data-form="delete-budget-{{ $model->id }}"
   style="margin-bottom: 3px;">
  <i class="fa fa-trash"></i> Hapus
</a>
<form id="delete-budget-{{ $model->id }}" action="{{ route('budgets.destroy', $model) }}" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>

<script>
(function() {
    document.querySelectorAll('.btn-delete-budget').forEach(function(btn) {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var formId = this.getAttribute('data-form');
            swal({
                title: 'Yakin hapus anggaran ini?',
                text: 'Semua aktivitas anggaran di dalamnya akan ikut terhapus dan saldo dikembalikan ke saldo bebas.',
                icon: 'warning',
                buttons: {
                    cancel: { text: 'Batal', value: null, visible: true },
                    confirm: { text: 'Ya, Hapus', value: true, className: 'btn-danger' }
                },
                dangerMode: true,
            }).then(function(ok) {
                if (ok) {
                    document.getElementById(formId).submit();
                }
            });
        });
    });
})();
</script>
