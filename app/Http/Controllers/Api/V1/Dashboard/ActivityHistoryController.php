<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Enum\UserTypeEnum;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class ActivityHistoryController extends Controller
{
    use ApiResponseTrait;

    public function index(Request $request)
    {
        abort_unless($request->user()->type === UserTypeEnum::ADMIN, 403);

        $filters = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'causer_id' => ['sometimes', 'integer', 'min:1'],
            'event' => ['sometimes', 'string', 'max:100'],
            'log_name' => ['sometimes', 'string', 'max:100'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ]);

        $activities = Activity::query()
            ->with('causer')
            ->when(isset($filters['causer_id']), fn ($query) => $query->where('causer_id', $filters['causer_id']))
            ->when(isset($filters['event']), fn ($query) => $query->where('event', $filters['event']))
            ->when(isset($filters['log_name']), fn ($query) => $query->where('log_name', $filters['log_name']))
            ->when(isset($filters['from']), fn ($query) => $query->whereDate('created_at', '>=', $filters['from']))
            ->when(isset($filters['to']), fn ($query) => $query->whereDate('created_at', '<=', $filters['to']))
            ->latest()
            ->paginate($filters['per_page'] ?? 20)
            ->through(fn (Activity $activity) => [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'event' => $activity->event,
                'actor' => $activity->causer ? [
                    'id' => $activity->causer->getKey(),
                    'name' => $activity->causer->name ?? null,
                ] : null,
                'subject' => $activity->subject_type ? [
                    'type' => class_basename($activity->subject_type),
                    'id' => $activity->subject_id,
                ] : null,
                'properties' => $activity->properties,
                'created_at' => $activity->created_at,
            ]);

        return $this->respondWithSuccess(__('Activity history'), [
            'activities' => $activities,
        ]);
    }
}