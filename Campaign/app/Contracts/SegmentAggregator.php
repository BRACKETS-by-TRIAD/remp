<?php

namespace App\Contracts;

use App\CampaignSegment;
use Illuminate\Support\Collection;

class SegmentAggregator implements SegmentContract
{
    const TAG = 'segments';

    /** @var SegmentContract[] */
    private $contracts;

    public function __construct($segmentContracts)
    {
        /** @var SegmentContract $contract */
        foreach ($segmentContracts as $contract) {
            $this->contracts[$contract->provider()] = $contract;
        }
    }

    public function provider(): string
    {
        throw new SegmentException("Aggregator cannot return provider value");
    }

    public function list(): Collection
    {
        $collection = collect([]);
        foreach ($this->contracts as $contract) {
            $list = $contract->list();
            $collection = $collection->merge($list);
        }
        return $collection;
    }

    public function check(CampaignSegment $campaignSegment, $userId): bool
    {
        return $this->contracts[$campaignSegment->provider]
            ->check($campaignSegment, $userId);
    }

    public function users(CampaignSegment $campaignSegment): Collection
    {
        return $this->contracts[$campaignSegment->provider]
            ->users($campaignSegment);
    }

    public function cacheEnabled(CampaignSegment $campaignSegment): bool
    {
        return $this->contracts[$campaignSegment->provider]
            ->cacheEnabled($campaignSegment);
    }

    public function setCache($cache): void
    {
        foreach ($this->contracts as $provider => $contract) {
            if ($cache && isset($cache->$provider)) {
                $contract->setCache($cache->$provider);
            }
        }
    }

    public function getCache()
    {
        $cache = new \stdClass;
        foreach ($this->contracts as $provider => $contract) {
            if ($cc = $contract->getCache()) {
                $cache->$provider = $cc;
            }
        }
        return $cache;
    }
}
