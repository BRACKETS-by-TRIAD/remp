<?php

namespace App\Contracts;

use App\CampaignSegment;
use Illuminate\Support\Collection;

interface SegmentContract
{
    const BLOOM_FILTER_CACHE_TAG = 'segment_bloom';

    public function list(): Collection;

    public function check(CampaignSegment $campaignSegment, $userId): bool;

    public function users(CampaignSegment $campaignSegment): Collection;

    public function provider(): string;

    public function cacheEnabled(CampaignSegment $campaignSegment): bool;

    /**
     * setCache stores and provides cache object for campaign segment providers.
     *
     * @param $cache \stdClass Array of objects keyed by name of the segment provider. Internals
     *                     of the stored object is defined by contract of provider itself
     *                     and not subject of validation here.
     */
    public function setCache($cache): void;

    /**
     * getCache returns internal cache objects for each segment provider to be stored
     * by third party and provided later via *setCache()* call.
     *
     * @return \stdClass
     */
    public function getCache();
}
