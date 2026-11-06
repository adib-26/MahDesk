<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexNotificationsRequest;
use App\Http\Resources\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * In-app notifications for the authenticated user, across all organizations.
 */
class NotificationController extends Controller
{
    public function index(IndexNotificationsRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $query = $user->notifications()->getQuery()
            ->reorder()
            ->when($request->has('unread'), fn ($q) => $request->boolean('unread') ? $q->whereNull('read_at') : $q->whereNotNull('read_at'))
            ->when($request->filter('type'), fn ($q, $type) => $q->where('data->type', $type))
            ->when($request->filter('organization'), fn ($q, $slug) => $q->where('data->organization->slug', $slug));

        return NotificationResource::collection(
            $request->applySort($query)->paginate($request->perPage())->withQueryString(),
        )->additional(['meta' => ['unread_count' => $user->unreadNotifications()->count()]]);
    }

    public function markRead(Request $request, string $notification): NotificationResource
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return NotificationResource::make($item);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $updated = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['marked_read' => $updated]]);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->delete();

        return response()->json(null, 204);
    }
}
