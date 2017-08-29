@extends('layouts.app')

@section('title', 'Edit campaign')

@section('content')

    <div class="c-header">
        <h2>Campaigns: {{ $campaign->name }}</h2>
    </div>
    <div class="card">
        <div class="card-header">
            <h2>Edit campaign <small>{{ $campaign->name }}</small></h2>
        </div>

        <div class="card-body card-padding">
            @include('flash::message')

            {!! Form::model($campaign, ['route' => ['campaigns.update', $campaign], 'method' => 'PATCH']) !!}
            @include('campaigns._form')
            {!! Form::close() !!}
        </div>
    </div>

@endsection