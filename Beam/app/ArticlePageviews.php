<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ArticlePageviews extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'article_id',
        'time_from',
        'time_to',
        'sum',
        'signed_in',
        'subscribers',
    ];

    protected $dates = [
        'time_from',
        'time_to',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}
