@extends('layouts.app')

@section('title', 'Segments')

@section('sidebar')
    @parent

    <p>This is appended to the master sidebar.</p>
@endsection

@section('content')

    <div class="c-header">
        <h2>Segments</h2>
    </div>
    <div class="card">
        <div class="card-header">
            <h2>Lorem ipsum <small>Lorem ipsum dolor sit amet, consectetur adipiscing elit</small></h2>
            <div class="actions">
                <a href="{{ route('segments.create') }}" class="btn palette-Cyan bg waves-effect">Add new segment</a>
            </div>
        </div>

        {!! Widget::run('DataTable', [
            'colSettings' => ['name', 'code', 'active'],
            'dataSource' => route('segments.json'),
            'rowActions' => [
                // ['name' => 'show', 'class' => 'zmdi-palette-Cyan zmdi-eye'],
                ['name' => 'edit', 'class' => 'zmdi-palette-Cyan zmdi-edit'],
            ],
        ]) !!}
    </div>

@endsection