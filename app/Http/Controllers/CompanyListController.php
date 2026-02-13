<?php

namespace App\Http\Controllers;

use App\Models\CompanyList;
use Inertia\Inertia;
use Inertia\Response;

class CompanyListController extends Controller
{
    public function show(CompanyList $list): Response
    {
        $sites = $list->matchedSites()
            ->with('latestCrawlResult')
            ->orderByDesc('hype_score')
            ->paginate(24);

        return Inertia::render('CompanyLists/Show', [
            'list' => $list,
            'sites' => $sites,
            'totalCompanies' => $list->entries()->count(),
            'matchedCount' => $list->matchedSites()->count(),
        ]);
    }
}
