@push('head')
<link href="/assets/css/prism/prism-vs.css" rel="stylesheet">
<script src="/assets/vendor/prism/prism.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.11.0/highlight.min.js"></script>

<style type="text/css">
    pre {
        padding: 0;
    }
</style>
@endpush

@push('scripts')
<script type="text/javascript">
    hljs.initHighlightingOnLoad();
</script>
@endpush

@extends('layouts.app')

@section('title', 'Show campaign')

@section('content')

    <div class="c-header">
        <h2>Campaigns: {{ $campaign->name }}</h2>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2>JS snippet</h2>
            </div>
            <div class="card-body card-padding">
                <div class="row">
                    <div class="col-md-12">
                        @php
                            $libUrl = asset("assets/js/remplib.js");
                            $baseUrl = url('/');
                            $snippet = <<<HTML
<script type="text/javascript">
    (function(win, doc) {
        function e(e) {
            return function() {
                var args = arguments;
                if ("initialize" === e && args && args[0].modify && args[0].modify.overlay && "loading" === doc.readyState) {
                    var a = "__inf__overlay__";
                    doc.write('<div id="' + a + '" style="position:absolute;background:#fff;left:0;top:0;width:100%;height:100%;z-index:1010101"></div>');
                    setTimeout(function() {
                        var e = doc.getElementById(a);
                        e && doc.body.removeChild(e);
                    }, args[0].modify.delay || 500)
                }
                this._.push([e, args])
            }
        }
        if (!win.remplib) {
            var fn, i, funcs = "init identify".split(" "),
                script = doc.createElement("script"),
                d = "https:" === doc.location.protocol ? "https:" : "http:";
            win.remplib = {_: []};

            for (i = 0; i < funcs.length; i++) {
                fn = funcs[i];
                win.remplib[fn] = e(fn);
            }

            script.type = "text/javascript";
            script.async = true;
            script.src = d + "//rempcampaign.local/assets/js/remplib.js";
            doc.getElementsByTagName("head")[0].appendChild(script);
        }
    })(window, document);

    remplib.init({
        "token": "beam-property-token"
    });
    remplib.identify("user-identifier"); // optional
</script>
HTML;
                        @endphp
                        <pre><code class="html">{{ $snippet }}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h2>Show campaign <small>{{ $campaign->name }}</small></h2>
            </div>
            <div class="card-body card-padding">
                <div class="row m-t-10 cp-container">
                    <div class="col-md-4"><strong>Banner</strong></div>
                    <div class="col-md-8">{{ $campaign->banner->name }}</div>
                </div>
                <div class="row m-t-10 cp-container">
                    <div class="col-md-4"><strong>Segment</strong></div>
                    <div class="col-md-8">{{ $campaign->segment_id }}</div>
                </div>
                <div class="row m-t-10 cp-container">
                    <div class="col-md-4"><strong>Active</strong></div>
                    <div class="col-md-8">{{ @yesno($campaign->active) }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
