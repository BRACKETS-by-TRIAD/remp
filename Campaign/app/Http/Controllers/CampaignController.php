<?php

namespace App\Http\Controllers;

use App\Banner;
use App\Campaign;
use App\CampaignSegment;
use App\Contracts\SegmentAggregator;
use App\Contracts\SegmentException;
use App\Http\Requests\CampaignRequest;
use App\Http\Resources\CampaignResource;
use Cache;
use HTML;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\Resource;
use Illuminate\Support\Collection;
use View;
use Yajra\Datatables\Datatables;
use App\Models\Dimension\Map as DimensionMap;
use App\Models\Position\Map as PositionMap;
use App\Models\Alignment\Map as AlignmentMap;

class CampaignController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->format([
            'html' => view('campaigns.index'),
            'json' => CampaignResource::collection(Campaign::paginate()),
        ]);
    }

    public function json(Datatables $dataTables)
    {
        $campaigns = Campaign::select()
            ->with(['banner', 'altBanner', 'segments'])
            ->get();

        return $dataTables->of($campaigns)
            ->addColumn('actions', function (Campaign $campaign) {
                return [
                    'edit' => route('campaigns.edit', $campaign),
                ];
            })
            ->addColumn('name', function (Campaign $campaign) {
                return Html::linkRoute('campaigns.edit', $campaign->name, $campaign);
            })
            ->addColumn('banner', function (Campaign $campaign) {
                return Html::linkRoute('banners.edit', $campaign->banner->name, $campaign->banner);
            })
            ->addColumn('alt_banner', function (Campaign $campaign) {
                if (!$campaign->altBanner) {
                    return null;
                }
                return Html::linkRoute('banners.edit', $campaign->altBanner->name, $campaign->altBanner);
            })
            ->rawColumns(['actions', 'active', 'signed_in', 'once_per_session'])
            ->setRowId('id')
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param SegmentAggregator $segmentAggregator
     * @return \Illuminate\Http\Response
     */
    public function create(SegmentAggregator $segmentAggregator)
    {
        $campaign = new Campaign();
        $campaign->fill(old());
        $selectedSegments = collect(old('segments'));

        $banners = Banner::all();
        try {
            $segments = $segmentAggregator->list();
        } catch (SegmentException $e) {
            $segments = new Collection();
            flash('Unable to fetch list of segments, please check the application configuration.')->error();
            \Log::error($e->getMessage());
        }

        return view('campaigns.create', [
            'campaign' => $campaign,
            'banners' => $banners,
            'segments' => $segments,
            'selectedSegments' => $selectedSegments,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CampaignRequest|Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(CampaignRequest $request)
    {
        $campaign = new Campaign();
        $campaign->fill($request->all());
        $campaign->save();
        $campaign->banner_id = $request->get('banner_id');
        $campaign->alt_banner_id = $request->get('alt_banner_id');

        foreach ($request->get('segments', []) as $r) {
            /** @var CampaignSegment $campaignSegment */
            $campaignSegment = new CampaignSegment();
            $campaignSegment->code = $r['code'];
            $campaignSegment->provider = $r['provider'];
            $campaignSegment->campaign_id = $campaign->id;
            $campaignSegment->save();
        }

        return response()->format([
            'html' => redirect(route('campaigns.index'))->with('success', 'Campaign created'),
            'json' => new CampaignResource($campaign),
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Campaign  $campaign
     * @return \Illuminate\Http\Response
     */
    public function show(Campaign $campaign)
    {
        return response()->format([
            'html' => view('campaigns.show', [
                'campaign' => $campaign,
            ]),
            'json' => new CampaignResource($campaign),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Campaign $campaign
     * @param SegmentAggregator $segmentAggregator
     * @return \Illuminate\Http\Response
     */
    public function edit(Campaign $campaign, SegmentAggregator $segmentAggregator)
    {
        $campaign->fill(old());
        $banners = Banner::all();

        try {
            $segments = $segmentAggregator->list();
        } catch (SegmentException $e) {
            $segments = new Collection();
            flash('Unable to fetch list of segments, please check the application configuration.')->error();
            \Log::error($e->getMessage());
        }

        return view('campaigns.edit', [
            'campaign' => $campaign,
            'banners' => $banners,
            'segments' => $segments,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param CampaignRequest|Request $request
     * @param  \App\Campaign $campaign
     * @return \Illuminate\Http\Response
     */
    public function update(CampaignRequest $request, Campaign $campaign)
    {
        $campaign->fill($request->all());
        $campaign->save();
        $campaign->banner_id = $request->get('banner_id');
        $campaign->alt_banner_id = $request->get('alt_banner_id');

        foreach ($request->get('segments', []) as $r) {
            /** @var CampaignSegment $campaignSegment */
            $campaignSegment = CampaignSegment::findOrNew($r['id']);
            $campaignSegment->code = $r['code'];
            $campaignSegment->provider = $r['provider'];
            $campaignSegment->campaign_id = $campaign->id;
            $campaignSegment->save();
        }

        CampaignSegment::destroy($request->get('removedSegments'));

        return response()->format([
            'html' => redirect(route('campaigns.index'))->with('success', 'Campaign updated'),
            'json' => new CampaignResource($campaign),
        ]);
    }

    /**
     * @param Request $r
     * @param DimensionMap $dm
     * @param PositionMap $pm
     * @param AlignmentMap $am
     * @param SegmentAggregator $sa
     * @return \Illuminate\Http\JsonResponse
     */
    public function showtime(
        Request $r,
        DimensionMap $dm,
        PositionMap $pm,
        AlignmentMap $am,
        SegmentAggregator $sa
    ) {
        // validation

        $data = \GuzzleHttp\json_decode($r->get('data'));
        $url = $data->url ?? null;
        if (!$url) {
            return response()
                ->jsonp($r->get('callback'), [
                    'success' => false,
                    'errors' => ['url is required and missing'],
                ]);
        }

        $userId = null;
        if (isset($data->userId) || !empty($data->userId)) {
            $userId = $data->userId;
        }

        $browserId = null;
        if (isset($data->browserId) || !empty($data->browserId)) {
            $browserId = $data->browserId;
        }
        if (!$browserId) {
            return response()
                ->jsonp($r->get('callback'), [
                    'success' => false,
                    'errors' => ['browserId is required and missing'],
                ])
                ->setStatusCode(400);
        }

        if (isset($data->cache)) {
            $sa->setCache($data->cache);
        }

        $campaignIds = Cache::get(Campaign::ACTIVE_CAMPAIGN_IDS, []);
        if (count($campaignIds) == 0) {
            return response()
                ->jsonp($r->get('callback'), [
                    'success' => true,
                    'data' => [],
                    'providerData' => $sa->getProviderData(),
                ]);
        }

        /** @var Campaign $campaign */
        $positions = $pm->positions();
        $dimensions = $dm->dimensions();
        $alignments = $am->alignments();
        $displayedCampaigns = [];

        foreach ($campaignIds as $campaignId) {
            $campaign = Cache::tags(Campaign::CAMPAIGN_TAG)->get($campaignId);
            $running = false;
            foreach ($campaign->schedules as $schedule) {
                if ($schedule->isRunning()) {
                    $running = true;
                    break;
                }
            }
            if (!$running) {
                continue;
            }

            // banner
            $bannerVariantA = $campaign->banner ?? false;
            if (!$bannerVariantA) {
                return response()
                    ->jsonp($r->get('callback'), [
                        'success' => false,
                        'errors' => ["active campaign [{$campaign->uuid}] has no banner set"],
                    ]);
            }

            $banner = null;
            $bannerVariantB = $campaign->altBanner ?? false;
            if (!$bannerVariantB) {
                // only one variant of banner, so set it
                $banner = $bannerVariantA;
            } else {
                // there are two variants
                // find banner previously displayed to user
                $bannerId = null;
                $campaignsBanners = $data->campaignsBanners ?? false;
                if ($campaignsBanners && isset($campaignsBanners->{$campaign->uuid})) {
                    $bannerId = $campaignsBanners->{$campaign->uuid}->bannerId ?? null;
                }

                if ($bannerId !== null) {
                    // check if displayed banner is one of existing variants
                    switch ($bannerId) {
                        case $bannerVariantA->uuid:
                            $banner = $bannerVariantA;
                            break;
                        case $bannerVariantB->uuid:
                            $banner = $bannerVariantB;
                            break;
                    }
                }

                // banner still not set, choose random variant
                if ($banner === null) {
                    $banner = rand(0, 1) ? $bannerVariantA : $bannerVariantB;
                }
            }

            // check if campaign is set to be seen only once per session
            // and check campaign UUID against list of campaigns seen by user
            $campaignsSeen = $data->campaignsSeen ?? false;
            if ($campaign->once_per_session && $campaignsSeen) {
                $seen = false;
                foreach ($campaignsSeen as $campaignSeen) {
                    if ($campaignSeen->campaignId === $campaign->uuid) {
                        $seen = true;
                        break;
                    }
                }
                if ($seen) {
                    continue;
                }
            }

            // signed in state
            if (isset($campaign->signed_in) && $campaign->signed_in !== boolval($userId)) {
                continue;
            }

            // segment
            foreach ($campaign->segments as $campaignSegment) {
                $campaignSegment->setRelation('campaign', $campaign); // setting this manually to avoid DB query

                if ($userId) {
                    if (!$sa->checkUser($campaignSegment, strval($userId))) {
                        continue 2;
                    }
                } else {
                    if (!$sa->checkBrowser($campaignSegment, strval($browserId))) {
                        continue 2;
                    }
                }
            }

            //render
            $displayedCampaigns[] = View::make('banners.preview', [
                'banner' => $banner,
                'campaign' => $campaign,
                'positions' => $positions,
                'dimensions' => $dimensions,
                'alignments' => $alignments,
            ])->render();
        }

        if (empty($displayedCampaigns)) {
            return response()
                ->jsonp($r->get('callback'), [
                    'success' => true,
                    'data' => [],
                    'providerData' => $sa->getProviderData(),
                ]);
        }

        return response()
            ->jsonp($r->get('callback'), [
                'success' => true,
                'errors' => [],
                'data' => $displayedCampaigns,
                'providerData' => $sa->getProviderData(),
            ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Campaign  $campaign
     * @return \Illuminate\Http\Response
     */
    public function destroy(Campaign $campaign)
    {
        //
    }
}
