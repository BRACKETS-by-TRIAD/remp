@extends('layouts.app')

@section('title', 'Authors')

@section('content')

    <div class="c-header">
        <h2>Authors</h2>
    </div>

    <div class="well">
        <div class="row">
            <div class="col-md-6">
                <h4>Filter by publish date</h4>
                <div id="smart-range-selector">
                    {!! Form::hidden('published_from', $publishedFrom) !!}
                    {!! Form::hidden('published_to', $publishedTo) !!}
                    <smart-range-selector from="{{$publishedFrom}}" to="{{$publishedTo}}" :callback="callback">
                    </smart-range-selector>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Author stats <small></small></h2>
        </div>

        {!! Widget::run('DataTable', [
            'colSettings' => [
                'name' => [
                    'header' => 'author',
                    'orderable' => false,
                    'filter' => $authors,
                    'priority' => 1,
                ],
                'articles_count' => [
                    'header' => 'articles',
                    'priority' => 3,
                ],
                'conversions_count' => [
                    'header' => 'conversions',
                    'priority' => 2,
                ],
                'conversions_amount' => [
                    'header' => 'amount',
                    'render' => 'array',
                    'priority' => 2,
                ],
                'pageviews_all' => [
                    'header' => 'all pageviews',
                    'render' => 'number',
                    'priority' => 2,
                ],
                'pageviews_signed_in' => [
                    'header' => 'signed in pageviews',
                    'render' => 'number',
                    'priority' => 5,
                ],
                'pageviews_subscribers' => [
                    'header' => 'subscriber pageviews',
                    'render' => 'number',
                    'priority' => 5,
                ],
                'avg_timespent_all' => [
                    'header' => 'avg time all',
                    'render' => 'duration',
                    'priority' => 2,
                ],
                'avg_timespent_signed_in' => [
                    'header' => 'avg time signed in',
                    'render' => 'duration',
                    'priority' => 5,
                ],
                'avg_timespent_subscribers' => [
                    'header' => 'avg time subscribers',
                    'render' => 'duration',
                    'priority' => 5,
                ],
            ],
            'dataSource' => route('authors.dtAuthors'),
            'order' => [3, 'desc'],
            'requestParams' => [
                'published_from' => '$(\'[name="published_from"]\').val()',
                'published_to' => '$(\'[name="published_to"]\').val()',
                'tz' => 'Intl.DateTimeFormat().resolvedOptions().timeZone'
            ],
            'refreshTriggers' => [
                [
                    'event' => 'change',
                    'selector' => '[name="published_from"]'
                ],
                [
                    'event' => 'change',
                    'selector' => '[name="published_to"]',
                ],
            ],
        ]) !!}
    </div>

    <script type="text/javascript">
        new Vue({
            el: "#smart-range-selector",
            components: {
                SmartRangeSelector
            },
            methods: {
                callback: function (from, to) {
                    $('[name="published_from"]').val(from);
                    $('[name="published_to"]').val(to).trigger("change");
                }
            }
        });
    </script>

@endsection
