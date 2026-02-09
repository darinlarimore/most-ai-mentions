<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public channels for real-time updates (no auth required)
Broadcast::channel('crawl-activity', function () {
    return true;
});

Broadcast::channel('crawl-queue', function () {
    return true;
});

Broadcast::channel('leaderboard', function () {
    return true;
});
