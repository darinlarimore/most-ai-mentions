<?php

use App\Http\Controllers\AlgorithmController;
use App\Http\Controllers\CrawlQueueController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\InsightsController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Sitemap
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Public routes
Route::get('/', [LeaderboardController::class, 'index'])->name('leaderboard');
Route::get('/user-rated', [LeaderboardController::class, 'userRated'])->name('leaderboard.user-rated');
Route::get('/sites/{site}', [SiteController::class, 'show'])->name('sites.show');
Route::get('/algorithm', [AlgorithmController::class, 'index'])->name('algorithm');
Route::get('/insights', [InsightsController::class, 'index'])->name('insights');
Route::get('/insights/stats', [InsightsController::class, 'stats'])->name('insights.stats');
Route::get('/insights/network', [InsightsController::class, 'network'])->name('insights.network');
Route::get('/insights/charts', [InsightsController::class, 'charts'])->name('insights.charts');
Route::get('/crawl/queue', [CrawlQueueController::class, 'index'])->name('crawl.queue');
Route::get('/crawl/live', [CrawlQueueController::class, 'live'])->name('crawl.live');
Route::get('/donate', [DonationController::class, 'index'])->name('donate');
Route::get('/donate/success', [DonationController::class, 'success'])->name('donate.success');

// Newsletter (public)
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

// Site submission (public)
Route::get('/submit', [SiteController::class, 'create'])->name('sites.create');
Route::post('/submit', [SiteController::class, 'store'])->name('sites.store');
Route::post('/submit/batch', [SiteController::class, 'storeBatch'])->name('sites.store-batch');
Route::post('/donate/session', [DonationController::class, 'createSession'])->name('donate.session');

// Rating (public, rate limited)
Route::post('/sites/{site}/rate', [RatingController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('sites.rate');

// Stripe webhook (no CSRF)
Route::post('/stripe/webhook', [DonationController::class, 'webhook'])->name('stripe.webhook');

require __DIR__.'/settings.php';
