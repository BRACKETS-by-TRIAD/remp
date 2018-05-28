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
            <h2>List of segments <small></small></h2>
            <div class="actions">
                <a href="{{ route('segments.create') }}" class="btn palette-Cyan bg waves-effect">Add new segment</a>
            </div>
        </div>

        {!! Widget::run('DataTable', [
            'colSettings' => [
                'name' => [
                    'priority' => 1,
                ],
                'code' => [
                    'priority' => 2,
                ],
                'active' => [
                    'render' => 'boolean',
                    'header' => 'Is active',
                    'priority' => 2,
                ],
                'created_at' => [
                    'render' => 'date',
                    'header' => 'Created at',
                    'priority' => 3,
                ],
                'updated_at' => [
                    'render' => 'date',
                    'header' => 'Updated at',
                    'priority' => 1,
                ],
            ],
            'dataSource' => route('segments.json'),
            'rowActions' => [
                ['name' => 'edit', 'class' => 'zmdi-palette-Cyan zmdi-edit'],
                ['name' => 'copy', 'class' => 'zmdi-palette-Cyan zmdi-copy'],
            ],
            'rowActionLink' => 'edit',
            'order' => [4, 'desc'],
        ]) !!}
    </div>

@endsection
