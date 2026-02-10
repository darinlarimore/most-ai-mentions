<?php

test('returns a successful response', function () {
    $response = $this->get(route('leaderboard'));

    $response->assertOk();
});
