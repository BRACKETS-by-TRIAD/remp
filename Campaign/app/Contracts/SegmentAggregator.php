<?php

namespace App\Contracts;

use App\CampaignSegment;
use Illuminate\Support\Collection;
use Illuminate\Queue\SerializableClosure;

class SegmentAggregator implements SegmentContract
{
    const TAG = 'segments';

    /** @var SegmentContract[] */
    private $contracts = [];

    private $errors = [];

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
            try {
                $list = $contract->list();
                $collection = $collection->merge($list);
            } catch (\Exception $e) {
                $this->errors[] = sprintf("%s: %s", $contract->provider(), $e->getMessage());
            }
        }
        return $collection;
    }

    public function checkUser(CampaignSegment $campaignSegment, string $userId): bool
    {
        return $this->contracts[$campaignSegment->provider]
            ->checkUser($campaignSegment, $userId);
    }

    public function checkBrowser(CampaignSegment $campaignSegment, string $browserId): bool
    {
        return $this->contracts[$campaignSegment->provider]
            ->checkBrowser($campaignSegment, $browserId);
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

    public function setProviderData($cache): void
    {
        foreach ($this->contracts as $provider => $contract) {
            if ($cache && isset($cache->$provider)) {
                $contract->setProviderData($cache->$provider);
            }
        }
    }

    public function getProviderData()
    {
        $cache = new \stdClass;
        foreach ($this->contracts as $provider => $contract) {
            if ($cc = $contract->getProviderData()) {
                $cache->$provider = $cc;
            }
        }
        return $cache;
    }

    public function hasErrors()
    {
        return count($this->errors) > 0;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getSerializableClosure(): SerializableClosure
    {
        return new SerializableClosure(function () {
            return $this;
        });
    }
}
