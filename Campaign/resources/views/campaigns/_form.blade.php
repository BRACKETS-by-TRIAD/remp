@php

/* @var $campaign \App\Campaign */
/* @var $banners \Illuminate\Support\Collection */
/* @var $segments \Illuminate\Support\Collection */

$banners = $banners->map(function(\App\Banner $banner) {
   return ['id' => $banner->id, 'name' => $banner->name];
});

$segments = $segments->mapToGroups(function ($item) {
    return [$item->group->name => [$item->code => $item]];
})->mapWithKeys(function($item, $key) {
    return [$key => $item->collapse()];
});

$segmentMap = $segments->flatten()->mapWithKeys(function ($item) {
    return [$item->code => $item->name];
})

@endphp

<div id="campaign-form">
    <campaign-form></campaign-form>
</div>

@push('scripts')

<script type="text/javascript">
    var campaign = {
        "name": '{!! $campaign->name !!}' || null,
        "segments": {!! isset($selectedSegments) ? $selectedSegments->toJson(JSON_UNESCAPED_UNICODE) : $campaign->segments->toJson(JSON_UNESCAPED_UNICODE) !!},
        "bannerId": {!! @json($campaign->banner_id) !!} || null,
        "signedIn": {!! @json($campaign->signed_in) !!},
        "active": {!! @json($campaign->active) !!},

        "banners": {!! $banners->toJson(JSON_UNESCAPED_UNICODE) !!},
        "availableSegments": {!! $segments->toJson(JSON_UNESCAPED_UNICODE) !!},
        "addedSegment": null,
        "removedSegments": [],
        "segmentMap": {!! $segmentMap->toJson(JSON_UNESCAPED_UNICODE) !!},
        "eventTypes": [
            {
                "category": "banner",
                "action": "show",
                "value": "banner|show",
                "label": "banner / show"
            },
            {
                "category": "banner",
                "action": "click",
                "value": "banner|click",
                "label": "banner / click"
            }
        ]
    };
    remplib.campaignForm.bind("#campaign-form", campaign);
</script>

@endpush