<a href="{{ route('incomes.edit', $model->id) }}" class="btn btn-warning btn-sm" style="margin-bottom: 3px;">
  <i class="fa fa-edit"></i> Edit
</a>

<a href="#" class="btn btn-danger btn-sm btn-delete-income"
   data-form="delete-income-{{ $model->id }}"
   style="margin-bottom: 3px;">
  <i class="fa fa-trash"></i> Hapus
</a>
<form id="delete-income-{{ $model->id }}" action="{{ route('incomes.destroy', $model->id) }}" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>

<script>
(function() {
    document.querySelectorAll('.btn-delete-income').forEach(function(btn) {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var formId = this.getAttribute('data-form');
            swal({
                title: 'Hapus pemasukan ini?',
                text: 'Saldo dan laporan akan menyesuaikan.',
                icon: 'warning',
                buttons: {
                    cancel: { text: 'Batal', value: null, visible: true },
                    confirm: { text: 'Ya, Hapus', value: true, className: 'btn-danger' }
                },
                dangerMode: true,
            }).then(function(ok) {
                if (ok) document.getElementById(formId).submit();
            });
        });
    });
})();
</script>
