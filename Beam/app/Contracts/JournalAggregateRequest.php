<?php

namespace App\Contracts;

class JournalAggregateRequest
{
    protected $category;

    protected $action;

    protected $filterBy = [];

    protected $groupBy = [];

    protected $timeBefore;

    protected $timeAfter;

    protected $timeHistogram = [];

    public static function from($category, $action = null)
    {
        return new self($category, $action);
    }

    public function __construct($category, $action = null)
    {
        $this->category = $category;
        $this->action = $action;
    }

    public function addFilter(string $tag, string ...$values): JournalAggregateRequest
    {
        foreach ($values as &$v) {
            $v = strval($v);
        }
        $this->filterBy[] = [
            "tag" => $tag,
            "values" => $values,
        ];
        return $this;
    }

    public function addGroup(string ...$tags): JournalAggregateRequest
    {
        $this->groupBy = array_merge($this->groupBy, $tags);
        return $this;
    }

    public function setTimeHistogram(string $interval, string $offset): JournalAggregateRequest
    {
        $this->timeHistogram = [
            'interval' => $interval,
            'offset' => $offset
        ];
        return $this;
    }

    public function setTimeBefore(\DateTime $timeBefore): JournalAggregateRequest
    {
        $this->timeBefore = $timeBefore;
        return $this;
    }

    public function setTimeAfter(\DateTime $timeAfter): JournalAggregateRequest
    {
        $this->timeAfter = $timeAfter;
        return $this;
    }

    public function setTime(\DateTime $timeAfter, \DateTime $timeBefore): JournalAggregateRequest
    {
        $this->timeAfter = $timeAfter;
        $this->timeBefore = $timeBefore;
        return $this;
    }

    public function buildUrl($template): string
    {
        if ($this->action) {
            return sprintf($template, $this->category, $this->action);
        }

        return sprintf($template, $this->category);
    }

    public function buildUrlWithItem($template, $item): string
    {
        return sprintf($template, $this->category, $this->action, $item);
    }

    public function getCategory()
    {
        return $this->category;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getFilterBy(): array
    {
        return $this->filterBy;
    }

    public function getGroupBy(): array
    {
        return $this->groupBy;
    }

    public function getTimeBefore(): ?\DateTime
    {
        return $this->timeBefore;
    }

    public function getTimeAfter(): ?\DateTime
    {
        return $this->timeAfter;
    }

    public function getTimeHistogram(): array
    {
        return $this->timeHistogram;
    }
}
