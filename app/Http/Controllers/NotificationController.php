<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The list behind the topbar bell. Every staff member reads only their own
 * notifications; nothing here takes a capability beyond being signed in.
 */
class NotificationController extends Controller
{
    private const LIMIT = 20;

    /**
     * Title and where a tap should take you, by notification type.
     *
     * @var array<string, array{0: string, 1: string|null}>
     */
    private const TYPES = [
        'integration_failing' => ['System health', '/app/system'],
        'order_voided' => ['Order voided', '/app/orders'],
        'staff_account_locked' => ['Account locked', '/manager/overview'],
        'payment_disputed' => ['Payment disputed', '/app/reports'],
    ];

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);

        $items = $user->notifications()
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->present($notification))
            ->values()
            ->all();

        return response()->json([
            'notifications' => $items,
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->user($request)
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail()
            ->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $this->user($request)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /**
     * @return array{id: string, type: string, title: string, message: string, url: string|null, read: bool, created_at: string|null}
     */
    private function present(DatabaseNotification $notification): array
    {
        /** @var array<string, mixed> $data */
        $data = (array) $notification->data;
        $type = (string) ($data['type'] ?? 'notice');

        [$title, $url] = self::TYPES[$type] ?? ['Notice', null];

        return [
            'id' => (string) $notification->id,
            'type' => $type,
            'title' => (string) ($data['title'] ?? $title),
            'message' => (string) ($data['message'] ?? ''),
            'url' => $url,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
