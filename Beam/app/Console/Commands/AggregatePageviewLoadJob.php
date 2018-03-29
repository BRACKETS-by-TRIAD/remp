<?php

namespace App\Console\Commands;

use App\Article;
use App\ArticlePageviews;
use App\Contracts\JournalAggregateRequest;
use App\Contracts\JournalContract;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregatePageviewLoadJob extends Command
{
    protected $signature = 'pageviews:aggregate-load {--now=}';

    protected $description = 'Reads pageview/load data from journal and stores aggregated data';

    /** @var JournalContract */
    private $journalContract;

    private $timeBefore;

    private $timeAfter;

    public function handle(JournalContract $journalContract)
    {
        $now = $this->hasOption('now') ? Carbon::parse($this->option('now')) : Carbon::now();
        $this->timeBefore = $now->minute(0)->second(0);
        $this->timeAfter = (clone $this->timeBefore)->subHour();

        $this->journalContract = $journalContract;

        $this->line(sprintf("Fetching aggregated pageviews data from <info>%s</info> to <info>%s</info>.", $this->timeAfter, $this->timeBefore));

        $request = new JournalAggregateRequest('pageviews', 'load');
        $request->setTimeAfter($this->timeAfter);
        $request->setTimeBefore($this->timeBefore);
        $request->addGroup('article_id', 'signed_in', 'subscriber');

        $records = $this->journalContract->count($request);

        if (count($records) === 1 && !isset($records[0]->tags->article_id)) {
            $this->line(sprintf("No articles to process, exiting."));
            return;
        }

        $bar = $this->output->createProgressBar(count($records));
        $bar->setFormat('%message%: %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->setMessage('Processing pageviews');

        $all = [];
        $signedIn = [];
        $subscribers = [];

        foreach ($records as $record) {
            if (empty($record->tags->article_id)) {
                $bar->advance();
                continue;
            }
            $bar->setMessage(sprintf("Processing pageviews for article ID: <info>%s</info>", $record->tags->article_id));

            $articleId = $record->tags->article_id;

            $all[$articleId] = $all[$articleId] ?? 0;
            $signedIn[$articleId] = $signedIn[$articleId] ?? 0;
            $subscribers[$articleId] = $subscribers[$articleId] ?? 0;

            $all[$articleId] += $record->count;
            if ($record->tags->signed_in === '1') {
                $signedIn[$articleId] += $record->count;
            }
            if ($record->tags->subscriber === '1') {
                $subscribers[$articleId] += $record->count;
            }

            $bar->advance();
        }
        $bar->finish();
        $this->line(' <info>OK!</info>');

        $bar = $this->output->createProgressBar(count($all));
        $bar->setFormat('%message%: %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $bar->setMessage('Storing aggregated data');

        foreach ($all as $articleId => $count) {
            $article = Article::select()->where([
                'external_id' => $articleId,
            ])->first();

            if (!$article) {
                $this->line(sprintf("\nUnable to find article with ID <error>%s</error> in article table.", $articleId));
                $bar->advance();
                continue;
            }

            /** @var ArticlePageviews $ap */
            $ap = ArticlePageviews::firstOrNew([
                'article_id' => $article->id,
                'time_from' => $this->timeAfter,
                'time_to' => $this->timeBefore,
            ]);

            $ap->sum = $count;
            $ap->signed_in = $signedIn[$articleId];
            $ap->subscribers = $subscribers[$articleId];
            $ap->save();

            $article->pageviews_all = $article->pageviews()->sum('sum');
            $article->pageviews_subscribers = $article->pageviews()->sum('subscribers');
            $article->pageviews_signed_in = $article->pageviews()->sum('signed_in');
            $article->save();

            $bar->advance();
        }
        $bar->finish();
        $this->line(' <info>OK!</info>');
    }
}
