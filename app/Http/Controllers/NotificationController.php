<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Intl\Exception\NotImplementedException;

class NotificationController extends Controller
{
    /**
     * Return notifications which are not
     * older than 7 days
     *
     * @return array
     */
    public function index()
    {
        /** @var  User */
        $user = Auth::user();
        $beforeOneWeek = Carbon::now()->subWeek()->toDateString();

        return $user->notifications->where('created_at', '>', $beforeOneWeek)->sortByDesc('created_at')->toArray();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @throws NotImplementedException
     * @return void
     */
    public function store(Request $request)
    {
        throw new NotImplementedException('Notification store actions isn\'t implemented yet.');
    }

    /**
     * Show the specified resource in storage.
     *
     * @param  int  $id
     *
     * @return DatabaseNotification|null
     */
    public function show($id)
    {
        /** @var  User */
        $user = Auth::user();

        return $user->notifications->where('id', $id)->first();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     *
     * @return void
     */
    public function update(Request $request, $id)
    {
        /** @var  User */
        $user = Auth::user();

        /** @var  DatabaseNotificationCollection */
        $notifications = $user->notifications->where('id', $id);

        $notifications->markAsRead();
    }
}
