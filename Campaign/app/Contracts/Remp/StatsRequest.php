<?php

namespace App\Contracts\Remp;

use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Contracts\StatsContract;
use App\Contracts\StatsException;
use GuzzleHttp\Exception\ClientException;

class StatsRequest implements StatsContract
{
    private $client;
    private $timeOffset;

    private $action;
    private $table;
    private $args = [];

    private $from;
    private $to;
    private $groupBy = [];
    private $filterBy = [];
    private $timeHistogram = [];

    public function __construct(Client $client, $timeOffset)
    {
        $this->timeOffset = $timeOffset;
        $this->client = $client;
        return $this;
    }

    public function forCampaign($campaignId)
    {
        $this->filterBy("utm_campaign", [$campaignId]);
        return $this;
    }

    public function forVariant($variantId)
    {
        $this->filterBy("banner_variant", [$variantId]);
        return $this;
    }

    public function events(string $categoryArg, string $actionArg) : StatsRequest
    {
        $this->args['categories'] = $categoryArg;
        $this->args['actions'] = $actionArg;
        $this->table = "events";
        return $this;
    }

    public function pageviews() : StatsRequest
    {
        $this->table = "pageviews";
        return $this;
    }

    public function timespent() : StatsRequest
    {
        $this->table = "pageviews";
        $this->action = "sum";
        return $this;
    }

    public function from(Carbon $from): StatsRequest
    {
        $this->from = $from->setTimezone('UTC')->toRfc3339String();
        return $this;
    }

    public function to(Carbon $to): StatsRequest
    {
        $this->to = $to->setTimezone('UTC')->toRfc3339String();
        return $this;
    }

    public function commerce(string $step) : StatsRequest
    {
        $this->args['steps'] = $step;
        $this->table = "commerce";
        return $this;
    }

    public function timeHistogram(string $interval) : StatsRequest
    {
        $this->timeHistogram = [
            'interval' => $interval,
            'offset' => $this->timeOffset,
        ];
        return $this;
    }

    public function count() : StatsRequest
    {
        $this->action = 'count';
        return $this;
    }

    public function sum() : StatsRequest
    {
        $this->action = 'sum';
        return $this;
    }

    public function filterBy(string $field, array $values) : StatsRequest
    {
        $this->filterBy[] = [
            'tag' => $field,
            'values' => $values
        ];
        return $this;
    }

    public function groupBy($field) : StatsRequest
    {
        if (is_string($field)) {
            $this->groupBy[] = $field;
        } else {
            $this->groupBy = array_merge($this->groupBy, $field);
        }

        return $this;
    }

    private function url() : string
    {
        $url = 'journal' . DIRECTORY_SEPARATOR . $this->table;

        foreach ($this->args as $arg => $val) {
            $url .= DIRECTORY_SEPARATOR . $arg . DIRECTORY_SEPARATOR . $val;
        }

        if ($this->action) {
            $url .= DIRECTORY_SEPARATOR . $this->action;
        }

        return $url;
    }

    public function get()
    {
        $payload = [
            'filter_by' => $this->filterBy,
            'group_by' => $this->groupBy,
        ];

        if ($this->from) {
            $payload['time_after'] = $this->from;
        }

        if ($this->to) {
            $payload['time_before'] = $this->to;
        }

        if ($this->timeHistogram) {
            $payload['time_histogram'] = $this->timeHistogram;
        }

        // dump(json_encode($payload));
        // dd($this->url());

        try {
            $result = $this->client->post($this->url(), [
                RequestOptions::JSON => $payload,
                RequestOptions::HEADERS => [
                    'Accept' => 'application/vnd.goa.error, application/vnd.count+json; type=collection',
                    'Content-Type' => 'application/json'
                ]
            ]);
        } catch (ClientException $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

        $stream = $result->getBody();

        // dd($stream);
        // dd(json_decode($stream->getContents()));

        try {
            $data = json_decode($stream->getContents());
        } catch (\Exception $e) {
            throw new StatsException('cannot decode json response', 400, $e);
        }

        // dd($data);

        return array_merge([
            'success' => true,
            'data' => $data[0]
        ]);
    }
}
