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
                        <a href="{{ route('campaigns.index') }}" class="btn palette-Cyan bg waves-effect"><i class="zmdi zmdi-long-arrow-return"></i> Back to campaigns</a>
                    </div>
                </div>

                <div class="card-body">
                    {!! Widget::run('DataTable', [
                    'colSettings' => [
                        'campaign' => [
                            'header' => 'Campaign',
                            'priority' => 1,
                        ],
                        'variants' => [
                            'header' => 'Variants',
                            'orderable' => false,
                            'priority' => 3,
                        ],
                        'start_time' => [
                            'header' => 'Scheduled start date',
                            'render' => 'date',
                            'priority' => 2,
                        ],
                        'end_time' => [
                            'header' => 'Scheduled end date',
                            'render' => 'date',
                            'priority' => 1,
                        ],
                        'status' => [
                            'header' => 'Status',
                            'priority' => 1,
                        ],
                        'updated_at' => [
                            'header' => 'Updated at',
                            'priority' => 3,
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
