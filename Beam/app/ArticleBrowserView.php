<?php

namespace App;

use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ArticleBrowserView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'article_id',
        'browser_id',
        'date',
        'pageviews',
        'timespent',
    ];
}
