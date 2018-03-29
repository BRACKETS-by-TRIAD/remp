<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Article extends Model
{
    protected $fillable = [
        'property_uuid',
        'external_id',
        'title',
        'author',
        'url',
        'section',
        'image_url',
        'published_at',
        'pageviews_all',
        'pageviews_signed_in',
        'pageviews_subscribers',
    ];

    protected $dates = [
        'published_at',
        'created_at',
        'updated_at',
    ];

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_uuid', 'uuid');
    }

    public function authors()
    {
        return $this->belongsToMany(Author::class);
    }

    public function sections()
    {
        return $this->belongsToMany(Section::class);
    }

    public function conversions()
    {
        return $this->hasMany(Conversion::class);
    }

    public function pageviews()
    {
        return $this->hasMany(ArticlePageviews::class);
    }

    public function timespent()
    {
        return $this->hasMany(ArticleTimespent::class);
    }

    public function setPublishedAtAttribute($value)
    {
        if (!$value) {
            return;
        }
        $this->attributes['published_at'] = new Carbon($value);
    }
}
