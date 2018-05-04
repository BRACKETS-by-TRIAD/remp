<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Campaign;
use Cache;

class ClearCampaignsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign:cache-clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear campaign cache.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Cache::forget(Campaign::ACTIVE_CAMPAIGN_IDS);

        Campaign::all()->map(function ($campaign) {
            Cache::tags([Campaign::CAMPAIGN_TAG])->forget($campaign->id);
        });

        $this->line('Campaigns cache cleared.');
    }
}
