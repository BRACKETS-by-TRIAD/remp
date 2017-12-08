@extends('layouts.app')

@section('title', 'API Tokens')

@section('content')

    <div class="c-header">
        <h2>API Tokens</h2>
    </div>
    <div class="card">
        <div class="card-header">
            <h2>List of API Tokens <small></small></h2>
            <div class="actions">
                <a href="{{ route('api-tokens.create') }}" class="btn palette-Cyan bg waves-effect">Add new API Token</a>
            </div>
        </div>

        {!! Widget::run('DataTable', [
            'colSettings' => [
                'name',
                'token',
                'active' => [
                    'header' => 'Is active',
                    'render' => 'boolean',
                ],
                'created_at' => [
                    'header' => 'Created at',
                    'render' => 'date',
                ],
                'updated_at' => [
                    'header' => 'Updated at',
                    'render' => 'date',
                ],
            ],
            'dataSource' => route('api-tokens.json'),
            'rowActions' => [
                ['name' => 'edit', 'class' => 'zmdi-palette-Cyan zmdi-edit'],
                ['name' => 'destroy', 'class' => 'zmdi-palette-Cyan zmdi-delete'],
            ],
            'rowActionLink' => 'show',
            'order' => [4, 'desc'],
        ]) !!}
    </div>
@endsection