<a href="{{ route('operational.edit', $model->id) }}" class="btn btn-warning btn-sm" style="margin-bottom: 3px;">
    <i class="fa fa-edit"></i> Edit
</a>

<a href="#" class="btn btn-danger btn-sm btn-delete-op"
   data-form="delete-op-{{ $model->id }}"
   data-name="{{ $model->name }}"
   style="margin-bottom: 3px;">
    <i class="fa fa-trash"></i> Hapus
</a>

<form id="delete-op-{{ $model->id }}" action="{{ route('operational.destroy', $model->id) }}" method="POST" style="display:none;">
    @csrf
    @method('DELETE')
</form>

<script>
(function() {
    document.querySelectorAll('.btn-delete-op').forEach(function(btn) {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var formId = this.getAttribute('data-form');
            var name = this.getAttribute('data-name') || 'biaya ini';
            swal({
                title: 'Yakin hapus biaya ini?',
                text: '"' + name + '" akan dihapus dan jumlahnya dikembalikan ke sisa anggaran.',
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
