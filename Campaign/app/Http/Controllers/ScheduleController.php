<?php

namespace App\Http\Controllers;

use App\Campaign;
use App\Http\Requests\ScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Schedule;
use Carbon\Carbon;
use HTML;
use Illuminate\Http\Request;
use Remp\LaravelHelpers\Resources\JsonResource;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Yajra\Datatables\Datatables;

class ScheduleController extends Controller
{
    public function index()
    {
        return response()->format([
            'html' => view('schedule.index'),
            'json' => Schedule::paginate(),
        ]);
    }

    /**
     * Return data for Schedule Datatable.
     *
     * If `$campaign` is provided, only schedules for that one Campaign are returned.
     *
     * Request parameters can be used:
     *
     *   * `active` - (bool) display only active (running or planned) schedules.
     *   * `limit`  - (int) count of results will be limited to that number.
     *
     * @param Datatables $dataTables
     * @param Campaign|null $campaign
     * @return mixed
     */
    public function json(Request $request, Datatables $dataTables, Campaign $campaign = null)
    {
        $scheduleSelect = Schedule::select()
            ->with(['campaign'])
            ->orderBy('start_time', 'ASC')
            ->orderBy('end_time', 'ASC');

        if (!is_null($campaign)) {
            $scheduleSelect->where('campaign_id', '=', $campaign->id);
        }

        if ($request->active) {
            $scheduleSelect->where(function (\Illuminate\Database\Eloquent\Builder $query) {
                $query
                    ->whereNull('end_time')
                    ->orWhere('end_time', '>=', Carbon::now());
            })
                ->whereIn('status', [Schedule::STATUS_READY, Schedule::STATUS_EXECUTED, Schedule::STATUS_PAUSED]);
        }

        if (is_numeric($request->limit)) {
            $scheduleSelect->limit($request->limit);
        }
        $schedule = $scheduleSelect->get();

        return $dataTables->of($schedule)
            ->addColumn('actions', function (Schedule $s) {
                return [
                    'edit' => $s->isEditable() ? route('schedule.edit', $s) : null,
                    'start' => $s->isRunnable() ? route('schedule.start', $s) : null,
                    'pause' => $s->isRunning() ? route('schedule.pause', $s) : null,
                    'stop' => $s->isRunning() ? route('schedule.stop', $s) : null,
                    'destroy' => $s->isEditable() ? route('schedule.destroy', $s) : null,
                ];
            })
            ->addColumn('campaign', function (Schedule $schedule) {
                return Html::linkRoute('campaigns.edit', $schedule->campaign->name, $schedule->campaign);
            })
            ->addColumn('action_methods', [
                'start' => 'POST',
                'pause' => 'POST',
                'stop' => 'POST',
                'destroy' => 'DELETE',
            ])
            ->addColumn('status', function (Schedule $schedule) {
                if ($schedule->isRunning()) {
                    return 'Running';
                }
                if ($schedule->status === Schedule::STATUS_PAUSED) {
                    return 'Paused';
                }
                if ($schedule->status === Schedule::STATUS_STOPPED) {
                    return 'Stopped';
                }
                if ($schedule->start_time > Carbon::now()) {
                    return 'Waiting for start';
                }
                if (!$schedule->isRunnable()) {
                    return 'Finished';
                }
                throw new \Exception('unhandled schedule status');
            })
            ->rawColumns(['actions', 'action_methods', 'status', 'banners', 'campaign'])
            ->setRowId('id')
            ->make(true);
    }

    /**
     * Show the form for creating a new schedule.
     *
     * If $campaign is provided, campaign is pre-selected and selectbox disabled.
     *
     * @param Campaign $campaign
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Campaign $campaign)
    {
        $schedule = new Schedule();
        $schedule->fill(old());
        $schedule->campaign_id = $campaign->id;
        $campaigns = Campaign::all();

        return view('schedule.create', [
            'schedule' => $schedule,
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param ScheduleRequest|Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(ScheduleRequest $request)
    {
        $schedule = new Schedule();
        $schedule->fill($request->all());
        $schedule->save();

        return response()->format([
            'html' => redirect(route('campaigns.index'))->with('success', sprintf(
                "Campaign %s scheduled from %s to %s",
                $schedule->campaign->name,
                Carbon::parse($schedule->start_time)->toDayDateTimeString(),
                Carbon::parse($schedule->end_time)->toDayDateTimeString()
            )),
            'json' => new ScheduleResource($schedule),
        ]);
    }

    public function edit(Schedule $schedule)
    {
        $schedule->fill(old());
        $campaigns = Campaign::whereActive(true)->get();

        return view('schedule.edit', [
            'schedule' => $schedule,
            'campaigns' => $campaigns,
        ]);
    }

    public function update(ScheduleRequest $request, Schedule $schedule)
    {
        $schedule->fill($request->all());
        $schedule->save();

        return response()->format([
            'html' => redirect(route('campaigns.index'))->with('success', sprintf(
                "Campaign %s rescheduled starting on %s and ending on %s",
                $schedule->campaign->name,
                Carbon::parse($schedule->start_time)->toDayDateTimeString(),
                Carbon::parse($schedule->end_time)->toDayDateTimeString()
            )),
            'json' => new ScheduleResource($schedule),
        ]);
    }

    public function destroy(Schedule $schedule)
    {
        $schedule->delete();

        return response()->format([
            'html' => redirect(route('campaigns.index'))->with('success', sprintf(
                "Schedule for campaign %s from %s to %s was removed",
                $schedule->campaign->name,
                Carbon::parse($schedule->start_time)->toDayDateTimeString(),
                Carbon::parse($schedule->end_time)->toDayDateTimeString()
            )),
            'json' => new ScheduleResource([]),
        ]);
    }

    /**
     * @param Schedule $schedule
     * @return \Illuminate\Http\Response
     */
    public function pause(Schedule $schedule)
    {
        if (!$schedule->isRunning()) {
            return response()->format([
                'html' => redirect(route('campaigns.index'))->with('success', sprintf(
                    "Schedule for campaign %s was not running, pause request ignored",
                    $schedule->campaign->name
                )),
                'json' => new JsonResource(new BadRequestHttpException('cannot pause schedule: not running')),
            ]);
        }

        $schedule->status = Schedule::STATUS_PAUSED;
        $schedule->save();

        return response()->format([
            'html' => redirect(route('campaigns.index'))->with('success', sprintf(
                "Schedule for campaign %s is now paused",
                $schedule->campaign->name
            )),
            'json' => new ScheduleResource([]),
        ]);
    }

    /**
     * @param Schedule $schedule
     * @return \Illuminate\Http\Response
     */
    public function start(Schedule $schedule)
    {
        if (!$schedule->isRunnable()) {
            return response()->format([
                'html' => redirect(route('campaigns.index'))->with('success', sprintf(
                    "Schedule for campaign %s was not runnable, satrt request ignored",
                    $schedule->campaign->name
                )),
                'json' => new JsonResource(new BadRequestHttpException('cannot start schedule: not runnable')),
            ]);
        }

        $schedule->status = Schedule::STATUS_EXECUTED;
        $schedule->save();

        return response()->format([
            'html' => redirect(route('campaigns.index'))->with('success', sprintf(
                "Schedule for campaign %s was started manually",
                $schedule->campaign->name
            )),
            'json' => new ScheduleResource([]),
        ]);
    }

    /**
     * @param Schedule $schedule
     * @return \Illuminate\Http\Response
     */
    public function stop(Schedule $schedule)
    {
        if (!$schedule->isRunnable()) {
            return response()->format([
                'html' => redirect(route('campaigns.index'))->with('success', sprintf(
                    "Schedule for campaign %s was not running, stop request ignored",
                    $schedule->campaign->name
                )),
                'json' => new JsonResource(new BadRequestHttpException('cannot stop schedule: not running')),
            ]);
        }

        $schedule->status = Schedule::STATUS_STOPPED;
        $schedule->save();

        return response()->format([
            'html' => redirect(route('campaigns.index'))->with('success', sprintf(
                "Schedule for campaign %s was stopped",
                $schedule->campaign->name
            )),
            'json' => new ScheduleResource([]),
        ]);
    }
}
