<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\StoreReportRequest;
use App\Models\Listing;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;

class ReportController extends Controller
{
    public function store(StoreReportRequest $request, Listing $listing, ReportService $reportService): RedirectResponse
    {
        $data = $request->validated();
        $reportService->submit(
            $request->user(),
            (int) $listing->id,
            (int) $data['reason_id'],
            $data['description'] ?? null,
        );

        return redirect()
            ->route('public.listings.show', $listing)
            ->with('status', __('ui.reports.submitted'));
    }
}
