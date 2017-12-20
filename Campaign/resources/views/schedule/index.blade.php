@extends('layouts.app')

@section('title', 'Scheduler')

@section('content')

    <div class="c-header">
        <h2>Scheduler</h2>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h2>Schedule <small></small></h2>
                    <div class="actions">
                        <a href="{{ route('schedule.create') }}" class="btn palette-Cyan bg waves-effect">Schedule new run</a>
                    </div>
                </div>

                <div class="card-body">
                    {!! Widget::run('DataTable', [
                    'colSettings' => [
                        'campaign' => [
                            'header' => 'Campaign',
                        ],
                        'banners' => [
                            'header' => 'Banners',
                        ],
                        'start_time' => [
                            'header' => 'Scheduled start date',
                            'render' => 'date',
                        ],
                        'end_time' => [
                            'header' => 'Scheduled end date',
                            'render' => 'date',
                        ],
                        'status' => [
                            'header' => 'Status',
                        ],
                        'updated_at' => [
                            'header' => 'Status',
                        ],
                    ],
                    'dataSource' => route('schedule.json'),
                    'rowActions' => [
                        ['name' => 'edit', 'class' => 'zmdi-palette-Cyan zmdi-edit'],
                        ['name' => 'start', 'class' => 'zmdi-palette-Cyan zmdi-play'],
                        ['name' => 'pause', 'class' => 'zmdi-palette-Cyan zmdi-pause'],
                        ['name' => 'stop', 'class' => 'zmdi-palette-Cyan zmdi-stop'],
                        ['name' => 'destroy', 'class' => 'zmdi-palette-Cyan zmdi-delete'],
                    ],
                ]) !!}
                </div>
            </div>
        </div>
    </div>


@endsection
