@extends('layouts.app')

@section('title', 'Edit segment')

@section('content')

    <div class="c-header">
        <h2>Segments</h2>
    </div>
    <div class="card">
        <div class="card-header">
            <h2>Edit segment / <small>{{ $segment->name }}</small></h2>
        </div>
        <div class="card-body card-padding">
            @component('segments._vue_form')
            @endcomponent

            {!! Form::model($segment, ['route' => ['segments.update', $segment], 'method' => 'PATCH', 'id' => 'segment-form']) !!}
            @include('segments._form')
            {!! Form::close() !!}
        </div>
    </div>

@endsection