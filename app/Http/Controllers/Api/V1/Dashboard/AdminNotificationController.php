<?php

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Notification\NotificationResource;
use App\Models\Notification;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    use ApiResponseTrait;

    public function __construct()
    {
        $this->middleware('role_or_permission:admin|notifications-index,api');
    }

    public function index(Request $request): mixed
    {
        $request->validate([
            'unread' => ['nullable', 'boolean'],
        ]);

        $notifications = Notification::with('notifiable')
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->get();

        return $this->respondWithCollection(
            NotificationResource::collection($notifications)
        );
    }
}