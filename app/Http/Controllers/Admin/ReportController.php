<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DismissReportRequest;
use App\Http\Requests\Admin\ReportQueueRequest;
use App\Http\Requests\Admin\ResolveReportRequest;
use App\Models\Report;
use App\Services\ReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(ReportQueueRequest $request): View
    {
        $status = $request->validated('status') ?? 'PENDING';
        $query = Report::query()
            ->with([
                'reporter:id',
                'reporter.profile:user_id,full_name',
                'reason:id,name',
                'listing:id,landlord_id,title,monthly_rent,occupancy_status,visibility_status,current_moderation_id,deleted_at',
                'listing.landlord:id,email,phone,account_status',
                'listing.landlord.profile:user_id,full_name',
                'listing.currentModeration:id,listing_id,status',
            ]);

        if ($status !== 'all') {
            $query->where('status', $status);
        } else {
            $query->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END");
        }

        $reports = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(10)->withQueryString();

        return view('admin.reports.index', compact('reports', 'status'));
    }

    public function show(Report $report): View
    {
        $report->load([
            'reporter:id,email,phone',
            'reporter.profile:user_id,full_name',
            'reason:id,code,name',
            'listing:id,landlord_id,category_id,current_moderation_id,title,description,monthly_rent,deposit_amount,area_m2,max_occupants,bedroom_count,bathroom_count,gender_requirement,ward_id,street_address,occupancy_status,visibility_status,expires_at,deleted_at',
            'listing.landlord:id,email,phone,account_status',
            'listing.landlord.profile:user_id,full_name,zalo_number',
            'listing.category:id,name',
            'listing.ward:id,name,district_id',
            'listing.ward.district:id,name,province_id',
            'listing.ward.district.province:id,name',
            'listing.currentModeration:id,listing_id,status',
            'listing.images:id,listing_id,image_url,is_cover,display_order',
            'listing.amenities:id,name',
            'listing.fees.feeType:id,name',
            'listing.fees.feeUnit:id,name',
            'enforcementActions' => fn ($query) => $query->limit(10)->with('admin:id')->with('admin.profile:user_id,full_name'),
        ]);

        return view('admin.reports.show', compact('report'));
    }

    public function dismiss(
        DismissReportRequest $request,
        Report $report,
        ReportService $reportService,
    ): RedirectResponse {
        $reportService->dismiss($report, $request->user(), $request->validated('resolution_reason'));

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('status', __('ui.reports.dismissed'));
    }

    public function resolve(
        ResolveReportRequest $request,
        Report $report,
        ReportService $reportService,
    ): RedirectResponse {
        $reportService->resolve(
            $report,
            $request->user(),
            $request->validated('action_type'),
            $request->validated('reason'),
        );

        return redirect()
            ->route('admin.reports.show', $report)
            ->with('status', __('ui.reports.resolved'));
    }
}
