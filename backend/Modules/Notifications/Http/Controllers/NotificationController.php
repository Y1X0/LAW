<?php

namespace Modules\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Notifications\Models\Notification;

/**
 * إشعارات المستخدم الحالي (Notifications / Phase 8 · PR-1) — قراءة ذاتية وتعليم كمقروء.
 * كل النقاط محروسة بـ notifications.view_own، ومنطوقة على `user_id` للمستخدم الحالي —
 * لا يرى/يعدّل أحدٌ إشعارات غيره (إشعار الغير يُعاد 404، لا يُكشف وجوده).
 */
class NotificationController
{
    /** GET /api/notifications — إشعارات المستخدم (الأحدث أولاً) مع عدّاد غير المقروء في meta. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'unread' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer'],
        ]);

        $userId = $request->user()->id;
        $query = Notification::query()->where('user_id', $userId)->latest('id');

        if ($request->boolean('unread')) {
            $query->unread();
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 20), 100));
        $page = $query->paginate($perPage);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Notification $n) => $this->present($n))->all(),
            'meta' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
                'unread' => $this->unreadFor($userId),
            ],
            'errors' => null,
        ]);
    }

    /** GET /api/notifications/unread-count — عدّاد غير المقروء فقط (لتغذية الجرس). */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['unread' => $this->unreadFor($request->user()->id)],
            'meta' => null,
            'errors' => null,
        ]);
    }

    /** PATCH /api/notifications/{notification}/read — تعليم إشعار المستخدم كمقروء (idempotent). */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 404);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json(['data' => $this->present($notification), 'meta' => null, 'errors' => null]);
    }

    /** PATCH /api/notifications/read-all — تعليم كل إشعارات المستخدم غير المقروءة كمقروءة. */
    public function markAllRead(Request $request): JsonResponse
    {
        $affected = Notification::query()
            ->where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['data' => ['marked' => $affected], 'meta' => null, 'errors' => null]);
    }

    private function unreadFor(int $userId): int
    {
        return Notification::query()->where('user_id', $userId)->unread()->count();
    }

    private function present(Notification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'related_type' => $n->related_type,
            'related_id' => $n->related_id,
            'read_at' => optional($n->read_at)->toIso8601String(),
            'created_at' => optional($n->created_at)->toIso8601String(),
        ];
    }
}
