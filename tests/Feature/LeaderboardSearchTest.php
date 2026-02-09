<?php

use App\Models\Site;

it('displays all active sites when no search query', function () {
    Site::factory()->count(3)->create(['status' => 'completed', 'is_active' => true]);

    $this->get('/')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Leaderboard/Index')
            ->has('sites.data', 3)
        );
});

it('filters sites by name', function () {
    Site::factory()->create(['name' => 'OpenAI', 'is_active' => true]);
    Site::factory()->create(['name' => 'Vercel', 'is_active' => true]);

    $this->get('/?search=openai')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
            ->where('sites.data.0.name', 'OpenAI')
            ->where('search', 'openai')
        );
});

it('filters sites by domain', function () {
    Site::factory()->create(['domain' => 'anthropic.com', 'is_active' => true]);
    Site::factory()->create(['domain' => 'vercel.com', 'is_active' => true]);

    $this->get('/?search=anthropic')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 1)
            ->where('sites.data.0.domain', 'anthropic.com')
        );
});

it('returns empty results for non-matching search', function () {
    Site::factory()->create(['name' => 'OpenAI', 'is_active' => true]);

    $this->get('/?search=nonexistent')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('sites.data', 0)
        );
});
