<?php

namespace App\Http\Controllers;

use App\Campaign;
use App\Http\Requests\ScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Schedule;
use Carbon\Carbon;
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

    public function json(Datatables $dataTables)
    {
        $schedule = Schedule::select()
            ->with(['campaign', 'campaign.banner'])
            ->whereHas('campaign', function (\Illuminate\Database\Eloquent\Builder $query) {
                $query->where('active', '=', true);
            })
            ->orderBy('start_time', 'DESC')
            ->orderBy('end_time', 'DESC')
            ->get();

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
            ->addColumn('action_methods', [
                'start' => 'POST',
                'pause' => 'POST',
                'stop' => 'POST',
                'destroy' => 'DELETE',
            ])
            ->addColumn('_csrf', csrf_token())
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
            ->editColumn('start_time', function (Schedule $schedule) {
                return $schedule->start_time;
            })
            ->editColumn('end_time', function (Schedule $schedule) {
                return $schedule->end_time;
            })
            ->rawColumns(['actions', 'action_methods', 'status'])
            ->setRowId('id')
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $schedule = new Schedule();
        $schedule->fill(old());
        $campaigns = Campaign::whereActive(true)->get();

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
            'html' => redirect(route('schedule.index'))->with('success', sprintf(
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
            'html' => redirect(route('schedule.index'))->with('success', sprintf(
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
            'html' => redirect(route('schedule.index'))->with('success', sprintf(
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
                'html' => redirect(route('schedule.index'))->with('success', sprintf(
                    "Schedule for campaign %s was not running, pause request ignored",
                    $schedule->campaign->name
                )),
                'json' => new JsonResource(new BadRequestHttpException('cannot pause schedule: not running')),
            ]);
        }

        $schedule->status = Schedule::STATUS_PAUSED;
        $schedule->save();

        return response()->format([
            'html' => redirect(route('schedule.index'))->with('success', sprintf(
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
                'html' => redirect(route('schedule.index'))->with('success', sprintf(
                    "Schedule for campaign %s was not runnable, satrt request ignored",
                    $schedule->campaign->name
                )),
                'json' => new JsonResource(new BadRequestHttpException('cannot start schedule: not runnable')),
            ]);
        }

        $schedule->status = Schedule::STATUS_EXECUTED;
        $schedule->save();

        return response()->format([
            'html' => redirect(route('schedule.index'))->with('success', sprintf(
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
                'html' => redirect(route('schedule.index'))->with('success', sprintf(
                    "Schedule for campaign %s was not running, stop request ignored",
                    $schedule->campaign->name
                )),
                'json' => new JsonResource(new BadRequestHttpException('cannot stop schedule: not running')),
            ]);
        }

        $schedule->status = Schedule::STATUS_STOPPED;
        $schedule->save();

        return response()->format([
            'html' => redirect(route('schedule.index'))->with('success', sprintf(
                "Schedule for campaign %s was stopped",
                $schedule->campaign->name
            )),
            'json' => new ScheduleResource([]),
        ]);
    }
}
