<?php

namespace App\Policies;

use App\Models\Rating;
use App\Models\Site;
use App\Models\User;

class RatingPolicy
{
    public function create(User $user, Site $site): bool
    {
        return ! $user->ratings()->where('site_id', $site->id)->exists();
    }

    public function update(User $user, Rating $rating): bool
    {
        return $user->id === $rating->user_id;
    }

    public function delete(User $user, Rating $rating): bool
    {
        return $user->id === $rating->user_id || $user->is_admin;
    }
}
