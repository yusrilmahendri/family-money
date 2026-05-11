@extends('welcome')

@section('content')
<div class="main" style="margin-top: 40px;">
    <div class="clearfix" style="margin-bottom: 15px;">
        <h3 class="pull-left">Tabungan &amp; goals</h3>
        <a href="{{ route('savings-goals.create') }}" class="btn btn-primary pull-right"><em class="fa fa-plus"></em> Goal baru</a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Goal</th>
                    <th>Target</th>
                    <th>Terkumpul</th>
                    <th>Deadline</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($goals as $g)
                    @php
                        $saved = $g->savedTotal();
                        $target = (float) $g->target_amount;
                        $pct = $target > 0 ? min(100, round(($saved / $target) * 100)) : 0;
                    @endphp
                    <tr>
                        <td>{{ $g->title }}</td>
                        <td>Rp {{ number_format($target, 0, ',', '.') }}</td>
                        <td>
                            Rp {{ number_format($saved, 0, ',', '.') }}
                            <div class="progress" style="height:6px;margin-top:4px;">
                                <div class="progress-bar progress-bar-success" style="width: {{ $pct }}%;"></div>
                            </div>
                        </td>
                        <td>{{ $g->deadline ? $g->deadline->format('d M Y') : '—' }}</td>
                        <td>
                            <a href="{{ route('savings-goals.show', $g) }}" class="btn btn-info btn-xs">Detail / nabung</a>
                            <a href="{{ route('savings-goals.edit', $g) }}" class="btn btn-warning btn-xs">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted">Belum ada goal.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
