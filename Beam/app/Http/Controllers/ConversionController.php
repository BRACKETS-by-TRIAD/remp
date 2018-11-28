<?php

namespace App\Http\Controllers;

use App\Author;
use App\Contracts\JournalContract;
use App\Contracts\JournalHelpers;
use App\Contracts\JournalListRequest;
use App\Conversion;
use App\Http\Request;
use App\Http\Requests\ConversionRequest;
use App\Http\Requests\ConversionUpsertRequest;
use App\Http\Resources\ConversionResource;
use App\Section;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Remp\LaravelHelpers\Resources\JsonResource;
use Yajra\Datatables\Datatables;

class ConversionController extends Controller
{
    private $journal;

    private $journalHelper;

    public function __construct(JournalContract $journal)
    {
        $this->journal = $journal;
        $this->journalHelper = new JournalHelpers($journal);
    }

    public function index(Request $request)
    {
        return response()->format([
            'html' => view('conversions.index', [
                'authors' => Author::all()->pluck('name', 'id'),
                'sections' => Section::all()->pluck('name', 'id'),
                'conversionFrom' => $request->get('conversion_from', 'now - 30 days'),
                'conversionTo' => $request->get('conversion_to', 'now'),
            ]),
            'json' => ConversionResource::collection(Conversion::paginate()),
        ]);
    }

    public function json(Request $request, Datatables $datatables)
    {
        $conversions = Conversion::select('conversions.*')
            ->with(['article', 'article.authors', 'article.sections'])
            ->join('articles', 'articles.id', '=', 'conversions.article_id')
            ->join('article_author', 'articles.id', '=', 'article_author.article_id')
            ->join('article_section', 'articles.id', '=', 'article_section.article_id');


        if ($request->input('conversion_from')) {
            $conversions->where('paid_at', '>=', Carbon::parse($request->input('conversion_from'), $request->input('tz'))->tz('UTC'));
        }
        if ($request->input('conversion_to')) {
            $conversions->where('paid_at', '<=', Carbon::parse($request->input('conversion_to'), $request->input('tz'))->tz('UTC'));
        }

        return $datatables->of($conversions)
            ->addColumn('actions', function (Conversion $conversion) {
                return [
                    'show' => route('conversions.show', $conversion),
                ];
            })
            ->addColumn('article.title', function (Conversion $conversion) {
                return \HTML::link(route('articles.show', ['article' => $conversion->article->id]), $conversion->article->title);
            })
            ->filterColumn('article.authors[, ].name', function (Builder $query, $value) {
                $values = explode(",", $value);
                $query->whereIn('article_author.author_id', $values);
            })
            ->filterColumn('article.sections[, ].name', function (Builder $query, $value) {
                $values = explode(",", $value);
                $query->whereIn('article_section.section_id', $values);
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function store(ConversionRequest $request)
    {
        $conversion = new Conversion();
        $conversion->fill($request->all());
        $conversion->save();

        return response()->format([
            'html' => redirect(route('conversions.index'))->with('success', 'Conversion created'),
            'json' => new ConversionResource($conversion),
        ]);
    }

    public function show(Conversion $conversion)
    {
        $timeBefore = clone $conversion->paid_at;
        $timeAfter = (clone $timeBefore)->subDays(14);
        $actions = collect();

        // Commerce
        $commerces = $this->journal->list(JournalListRequest::from('commerce')
            ->addFilter('user_id', $conversion->user_id)
            ->setTimeAfter($timeAfter)
            ->setTimeBefore($timeBefore));

        if ($commerces->isNotEmpty()) {
            foreach ($commerces[0]->commerces as $item) {
                if (isset($item->system->time, $item->step)) {
                    $obj = new \stdClass();
                    $obj->time = Carbon::parse($item->system->time);
                    $obj->action = "commerce:{$item->step}";
                    $actions->push($obj);
                }
            }
        }

        // Events
        $events = $this->journal->list(JournalListRequest::from('events')
            ->addFilter('user_id', $conversion->user_id)
            ->setTimeAfter($timeAfter)
            ->setTimeBefore($timeBefore));

        if ($events->isNotEmpty()) {
            foreach ($events[0]->events as $item) {
                if (isset($item->system->time, $item->action, $item->category)) {
                    $obj = new \stdClass();
                    $obj->time = Carbon::parse($item->system->time);
                    $obj->action = "{$item->action}:{$item->category}";
                    $actions->push($obj);
                }
            }
        }

        // Pageviews
        $pageviews = $this->journal->list(JournalListRequest::from('pageviews')
            ->addFilter('user_id', $conversion->user_id)
            ->setTimeAfter($timeAfter)
            ->setTimeBefore($timeBefore));

        if ($pageviews->isNotEmpty()) {
            foreach ($pageviews[0]->pageviews as $item) {
                if (isset($item->system->time, $item->article->id)) {
                    $obj = new \stdClass();
                    $obj->time = Carbon::parse($item->system->time);
                    $obj->action = 'pageview';
                    $obj->article_id = $item->article->id;
                    $actions->push($obj);
                }
            }
        }

        $actions->sortBy('time');

        return response()->format([
            'html' => view('conversions.show', [
                'conversion' => $conversion,
                'actions' => $actions
            ]),
            'json' => new ConversionResource($conversion),
        ]);
    }

    public function upsert(ConversionUpsertRequest $request)
    {
        foreach ($request->get('conversions', []) as $c) {
            // When saving to DB, Eloquent strips timezone information,
            // therefore convert to UTC
            $c['paid_at'] = Carbon::parse($c['paid_at'])->tz('UTC');
            $conversion = Conversion::firstOrNew([
                'transaction_id' => $c['transaction_id'],
            ]);
            $conversion->fill($c);
            $conversion->save();
        }

        return response()->format([
            'html' => redirect(route('conversions.index'))->with('success', 'Conversions created'),
            'json' => new JsonResource([]),
        ]);
    }
}
