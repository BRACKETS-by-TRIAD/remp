<?php

namespace App\Console\Commands;

use App\Article;
use App\Contracts\JournalAggregateRequest;
use App\Contracts\JournalContract;
use App\Contracts\JournalHelpers;
use App\Contracts\JournalListRequest;
use App\Conversion;
use App\Model\ConversionCommerceEvent;
use App\Model\ConversionCommerceEventProduct;
use App\Model\ConversionGeneralEvent;
use App\Model\ConversionPageviewEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AggregateConversionEvents extends Command
{
    const COMMAND = 'conversions:aggregate-events';
    const DAYS_IN_PAST = 14;

    protected $signature = self::COMMAND . ' {--conversion_id=} {--days=' . self::DAYS_IN_PAST . '}';

    protected $description = 'Aggregate events prior given conversion';

    private $journal;

    private $journalHelper;

    public function __construct(JournalContract $journal)
    {
        parent::__construct();
        $this->journal = $journal;
        $this->journalHelper = new JournalHelpers($journal);
    }

    public function handle()
    {
        $this->line('Aggregation of conversion events started');
        $conversionId = $this->option('conversion_id') ?? null;

        $days = (int) $this->option('days');

        if ($conversionId) {
            $conversion = Conversion::find($conversionId);
            if (!$conversion) {
                $this->error("Conversion with ID $conversionId not found.");
                return;
            }

            if ($conversion->pageviewEvents->count() > 0 ||
                $conversion->commerceEvents->count() > 0 ||
                $conversion->generalEvents->count() > 0) {
                $this->info("Conversion with ID $conversionId already aggregated.");
                return;
            }

            $this->aggregateConversion($conversion, $days);
        } else {
            foreach ($this->getUnaggregatedConversions() as $conversion) {
                $this->aggregateConversion($conversion, $days);
            }
        }

        $this->line(' <info>OK!</info>');
    }

    protected function getBrowsersForUser(Conversion $conversion, $category, $action = null)
    {
        $before = $conversion->paid_at;
        // take maximum one year old browser IDs
        $after = (clone $before)->subYear();

        $browserIds = [];
        $records = $this->journal->count(JournalAggregateRequest::from($category, $action)
            ->addGroup('browser_id')
            ->addFilter('user_id', $conversion->user_id)
            ->setTime($after, $before));
        foreach ($records as $record) {
            if ($record->tags->browser_id && $record->tags->browser_id !== '') {
                $browserIds[] = $record->tags->browser_id;
            }
        }

        return $browserIds;
    }

    private function getUnaggregatedConversions()
    {
        $unionQuery = ConversionGeneralEvent::select('conversion_id')
            ->groupBy('conversion_id')
            ->union(ConversionCommerceEvent::select('conversion_id')->groupBy('conversion_id'))
            ->union(ConversionPageviewEvent::select('conversion_id')->groupBy('conversion_id'));

        return Conversion::select('conversions.*')
            ->leftJoinSub($unionQuery, 't', function ($join) {
                $join->on('conversions.id', 't.conversion_id');
            })
            ->whereNotNull('conversions.user_id')
            ->whereNull('t.conversion_id')
            ->get();
    }

    protected function aggregateConversion(Conversion $conversion, int $days)
    {
        if (!$conversion->user_id) {
            $this->line("Conversion #{$conversion->user_id} has no assigned user.");
            return;
        }
        $browserIds = $this->getBrowsersForUser($conversion, 'pageviews', 'load');
        $this->loadAndStorePageviewEvents(
            $conversion,
            $this->buildJournalListRequest('pageviews', $browserIds, $conversion, $days)
                ->setLoadTimespent()
        );

        $browserIds = $this->getBrowsersForUser($conversion, 'commerce');
        $this->loadAndStoreCommerceEvents(
            $conversion,
            $this->buildJournalListRequest('commerce', $browserIds, $conversion, $days)
        );

        $browserIds = $this->getBrowsersForUser($conversion, 'events');
        $this->loadAndStoreGeneralEvents(
            $conversion,
            $this->buildJournalListRequest('events', $browserIds, $conversion, $days)
        );
    }

    private function buildJournalListRequest(string $category, array $browserIds, Conversion $conversion, int $days): JournalListRequest
    {
        $r = JournalListRequest::from($category)
            ->setTime((clone $conversion->paid_at)->subDays($days), $conversion->paid_at);

        if ($browserIds) {
            $r->addFilter('browser_id', ...$browserIds);
        } else {
            $r->addFilter('user_id', $conversion->user_id);
        }

        return $r;
    }

    protected function loadAndStorePageviewEvents(Conversion $conversion, JournalListRequest $request)
    {
        $events = $this->journal->list($request);
        if ($events->isNotEmpty()) {
            foreach ($events[0]->pageviews as $item) {
                if (!isset($item->article->id)) {
                    continue;
                }
                $article = Article::where('external_id', $item->article->id)->first();

                if ($article) {
                    ConversionPageviewEvent::create([
                        'conversion_id' => $conversion->id,
                        'time' => Carbon::parse($item->system->time)->tz('UTC'),
                        'article_id' => $article->id,
                        'locked' => isset($item->article->locked) ? filter_var($item->article->locked, FILTER_VALIDATE_BOOLEAN) : null,
                        'timespent' => $item->user->timespent ?? null,
                        'utm_campaign' => $item->user->source->utm_campaign ?? null,
                        'utm_content' => $item->user->source->utm_content ?? null,
                        'utm_medium' => $item->user->source->utm_medium ?? null,
                        'utm_source' => $item->user->source->utm_source ?? null,
                    ]);
                }
            }
        }
    }

    protected function loadAndStoreCommerceEvents(Conversion $conversion, JournalListRequest $request)
    {
        $events = $this->journal->list($request);
        if ($events->isNotEmpty()) {
            foreach ($events[0]->commerces as $item) {
                $commerceEvent = ConversionCommerceEvent::create([
                    'time' => Carbon::parse($item->system->time)->tz('UTC'),
                    'step' => $item->step,
                    'funnel_id' => $item->details->funnel_id ?? null,
                    'amount' => $item->revenue->amount ?? null,
                    'currency' => $item->revenue->currency ?? null,
                    'utm_campaign' => $item->source->utm_campaign ?? null,
                    'utm_content' => $item->source->utm_content ?? null,
                    'utm_medium' => $item->source->utm_medium ?? null,
                    'utm_source' => $item->source->utm_source ?? null,
                    'conversion_id' => $conversion->id,
                ]);

                if (isset($item->details->product_ids)) {
                    foreach ($item->details->product_ids as $productId) {
                        $product = new ConversionCommerceEventProduct(['product_id' => $productId]);
                        $commerceEvent->products()->save($product);
                    }
                }
            }
        }
    }

    protected function loadAndStoreGeneralEvents(Conversion $conversion, JournalListRequest $request)
    {
        $events = $this->journal->list($request);
        if ($events->isNotEmpty()) {
            foreach ($events[0]->events as $item) {
                ConversionGeneralEvent::create([
                    'time' => Carbon::parse($item->system->time)->tz('UTC'),
                    'action' => $item->action ?? null,
                    'category' => $item->category ?? null,
                    'conversion_id' => $conversion->id,
                    'utm_campaign' => $item->utm_campaign ?? null,
                    'utm_content' => $item->utm_content ?? null,
                    'utm_medium' => $item->utm_medium ?? null,
                    'utm_source' => $item->utm_source ?? null,
                ]);
            }
        }
    }
}
