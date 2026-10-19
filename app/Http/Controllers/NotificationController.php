<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $link = $notification->data['link'] ?? null;

        return $link ? redirect($link) : back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'notifications-read');
    }

    /**
     * Remove one of the user's notifications from their history.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return back()->with('status', 'notification-deleted');
    }

    /**
     * Clear the user's whole notification history.
     */
    public function destroyAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back()->with('status', 'notifications-cleared');
    }
}
