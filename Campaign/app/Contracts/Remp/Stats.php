<?php

namespace App\Contracts\Remp;

use GuzzleHttp\Client;
use App\Contracts\StatsContract;
use Carbon\Carbon;

class Stats implements StatsContract
{
    private $client;
    private $timeOffset;

    public function __construct(Client $client, $timeOffset)
    {
        $this->client = $client;
        $this->timeOffset = $timeOffset;
    }

    public function forCampaign($campaignId) : StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->forCampaign($campaignId);
    }

    public function forVariant($variantId) : StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->events($variantId);
    }

    public function events(string $categoryArg, string $actionArg): StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->events($categoryArg, $actionArg);
    }

    public function pageviews(): StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->pageviews();
    }

    public function timespent(): StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->timespent();
    }

    public function from(Carbon $from): StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->from($from);
    }

    public function to(Carbon $to): StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->to($to);
    }

    public function commerce(string $step): StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->commerce($step);
    }

    public function timeHistogram(string $interval): StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->timeHistogram($interval);
    }

    public function count(): StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->count();
    }

    public function sum(): StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->sum();
    }

    public function filterBy(string $field, array $values): StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->filterBy($field, $values);
    }

    public function groupBy($field): StatsRequest
    {
        return (new StatsRequest($this->client, $this->timeOffset))->groupBy($field);
    }
}
