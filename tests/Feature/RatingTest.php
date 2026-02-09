<?php

use App\Models\Rating;
use App\Models\Site;

it('allows unauthenticated users to rate a site', function () {
    $site = Site::factory()->create();

    $this->post("/sites/{$site->id}/rate", [
        'score' => 4,
        'comment' => 'Pretty hyped!',
    ])->assertRedirect();

    $this->assertDatabaseHas('ratings', [
        'site_id' => $site->id,
        'score' => 4,
        'comment' => 'Pretty hyped!',
        'user_id' => null,
    ]);

    $site->refresh();
    expect($site->user_rating_avg)->toBe(4.0);
    expect($site->user_rating_count)->toBe(1);
});

it('updates existing rating from the same IP', function () {
    $site = Site::factory()->create();

    $this->post("/sites/{$site->id}/rate", ['score' => 3]);
    $this->post("/sites/{$site->id}/rate", ['score' => 5]);

    expect(Rating::where('site_id', $site->id)->count())->toBe(1);
    expect(Rating::where('site_id', $site->id)->first()->score)->toBe(5);
});

it('validates score is between 1 and 5', function () {
    $site = Site::factory()->create();

    $this->post("/sites/{$site->id}/rate", ['score' => 0])
        ->assertSessionHasErrors('score');

    $this->post("/sites/{$site->id}/rate", ['score' => 6])
        ->assertSessionHasErrors('score');
});

it('stores user_id when authenticated', function () {
    $site = Site::factory()->create();
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user)
        ->post("/sites/{$site->id}/rate", ['score' => 5]);

    $this->assertDatabaseHas('ratings', [
        'site_id' => $site->id,
        'user_id' => $user->id,
        'score' => 5,
    ]);
});
