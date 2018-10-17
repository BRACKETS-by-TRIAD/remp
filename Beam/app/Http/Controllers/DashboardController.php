<?php

namespace App\Http\Controllers;

use App\Article;
use App\Contracts\JournalAggregateRequest;
use App\Contracts\JournalConcurrentsRequest;
use App\Contracts\JournalContract;
use App\Contracts\JournalHelpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use InvalidArgumentException;

class DashboardController extends Controller
{
    private const NUMBER_OF_ARTICLES = 30;

    private $journal;

    private $journalHelper;

    public function __construct(JournalContract $journal)
    {
        $this->journal = $journal;
        $this->journalHelper = new JournalHelpers($journal);
    }

    public function index()
    {
        return view('dashboard.index');
    }

    public function public()
    {
        return view('dashboard.public');
    }

    private function getJournalParameters($interval, $tz)
    {
        switch ($interval) {
            case 'today':
                return [Carbon::tomorrow($tz), Carbon::today($tz), '20m', 20];
            case '7days':
                return [Carbon::tomorrow($tz), Carbon::today($tz)->subDays(6), '1h', 60];
            case '30days':
                return [Carbon::tomorrow($tz), Carbon::today($tz)->subDays(29), '2h', 120];
            default:
                throw new InvalidArgumentException("Parameter 'interval' must be one of the [today,7days,30days] values, instead '$interval' provided");
        }
    }

    public function timeHistogram(Request $request)
    {
        $request->validate([
            'tz' => 'timezone',
            'interval' => 'required|in:today,7days,30days',
            'settings.compareWith' => 'required|in:last_week,average'
        ]);

        $settings = $request->get('settings');

        $tz = new \DateTimeZone($request->get('tz', 'UTC'));
        $interval = $request->get('interval');
        [$timeBefore, $timeAfter, $intervalText, $intervalMinutes] = $this->getJournalParameters($interval, $tz);

        $journalRequest = new JournalAggregateRequest('pageviews', 'load');
        $journalRequest->setTimeAfter($timeAfter);
        $journalRequest->setTimeBefore($timeBefore);
        $journalRequest->setTimeHistogram($intervalText, '0h');
        $journalRequest->addGroup('derived_referer_medium');
        $currentRecords = $this->journal->count($journalRequest);

        // Get all tags
        $tags = [];
        foreach ($currentRecords as $records) {
            $tags[$records->tags->derived_referer_medium] = true;
        }

        // Compute shadow values for today and 7-days intervals
        $shadowRecords = [];
        if ($interval !== '30days') {
            $numberOfAveragedWeeks = $settings['compareWith'] === 'average' ? 4 : 1;

            for ($i = 1; $i <= $numberOfAveragedWeeks; $i++) {
                $from = (clone $timeBefore)->subWeeks($i);
                $to = (clone $timeAfter)->subWeeks($i);

                foreach ($this->pageviewRecordsBasedOnRefererMedium($from, $to, $intervalText) as $records) {
                    $currentTag = $records->tags->derived_referer_medium;
                    // update tags
                    $tags[$currentTag] = true;

                    if (!isset($records->time_histogram)) {
                        continue;
                    }

                    foreach ($records->time_histogram as $timeValue) {
                        // we want to plot previous results on same points as current ones,
                        // therefore add week which was subtracted before when data was queried
                        $correctedDate = Carbon::parse($timeValue->time)->addWeeks($i)->toIso8601ZuluString();

                        if (!array_key_exists($correctedDate, $shadowRecords)) {
                            $shadowRecords[$correctedDate] = [];
                        }
                        if (!array_key_exists($currentTag, $shadowRecords[$correctedDate])) {
                            $shadowRecords[$correctedDate][$currentTag] = collect();
                        }
                        $shadowRecords[$correctedDate][$currentTag]->push($timeValue->value);
                    }
                }
            }
        }

        $tags = array_keys($tags);

        // Values might be missing in time histogram, therefore fill all tags with 0s by default
        $results = [];
        $shadowResults = [];
        $shadowResultsSummed = [];
        $timeIterator = JournalHelpers::getTimeIterator($timeAfter, $intervalMinutes);

        $emptyValues = collect($tags)->mapWithKeys(function ($item) {
            return [$item => 0];
        })->toArray();

        while ($timeIterator->lessThan($timeBefore)) {
            $zuluDate = $timeIterator->toIso8601ZuluString();

            $results[$zuluDate] = $emptyValues;
            $results[$zuluDate]['Date'] = $zuluDate;

            if (count($shadowRecords) > 0) {
                $shadowResults[$zuluDate] = $emptyValues;
                $shadowResults[$zuluDate]['Date'] = $shadowResultsSummed[$zuluDate]['Date'] = $zuluDate;
                $shadowResultsSummed[$zuluDate]['value'] = 0;
            }

            $timeIterator->addMinutes($intervalMinutes);
        }

        // Save current results
        foreach ($currentRecords as $records) {
            if (!isset($records->time_histogram)) {
                continue;
            }
            $currentTag = $records->tags->derived_referer_medium;

            foreach ($records->time_histogram as $timeValue) {
                $results[$timeValue->time][$currentTag] = $timeValue->value;
            }
        }

        // Save shadow results
        foreach ($shadowRecords as $date => $tagsAndValues) {
            foreach ($tagsAndValues as $tag => $values) {
                $avg = (int) round($values->avg());

                $shadowResults[$date][$tag] = $avg;
                $shadowResultsSummed[$date]['value'] += $avg;
            }
        }

        // What part of current results we should draw (omit future 0 values)
        $numberOfCurrentValues = (int) floor((Carbon::now($tz)->getTimestamp() - $timeAfter->getTimestamp()) / ($intervalMinutes * 60));
        $results = collect(array_values($results))->take($numberOfCurrentValues);

        return response()->json([
            'intervalMinutes' => $intervalMinutes,
            'results' => $results,
            'previousResults' => array_values($shadowResults),
            'previousResultsSummed' => array_values($shadowResultsSummed),
            'tags' => $tags
        ]);
    }

    private function pageviewRecordsBasedOnRefererMedium(Carbon $timeBefore, Carbon $timeAfter, string $interval)
    {
        $journalRequest = new JournalAggregateRequest('pageviews', 'load');
        $journalRequest->setTimeAfter($timeAfter);
        $journalRequest->setTimeBefore($timeBefore);
        $journalRequest->setTimeHistogram($interval, '0h');
        $journalRequest->addGroup('derived_referer_medium');
        return $this->journal->count($journalRequest);
    }

    public function mostReadArticles(Request $request)
    {
        $request->validate([
            'settings.onlyTrafficFromFrontPage' => 'required|boolean'
        ]);

        $settings = $request->get('settings');

        $timeBefore = Carbon::now();
        $timeAfter = (clone $timeBefore)->subSeconds(600); // Last 10 minutes

        $concurrentsRequest = new JournalConcurrentsRequest();

        if ($settings['onlyTrafficFromFrontPage']) {
            $concurrentsRequest->addFilter('derived_referer_host_with_path', config('dashboard.frontpage_referrer'));
        }
        $concurrentsRequest->setTimeAfter($timeAfter);
        $concurrentsRequest->setTimeBefore($timeBefore);
        $concurrentsRequest->addGroup('article_id');

        // records are already sorted
        $records = $this->journal->concurrents($concurrentsRequest);

        $topPages = [];
        $i = 0;
        $totalConcurrents = 0;
        foreach ($records as $record) {
            $totalConcurrents += $record->count;

            if ($i >= self::NUMBER_OF_ARTICLES) {
                continue;
            }

            $obj = new \stdClass();
            $obj->count = $record->count;
            $obj->external_article_id = $record->tags->article_id;

            if (!$record->tags->article_id) {
                $obj->title = 'Landing page';
                $obj->landing_page = true;
            } else {
                $article = Article::where('external_id', $record->tags->article_id)->first();
                if (!$article) {
                    continue;
                }

                $obj->landing_page = false;
                $obj->title = $article->title;
                $obj->published_at = $article->published_at->toAtomString();
                $obj->conversions_count = $article->conversions->count();
                $obj->article = $article;
            }
            $topPages[] = $obj;
            $i++;
        }

        // Top articles without landing page(s)
        $topArticles = collect($topPages)->filter(function ($item) {
            return !empty($item->external_article_id);
        })->pluck('article');

        // Timespent is computed as average of timespent values 2 hours in the past
        $externalIdsToTimespent = $this->journalHelper->timespentForArticles(
            $topArticles,
            (clone $timeAfter)->subHours(2)
        );

        $externalIdsToUniqueUsersCount = $this->journalHelper->uniqueUsersCountForArticles($topArticles);

        $threeMonthsAgo = Carbon::now()->subMonths(3);

        foreach ($topPages as $item) {
            if ($item->external_article_id) {
                $secondsTimespent = $externalIdsToTimespent->get($item->external_article_id, 0);
                $item->avg_timespent_string = $secondsTimespent >= 3600 ?
                    gmdate('H:i:s', $secondsTimespent) :
                    gmdate('i:s', $secondsTimespent);
                $item->unique_browsers_count = $externalIdsToUniqueUsersCount[$item->external_article_id];
                // Show conversion rate only for articles published in last 3 months
                if ($item->conversions_count !== 0 && $item->article->published_at->gte($threeMonthsAgo)) {
                    // Artificially increased 10000x so conversion rate is more readable
                    $item->conversion_rate = number_format(($item->conversions_count / $item->unique_browsers_count) * 10000, 2);
                }

                $item->url = route('articles.show', ['article' => $item->article->id]);
            }
        }

        return response()->json([
            'articles' => $topPages,
            'totalConcurrents' => $totalConcurrents
        ]);
    }
}
