@extends('entity.layout')
@section('content')
    <h3>Edit Goal</h3>
    <form method="POST" action="{{ route('entity.savings-goals.update', [$entity, $goal]) }}">
        @csrf @method('PUT')
        <div class="form-group"><label>Judul</label><input class="form-control" name="title" value="{{ $goal->title }}" required></div>
        <div class="form-group"><label>Target</label><input class="form-control" name="target_amount" value="{{ $goal->target_amount }}" required></div>
        <div class="form-group"><label>Deadline</label><input type="date" class="form-control" name="deadline" value="{{ $goal->deadline?->toDateString() }}"></div>
        <button class="btn btn-primary">Perbarui</button>
    </form>
@endsection
