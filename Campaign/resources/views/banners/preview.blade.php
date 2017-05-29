{{--<script type="text/javascript">--}}

var bannerId = 'b-{{ $banner->uuid }}';
var scripts = [];
if (typeof window.Vue === 'undefined') {
    scripts.push('https://cdnjs.cloudflare.com/ajax/libs/vue/2.3.2/vue.js');
}
if (typeof window.Campaign.banner === 'undefined') {
    scripts.push('{{ asset('/assets/js/banner.js') }}');
}

var styles = [
    '{{ asset('assets/css/banner.css') }}'
];

var waiting = scripts.length + styles.length;
var run = function() {
    if (waiting) {
        return;
    }
    var alignments = JSON.parse('{!! json_encode($alignments) !!}');
    var dimensions = JSON.parse('{!! json_encode($dimensions) !!}');
    var positions = JSON.parse('{!! json_encode($positions) !!}');
    var banner = Campaign.banner.fromModel({!! $banner->toJson() !!});

    banner.show = false;
    banner.alignmentOptions = alignments;
    banner.dimensionOptions = dimensions;
    banner.positionOptions = positions;

    var d = document.createElement('div');
    d.id = bannerId;
    var bp = document.createElement('banner-preview');
    d.appendChild(bp);
    var b = document.getElementsByTagName('body')[0];
    b.appendChild(d);

    Campaign.banner.bindPreview(banner, {
        zIndex: 99, //TODO: remove when REMP template is fixed,
        position: 'fixed'
    });
    new Vue({
        el: '#' + bannerId
    });
    setTimeout(function() {
        banner.show = true;
        if (banner.closeTimeout) {
            setTimeout(function() {
                banner.show = false;
            }, banner.closeTimeout);
        }
    }, banner.displayDelay);
};

for (var i=0; i<scripts.length; i++) {
    Campaign.lib.loadScript(scripts[i], function() {
        waiting -= 1;
        run();
    });
}
for (i=0; i<styles.length; i++) {
    Campaign.lib.loadStyle(styles[i], function() {
        waiting -= 1;
        run();
    });
}

{{--</script>--}}