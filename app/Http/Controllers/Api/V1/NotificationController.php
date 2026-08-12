<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\NotificationReadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\GetNotificationsRequest;
use App\Http\Requests\Notification\UpdateNotificationsStatusRequest;
use App\Http\Requests\Notification\UpdateNotificationStatusRequest;
use App\Http\Resources\Notification\NotificationCollection;
use App\Http\Resources\Notification\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * Display a listing of the authenticated user's notifications.
     */
    public function index(GetNotificationsRequest $request): JsonResponse
    {
        $notifications = $request->user()->notifications()
            ->latest()
            ->cursorPaginate($request->validated('per_page') ?? config('pagination.default_per_page'));

        // NotificationCollection is nested inside successResponse()'s envelope rather than
        // returned directly, so Resource::additional() never gets a chance to merge (that
        // only happens in JsonResource::toResponse()) — build the extra key in manually.
        $body = (new NotificationCollection($notifications))->toArray($request);
        $body['unread_count'] = $request->user()->unreadNotifications()->count();

        return $this->successResponse($body, 'Notifications retrieved successfully.');
    }

    /**
     * Mark a single notification as read or unread.
     */
    public function update(UpdateNotificationStatusRequest $request, DatabaseNotification $notification): JsonResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id, 404);

        match (NotificationReadStatus::from($request->validated('status'))) {
            NotificationReadStatus::Read => $notification->markAsRead(),
            NotificationReadStatus::Unread => $notification->markAsUnread(),
        };

        return $this->successResponse(
            new NotificationResource($notification->fresh()),
            'Notification updated successfully.'
        );
    }

    /**
     * Mark a batch of the authenticated user's notifications as read.
     */
    public function updateMany(UpdateNotificationsStatusRequest $request): JsonResponse
    {
        $request->user()->notifications()
            ->whereIn('id', $request->validated('ids'))
            ->get()
            ->each->markAsRead();

        return $this->successResponse(message: 'Notifications marked as read.');
    }
}
