<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Get notifications for the current user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $perPage = $request->get('per_page', 20);

            $query = $user->notifications();

            // Filter by read status
            if ($request->has('unread_only') && $request->boolean('unread_only')) {
                $query = $user->unreadNotifications();
            }

            $notifications = $query->latest()->paginate($perPage);

            $data = collect($notifications->items())->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => class_basename($notification->type),
                    'data' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ];
            });

            return $this->success([
                'notifications' => $data,
                'meta' => [
                    'current_page' => $notifications->currentPage(),
                    'last_page' => $notifications->lastPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                ],
            ], 'Notifications retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching notifications: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch notifications', 500);
        }
    }

    /**
     * Get unread notification count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        try {
            $count = $request->user()->unreadNotifications()->count();

            return $this->success([
                'count' => $count,
            ], 'Unread count retrieved successfully');
        } catch (\Exception $e) {
            Log::error('Error fetching unread count: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to fetch unread count', 500);
        }
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        try {
            $notification = $request->user()
                ->notifications()
                ->where('id', $id)
                ->first();

            if (!$notification) {
                return $this->error('Notification not found', 404);
            }

            $notification->markAsRead();

            return $this->success([
                'id' => $notification->id,
                'read_at' => $notification->read_at,
            ], 'Notification marked as read');
        } catch (\Exception $e) {
            Log::error('Error marking notification as read: ' . $e->getMessage(), [
                'notification_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to mark notification as read', 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {
            $request->user()->unreadNotifications->markAsRead();

            return $this->success(null, 'All notifications marked as read');
        } catch (\Exception $e) {
            Log::error('Error marking all notifications as read: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to mark notifications as read', 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $notification = $request->user()
                ->notifications()
                ->where('id', $id)
                ->first();

            if (!$notification) {
                return $this->error('Notification not found', 404);
            }

            $notification->delete();

            return $this->success(null, 'Notification deleted');
        } catch (\Exception $e) {
            Log::error('Error deleting notification: ' . $e->getMessage(), [
                'notification_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to delete notification', 500);
        }
    }

    /**
     * Delete all read notifications
     */
    public function deleteAllRead(Request $request): JsonResponse
    {
        try {
            $request->user()
                ->notifications()
                ->whereNotNull('read_at')
                ->delete();

            return $this->success(null, 'All read notifications deleted');
        } catch (\Exception $e) {
            Log::error('Error deleting read notifications: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return $this->error('Failed to delete notifications', 500);
        }
    }
}
