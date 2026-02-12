<?php

use App\Http\Controllers\Api\QueuePositionController;
use Illuminate\Support\Facades\Route;

Route::get('/queue-positions', QueuePositionController::class)->name('api.queue-positions');
