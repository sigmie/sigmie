<?php

declare(strict_types=1);

namespace App\Http\Controllers\Notifications;

use App\Events\Notifications\NotificationWasRead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class NotificationController extends \App\Http\Controllers\Controller
{
    public function index()
    {
        $beforeOneWeek = Carbon::now()->subWeek()->toDateString();

        return Auth::user()
            ->notifications
            ->where('created_at', '>', $beforeOneWeek)
            ->sortByDesc('created_at')
            ->toArray();
    }

    public function show($id)
    {
        return Auth::user()->notifications->where('id', $id)->first();
    }

    public function update($id)
    {
        $user = Auth::user();

        $user->notifications->firstWhere('id', $id)->markAsRead();

        event(new NotificationWasRead($user->id));
    }
}
