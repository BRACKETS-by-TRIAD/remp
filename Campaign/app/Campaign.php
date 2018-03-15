<?php

namespace App;

use Cache;
use Fico7489\Laravel\Pivot\Traits\PivotEventTrait;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class Campaign extends Model
{
    use PivotEventTrait;

    const ACTIVE_CAMPAIGN_IDS = 'active_campaign_ids';
    const CAMPAIGN_TAG = 'campaign';

    const PAGEVIEW_RULE_EVERY = 'every';
    const PAGEVIEW_RULE_SINCE = 'since';
    const PAGEVIEW_RULE_BEFORE = 'before';

    protected $fillable = [
        'name',
        'signed_in',
        'active',
        'once_per_session',
        'pageview_rules',
        'target_desktop',
        'target_mobile'
    ];

    protected $casts = [
        'active' => 'boolean',
        'signed_in' => 'boolean',
        'once_per_session' => 'boolean',
        'pageview_rules' => 'json',
        'target_desktop' => 'boolean',
        'target_mobile' => 'boolean'
    ];

    protected $attributes = [
        'active' => false,
        'once_per_session' => false,
        'pageview_rules' => '[]'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (Campaign $campaign) {
            $campaign->uuid = Uuid::uuid4()->toString();
        });
    }

    public function banners()
    {
        return $this->belongsToMany(Banner::class, 'campaign_banners')->withPivot('variant');
    }

    public function banner()
    {
        return $this->banners()->wherePivot('variant', '=', 'A');
    }

    public function getBannerAttribute()
    {
        if ($this->relationLoaded('banner')) {
            return $this->getRelation('banner')->first();
        }
        return $this->banner()->first();
    }

    public function altBanner()
    {
        return $this->banners()->wherePivot('variant', '=', 'B');
    }

    public function setBannerIdAttribute($value)
    {
        $data = [$value => ['variant' => 'A']];
        $this->banner()->sync($data);
    }

    public function getAltBannerAttribute()
    {
        if ($this->relationLoaded('altBanner')) {
            return $this->getRelation('altBanner')->first();
        }
        return $this->altBanner()->first();
    }

    public function setAltBannerIdAttribute($value)
    {
        $data = [];
        if (!empty($value)) {
            $data[$value] = ['variant' => 'B'];
        }
        $this->altBanner()->sync($data);
    }

    public function countries()
    {
        return $this->belongsToMany(
            Country::class,
            null,
            null,
            'country_iso_code',
            null,
            'iso_code'
        )->withPivot('blacklisted');
    }

    public function countriesWhitelist()
    {
        return $this->countries()->wherePivot('blacklisted', '=', false);
    }

    public function countriesBlacklist()
    {
        return $this->countries()->wherePivot('blacklisted', '=', true);
    }

    public function segments()
    {
        return $this->hasMany(CampaignSegment::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function cache()
    {
        $activeCampaignIds = self::whereActive(true)->pluck('id')->toArray();
        $campaign = $this->where(['id' => $this->id])->with([
            'segments',
            'banner',
            'banner.htmlTemplate',
            'banner.mediumRectangleTemplate',
            'banner.barTemplate',
            'banner.shortMessageTemplate',
            'altBanner',
            'altBanner.htmlTemplate',
            'altBanner.mediumRectangleTemplate',
            'altBanner.barTemplate',
            'altBanner.shortMessageTemplate',
            'countries',
            'countriesWhitelist',
            'countriesBlacklist',
            'schedules',
        ])->first();
        Cache::forever(self::ACTIVE_CAMPAIGN_IDS, $activeCampaignIds);
        Cache::tags([self::CAMPAIGN_TAG])->forever($this->id, $campaign);
    }
}
