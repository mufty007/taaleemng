<?php

namespace App\Http\Controllers\Marketing;

use App\Enums\DemoRequestKind;
use App\Http\Controllers\Controller;
use App\Support\Marketing\LandingContent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class MarketingController extends Controller
{
    public function home(): View
    {
        return view('marketing.home', [
            'modules' => LandingContent::modules(),
            'moduleGroups' => LandingContent::moduleGroups(),
            'fits' => LandingContent::fits(),
            'spotlight' => LandingContent::spotlight(),
            'reportRows' => LandingContent::reportRows(),
            'invoices' => LandingContent::invoices(),
            'payroll' => LandingContent::payroll(),
            'attendanceWeek' => LandingContent::attendanceWeek(),
            'roles' => LandingContent::roles(),
            'steps' => LandingContent::steps(),
            'plans' => LandingContent::plans(),
            'faqs' => LandingContent::faqs(),
        ]);
    }

    public function contact(Request $request): View
    {
        $kind = DemoRequestKind::tryFrom((string) $request->query('kind')) ?? DemoRequestKind::Demo;

        return view('marketing.contact', [
            'kinds' => DemoRequestKind::cases(),
            'selectedKind' => old('kind', $kind->value),
            'studentCounts' => ['Under 50', '50–150', '150–400', '400+'],
        ]);
    }

    public function legal(string $page): View
    {
        return view('marketing.legal', ['page' => $page]);
    }
}
