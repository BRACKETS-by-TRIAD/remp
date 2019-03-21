<?php

namespace App\Console\Commands;

use App\Campaign;
use App\CampaignBannerStats;
use App\Contracts\StatsHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AggregateCampaignStats extends Command
{
    const COMMAND = 'campaigns:aggregate-stats';

    protected $signature = self::COMMAND . ' {--now=} {--include-inactive}';

    protected $description = 'Reads campaign stats from journal and stores aggregated data';

    private $statsHelper;

    public function __construct(StatsHelper $statsHelper)
    {
        parent::__construct();
        $this->statsHelper = $statsHelper;
    }

    public function handle()
    {
        $now = $this->option('now') ? Carbon::parse($this->option('now')) : Carbon::now();
        $timeBefore = $now->minute(0)->second(0);
        $timeAfter = (clone $timeBefore)->subHour();

        $this->line(sprintf("Fetching stats data for campaigns between <info>%s</info> to <info>%s</info>.", $timeAfter, $timeBefore));

        $campaigns = Campaign::all();
        if (!$this->option('include-inactive')) {
            $campaigns = $campaigns->filter(function ($item) {
                return $item->active;
            });
        }

        foreach ($campaigns as $campaign) {
            foreach ($campaign->campaignBanners as $campaignBanner) {
                $stats = $this->statsHelper->variantStats($campaignBanner, $timeAfter, $timeBefore);

                /** @var CampaignBannerStats $cbs */
                $cbs = CampaignBannerStats::firstOrNew([
                    'campaign_banner_id' => $campaignBanner->id,
                    'time_from' => $timeAfter,
                    'time_to' => $timeBefore,
                ]);

                $cbs->click_count = $stats['click_count']->count ?? 0;
                $cbs->show_count = $stats['show_count']->count ?? 0;
                $cbs->payment_count = $stats['payment_count']->count ?? 0;
                $cbs->purchase_count = $stats['purchase_count']->count ?? 0;
                $cbs->purchase_sum = $stats['purchase_sum']->sum ?? 0.0;
                $cbs->purchase_currency = $stats['purchase_sum']->tags->currency ?? null;
                $cbs->save();
            }
        }

        $this->line(' <info>OK!</info>');
    }
}
