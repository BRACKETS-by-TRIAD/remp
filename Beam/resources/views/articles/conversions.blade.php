@extends('layouts.app')

@section('title', 'Articles - Conversion stats')

@section('content')

    <div class="c-header">
        <h2>Articles - Conversion stats</h2>
    </div>
    <div class="card">
        <div class="card-header">
            <h2>Conversion stats <small></small></h2>
        </div>

        {!! Widget::run('DataTable', [
            'colSettings' => [
                'title',
                'conversions_count' => ['header' => 'conversions'],
                'amount' => ['header' => 'amount'],
                'authors[, ].name' => ['header' => 'authors', 'orderable' => false, 'filter' => $authors],
                'sections[, ].name' => ['header' => 'sections', 'orderable' => false, 'filter' => $sections],
                'published_at' => ['header' => 'published at', 'render' => 'date'],
            ],
            'dataSource' => route('articles.dtConversions'),
            'order' => [5, 'desc'],
        ]) !!}

    </div>

@endsection
