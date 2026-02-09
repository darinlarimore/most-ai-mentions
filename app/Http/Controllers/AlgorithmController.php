<?php

namespace App\Http\Controllers;

use App\Services\HypeScoreCalculator;
use Inertia\Inertia;
use Inertia\Response;

class AlgorithmController extends Controller
{
    /**
     * Display the algorithm explanation page.
     */
    public function index(): Response
    {
        return Inertia::render('Algorithm/Index', [
            'factors' => HypeScoreCalculator::getAlgorithmExplanation(),
        ]);
    }
}
