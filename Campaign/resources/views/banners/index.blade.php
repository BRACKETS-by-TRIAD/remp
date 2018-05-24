@extends('layouts.app')

@section('title', 'Banners')

@section('content')

    <div class="c-header">
        <h2>Banners</h2>
    </div>
    <div class="card">
        <div class="card-header">
            <h2>List of banners <small></small></h2>
            <div class="actions">
                <a href="#modal-template-select" data-toggle="modal" class="btn palette-Cyan bg waves-effect">Add new banner</a>
            </div>
        </div>

        {!! Widget::run('DataTable', [
            'colSettings' => [
                'name' => [
                    'priority' => 10,
                ],
                'template' => [
                    'priority' => 10,
                ],
                'display_type' => [
                    'priority' => 10,
                ],
                'position' => [
                    'priority' => 10,
                ],
                'created_at' => [
                    'header' => 'Created at',
                    'render' => 'date',
                    'priority' => 10,
                ],
                'updated_at' => [
                    'header' => 'Updated at',
                    'render' => 'date',
                    'priority' => 10,
                ],
            ],
            'dataSource' => route('banners.json'),
            'rowActions' => [
                ['name' => 'show', 'class' => 'zmdi-palette-Cyan zmdi-eye'],
                ['name' => 'edit', 'class' => 'zmdi-palette-Cyan zmdi-edit'],
                ['name' => 'copy', 'class' => 'zmdi-palette-Cyan zmdi-copy'],
            ],
            'rowHighlights' => [
                'active' => true
            ],
            'order' => [5, 'desc'],
        ]) !!}
    </div>

    @include('banners._template_modal')
@endsection
