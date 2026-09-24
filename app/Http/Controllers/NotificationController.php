<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $notifications = $user->appNotifications()->latest('created_at')->paginate(15);
        $unreadCount = $user->appNotifications()->whereNull('read_at')->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markRead(Request $request, AppNotification $notification): RedirectResponse
    {
        $ownedNotification = $request->user()
            ->appNotifications()
            ->whereKey($notification->id)
            ->firstOrFail();

        if ($ownedNotification->read_at === null) {
            $ownedNotification->forceFill(['read_at' => now()])->save();
        }

        return redirect()->route('notifications.index');
    }
}
