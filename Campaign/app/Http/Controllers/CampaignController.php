<?php

namespace App\Http\Controllers;

use App\Banner;
use App\Campaign;
use App\CampaignBanner;
use App\CampaignSegment;
use App\Contracts\SegmentAggregator;
use App\Contracts\SegmentException;
use App\Country;
use App\Http\Request;
use App\Http\Requests\CampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Schedule;
use Cache;
use Carbon\Carbon;
use GeoIp2;
use HTML;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use View;
use Yajra\Datatables\Datatables;
use App\Models\Dimension\Map as DimensionMap;
use App\Models\Position\Map as PositionMap;
use App\Models\Alignment\Map as AlignmentMap;
use DeviceDetector\DeviceDetector;
use App\Contracts\Remp\Stats;

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
            ->with(['segments', 'countries', 'campaignBanners'])
            ->get();

        return $dataTables->of($campaigns)
            ->addColumn('actions', function (Campaign $campaign) {
                return [
                    'edit' => route('campaigns.edit', $campaign),
                    'copy' => route('campaigns.copy', $campaign),
                    'stats' => route('campaigns.stats', $campaign),
                ];
            })
            ->addColumn('name', function (Campaign $campaign) {
                return Html::linkRoute('campaigns.edit', $campaign->name, $campaign);
            })
            ->addColumn('variants', function (Campaign $campaign) {
                $data = $campaign->campaignBanners->all();
                $variants = [];

                foreach ($data as $variant) {
                    $proportion = $variant['proportion'];

                    if ($variant['control_group'] == 0) {
                        // handle variants with banner
                        $link = link_to(
                            route('banners.edit', $variant['banner_id']),
                            $variant['variant']
                        );

                        $variants[] = "{$link} ({$proportion}%)";
                    } else {
                        // handle control group
                        $variants[] = "{$variant['variant']} ({$proportion}%)";
                    }
                }

                return implode(', ', $variants);
            })
            ->addColumn('segments', function (Campaign $campaign) {
                return implode(' ', $campaign->segments->pluck('code')->toArray());
            })
            ->addColumn('countries', function (Campaign $campaign) {
                return implode(' ', $campaign->countries->pluck('name')->toArray());
            })
            ->addColumn('active', function (Campaign $campaign) {
                return view('campaigns.partials.activeToggle', [
                    'id' => $campaign->id,
                    'active' => $campaign->active
                ])->render();
            })
            ->addColumn('devices', function (Campaign $campaign) {
                return count($campaign->devices) == count($campaign->getAllDevices()) ? 'all' : implode(' ', $campaign->devices);
            })
            ->rawColumns(['actions', 'active', 'signed_in', 'once_per_session', 'variants'])
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

        list(
            $campaign,
            $bannerId,
            $variants,
            $selectedCountries,
            $countriesBlacklist
        ) = $this->processOldCampaign($campaign, old());

        return view('campaigns.create', [
            'campaign' => $campaign,
            'bannerId' => $bannerId,
            'variants' => $variants,
            'selectedCountries' => $selectedCountries,
            'countriesBlacklist' => $countriesBlacklist,
            'banners' => Banner::all(),
            'availableCountries' => Country::all(),
            'segments' => $this->getAllSegments($segmentAggregator)
        ]);
    }

    public function copy(Campaign $sourceCampaign, SegmentAggregator $segmentAggregator)
    {
        $sourceCampaign->load('banners', 'campaignBanners', 'segments', 'countries');
        $campaign = $this->replicateCampaign($sourceCampaign);

        flash(sprintf('Form has been pre-filled with data from campaign "%s"', $sourceCampaign->name))->info();

        list(
            $campaign,
            $bannerId,
            $variants,
            $selectedCountries,
            $countriesBlacklist
        ) = $this->processOldCampaign($campaign, old());

        return view('campaigns.create', [
            'campaign' => $campaign,
            'bannerId' => $bannerId,
            'variants' => $variants,
            'selectedCountries' => $selectedCountries,
            'countriesBlacklist' => $countriesBlacklist,
            'banners' => Banner::all(),
            'availableCountries' => Country::all(),
            'segments' => $this->getAllSegments($segmentAggregator)
        ]);
    }

    /**
     * Ajax validate form method.
     *
     * @param CampaignRequest|Request $request
     * @return \Illuminate\Http\Response
     */
    public function validateForm(CampaignRequest $request)
    {
        return response()->json(false);
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

        $this->saveCampaign($campaign, $request->all());

        $message = ['success' => sprintf('Campaign [%s] was created', $campaign->name)];

        // (de)activate campaign (based on flag or new schedule)
        $message['warning'] = $this->processCampaignActivation(
            $campaign,
            $request->get('activation_mode'),
            $request->get('active', false),
            $request->get('new_schedule_start_time'),
            $request->get('new_schedule_end_time')
        );

        return response()->format([
            'html' => $this->getRouteBasedOnAction(
                $request->get('action'),
                [
                    self::FORM_ACTION_SAVE_CLOSE => 'campaigns.index',
                    self::FORM_ACTION_SAVE => 'campaigns.edit',
                ],
                $campaign
            )->with($message),
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
        list(
            $campaign,
            $bannerId,
            $variants,
            $selectedCountries,
            $countriesBlacklist
        ) = $this->processOldCampaign($campaign, old());

        return view('campaigns.edit', [
            'campaign' => $campaign,
            'bannerId' => $bannerId,
            'variants' => $variants,
            'selectedCountries' => $selectedCountries,
            'countriesBlacklist' => $countriesBlacklist,
            'banners' => Banner::all(),
            'availableCountries' => Country::all(),
            'segments' => $this->getAllSegments($segmentAggregator)
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
        $this->saveCampaign($campaign, $request->all());

        $message = ['success' => sprintf('Campaign [%s] was updated.', $campaign->name)];

        // (de)activate campaign (based on flag or new schedule)
        $message['warning'] = $this->processCampaignActivation(
            $campaign,
            $request->get('activation_mode'),
            $request->get('active', false),
            $request->get('new_schedule_start_time'),
            $request->get('new_schedule_end_time')
        );

        return response()->format([
            'html' => $this->getRouteBasedOnAction(
                $request->get('action'),
                [
                        self::FORM_ACTION_SAVE_CLOSE => 'campaigns.index',
                        self::FORM_ACTION_SAVE => 'campaigns.edit',
                    ],
                $campaign
            )->with($message),
            'json' => new CampaignResource($campaign),
        ]);
    }

    /**
     * (De)Activate campaign (based on flag or new schedule).
     *
     * If `$activationMode` is 'activate-schedule' and new schedule has start time, create new schedule.
     * Otherwise activate / deactivate schedules - action based on provided `$activate` flag.
     *
     * @param $campaign
     * @param $activationMode
     * @param null $activate
     * @param null $newScheduleStartTime
     * @param null $newScheduleEndTime
     * @return null|string
     */
    private function processCampaignActivation(
        $campaign,
        $activationMode,
        $activate = null,
        $newScheduleStartTime = null,
        $newScheduleEndTime = null
    ): ?string {

        if ($activationMode === 'activate-schedule'
            && !is_null($newScheduleStartTime)) {
            $schedule = new Schedule();
            $schedule->campaign_id = $campaign->id;
            $schedule->start_time = $newScheduleStartTime;
            $schedule->end_time = $newScheduleEndTime;
            $schedule->save();
            return sprintf("Schedule with start time '%s' added", Carbon::parse($schedule->start_time)->toDayDateTimeString());
        } else {
            return $this->toggleSchedules($campaign, $activate);
        }
    }

    /**
     * Toggle campaign status - create or stop schedules.
     *
     * If campaign is not active, activate it:
     * - create new schedule with status executed (it wasn't planned).
     *
     * If campaign is active, deactivate it:
     * - stop all running or planned schedules.
     *
     * @param Campaign $campaign
     * @return JsonResponse
     */
    public function toggleActive(Campaign $campaign): JsonResponse
    {
        $activate = false;
        if (!$campaign->active) {
            $activate = true;
        }

        $this->toggleSchedules($campaign, $activate);

        // TODO: maybe add message from toggleSchedules to response?
        return response()->json([
            'active' => $campaign->active
        ]);
    }

    /**
     * Toggle schedules of campaign.
     *
     * When `activate` argument is not passed, no change is triggered.
     *
     * @param Campaign $campaign
     * @param null|boolean $activate
     * @return null|string Returns message about schedules state change.
     */
    private function toggleSchedules(Campaign $campaign, $activate = null): ?string
    {
        // do not change schedules when there is no `activate` order
        if (is_null($activate)) {
            return null;
        }

        $schedulesChangeMsg = null;
        if ($activate) {
            $activated = $this->startCampaignSchedule($campaign);
            if ($activated) {
                $schedulesChangeMsg = "Campaign was activated and is running.";
            }
        } else {
            $stopped = $this->stopCampaignSchedule($campaign);
            if ($stopped) {
                $schedulesChangeMsg = "Campaign was deactivated, all schedules were stopped.";
            }
        }

        return $schedulesChangeMsg;
    }


    /**
     * If no campaign's schedule is running, start new one.
     *
     * @param Campaign $campaign
     * @return bool Returns true if new schedule was added.
     */
    private function startCampaignSchedule(Campaign $campaign)
    {
        $activated = false;
        if (!$campaign->schedules()->running()->count()) {
            $schedule = new Schedule();
            $schedule->campaign_id = $campaign->id;
            $schedule->start_time = Carbon::now();
            $schedule->status = Schedule::STATUS_EXECUTED;
            $schedule->save();
            $activated = true;
        }

        return $activated;
    }

    /**
     * Stop all campaign schedules.
     *
     * @param Campaign $campaign
     * @return bool Returns true if any schedule was running and was stopped.
     */
    private function stopCampaignSchedule(Campaign $campaign): bool
    {
        $stopped = false;
        /** @var Schedule $schedule */
        foreach ($campaign->schedules()->runningOrPlanned()->get() as $schedule) {
            $schedule->status = Schedule::STATUS_STOPPED;
            $schedule->end_time = Carbon::now();
            $schedule->save();
            $stopped = true;
        }
        return $stopped;
    }

    /**
     * Returns countries array ready to sync with campaign_country pivot table
     *
     * @param array $countries
     * @param bool $blacklist
     * @return array
     */
    private function processCountries(array $countries, bool $blacklist): array
    {
        $processed = [];

        foreach ($countries as $cid) {
            $processed[$cid] = ['blacklisted' => $blacklist];
        }

        return $processed;
    }

    /**
     * @param Request $r
     * @param DimensionMap $dm
     * @param PositionMap $pm
     * @param AlignmentMap $am
     * @param SegmentAggregator $sa
     * @return JsonResponse
     */
    public function showtime(
        Request $r,
        DimensionMap $dm,
        PositionMap $pm,
        AlignmentMap $am,
        SegmentAggregator $sa,
        GeoIp2\Database\Reader $geoIPreader,
        DeviceDetector $dd
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

            /** @var Collection $campaignBanners */
            $campaignBanners = $campaign->campaignBanners->keyBy('uuid');

            // banner
            if ($campaignBanners->count() == 0) {
                Log::error("Active campaign [{$campaign->uuid}] has no banner set");
                continue;
            }

            $bannerUuid = null;
            $variantUuid = null;

            // find variant previously displayed to user
            $seenCampaignsBanners = $data->campaignsBanners ?? false;
            if ($seenCampaignsBanners && isset($seenCampaignsBanners->{$campaign->uuid})) {
                $bannerUuid = $seenCampaignsBanners->{$campaign->uuid}->bannerId ?? null;
                $variantUuid = $seenCampaignsBanners->{$campaign->uuid}->variantId ?? null;
            }

            // fallback for older version of campaigns local storage data
            // where decision was based on bannerUuid and not variantUuid (which was not present at all)
            if ($bannerUuid && !$variantUuid) {
                foreach ($campaignBanners as $campaignBanner) {
                    if (optional($campaignBanner->banner)->uuid === $bannerUuid) {
                        $variantUuid = $campaignBanner->uuid;
                        break;
                    }
                }
            }

            // variant still not set, choose random variant
            if ($variantUuid === null) {
                $variantsMapping = $campaign->getVariantsProportionMapping();

                $randVal = mt_rand(0, 100);
                $currPercent = 0;

                foreach ($variantsMapping as $uuid => $proportion) {
                    $currPercent = $currPercent + $proportion;
                    if ($currPercent >= $randVal) {
                        $variantUuid = $uuid;
                        break;
                    }
                }
            }

            /** @var CampaignBanner $variant */
            $variant = $campaignBanners->get($variantUuid);
            if (!$variant) {
                Log::error("Unable to get CampaignBanner [{$variantUuid}] for campaign [{$campaign->uuid}]");
                continue;
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

            // device rules
            $dd->setUserAgent($data->userAgent);
            $dd->parse();

            if (!in_array(Campaign::DEVICE_MOBILE, $campaign->devices) && $dd->isMobile()) {
                continue;
            }

            if (!in_array(Campaign::DEVICE_DESKTOP, $campaign->devices) && $dd->isDesktop()) {
                continue;
            }

            // country rules
            if (!$campaign->countries->isEmpty()) {
                // load country ISO code based on IP
                try {
                    $record = $geoIPreader->country($r->ip());
                    $countryCode = $record->country->isoCode;
                } catch (\MaxMind\Db\Reader\InvalidDatabaseException | GeoIp2\Exception\AddressNotFoundException $e) {
                    Log::error("Unable to load country for campaign [{$campaign->uuid}] with country rules: " . $e->getMessage());
                    continue;
                }
                if (is_null($countryCode)) {
                    Log::error("Unable to load country for campaign [{$campaign->uuid}] with country rules");
                    continue;
                }

                // check against white / black listed countries

                if (!$campaign->countriesBlacklist->isEmpty() && $campaign->countriesBlacklist->contains('iso_code', $countryCode)) {
                    continue;
                }
                if (!$campaign->countriesWhitelist->isEmpty() && !$campaign->countriesWhitelist->contains('iso_code', $countryCode)) {
                    continue;
                }
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

            // pageview rules
            if ($campaign->pageview_rules !== null) {
                foreach ($campaign->pageview_rules as $rule) {
                    if (!$rule['num'] || !$rule['rule']) {
                        continue;
                    }

                    switch ($rule['rule']) {
                        case Campaign::PAGEVIEW_RULE_EVERY:
                            if ($data->pageviewCount % $rule['num'] !== 0) {
                                continue 3;
                            }
                            break;
                        case Campaign::PAGEVIEW_RULE_SINCE:
                            if ($data->pageviewCount < $rule['num']) {
                                continue 3;
                            }
                            break;
                        case Campaign::PAGEVIEW_RULE_BEFORE:
                            if ($data->pageviewCount >= $rule['num']) {
                                continue 3;
                            }
                            break;
                    }
                }
            }


            $displayedCampaigns[] = View::make('banners.preview', [
                'banner' => $variant->banner,
                'variantUuid' => $variant->uuid,
                'campaign' => $campaign,
                'positions' => $positions,
                'dimensions' => $dimensions,
                'alignments' => $alignments,
                'controlGroup' => $variant->control_group
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

    public function saveCampaign(Campaign $campaign, array $data)
    {
        $campaign->fill($data);
        $campaign->save();

        if (!empty($data['variants_to_remove'])) {
            $campaign->removeVariants($data['variants_to_remove']);
        }

        $campaign->storeOrUpdateVariants($data['variants']);

        if (isset($data['countries'])) {
            $campaign->countries()->sync(
                $this->processCountries(
                    $data['countries'],
                    (bool)$data['countries_blacklist']
                )
            );
        }

        $segments = $data['segments'] ?? [];

        foreach ($segments as $r) {
            /** @var CampaignSegment $campaignSegment */
            $campaignSegment = CampaignSegment::findOrNew($r['id']);
            $campaignSegment->code = $r['code'];
            $campaignSegment->provider = $r['provider'];
            $campaignSegment->campaign_id = $campaign->id;
            $campaignSegment->save();
        }

        if (isset($data['removedSegments'])) {
            CampaignSegment::destroy($data['removedSegments']);
        }
    }

    public function replicateCampaign(Campaign $sourceCampaign)
    {
        $campaign = $sourceCampaign->replicate();
        $variants = [];

        foreach ($sourceCampaign->campaignBanners as $variant) {
            $variants[] = $variant->getClone();
        }

        $campaign->setRelation('campaignBanners', collect($variants));

        return $campaign;
    }

    public function processOldCampaign(Campaign $campaign, array $data)
    {
        $campaign->fill($data);

        // parse old segments data
        $segments = [];
        $segmentsData = isset($data['segments']) ? $data['segments'] : $campaign->segments->toArray();
        $removedSegments = isset($data['removedSegments']) ? $data['removedSegments'] : [];

        foreach ($segmentsData as $segment) {
            if (is_null($segment['id']) || !array_search($segment['id'], $removedSegments)) {
                $segments[] = $campaign->segments()->make($segment);
            }
        }
        $campaign->setRelation('segments', collect($segments));

        // parse selected countries
        $countries = $campaign->countries->toArray();
        $selectedCountries = $data['countries'] ?? array_map(function ($country) {
            return $country['iso_code'];
        }, $countries);

        // countries blacklist?
        $blacklisted = 0;
        foreach ($countries as $country) {
            $blacklisted = (int)$country['pivot']['blacklisted'];
        }

        // main banner
        if (array_key_exists('banner_id', $data)) {
            $bannerId = $data['banner_id'];
        } else if (!$campaign->campaignBanners->isEmpty()) {
            $bannerId = optional($campaign->campaignBanners[0])->banner_id;
        } else {
            $bannerId = optional($campaign->campaignBanners()->first())->banner_id;
        }

        // variants
        if (array_key_exists('variants', $data)) {
            $variants = $data['variants'];
        } else if (!$campaign->campaignBanners->isEmpty()) {
            $variants = $campaign->campaignBanners;
        } else {
            $variants = $campaign->campaignBanners()
                                ->with('banner')
                                ->get();
        }

        return [
            $campaign,
            $bannerId,
            $variants,
            $selectedCountries,
            isset($data['countries_blacklist'])
                ? $data['countries_blacklist']
                : $blacklisted
        ];
    }

    public function getAllSegments(SegmentAggregator $segmentAggregator)
    {
        try {
            $segments = $segmentAggregator->list();
        } catch (SegmentException $e) {
            $segments = new Collection();
            flash('Unable to fetch list of segments, please check the application configuration.')->error();
            Log::error($e->getMessage());
        }

        foreach ($segmentAggregator->getErrors() as $error) {
            flash($error)->error();
            Log::error($error);
        }

        return $segments;
    }

    public function stats(
        Campaign $campaign,
        Request $request,
        Stats $stats
    ) {
        return view('campaigns.stats', [
            'campaign' => $campaign,
            'from' => $request->input('from', 'now - 2 days'),
            'to' => $request->input('to', 'now'),
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
