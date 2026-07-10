<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardMetrics;
use App\Services\Admin\AdminOperationalAlerts;
use App\Services\Admin\AdminOperationsMetrics;
use Illuminate\Contracts\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardMetrics $metrics,
        private readonly AdminOperationsMetrics $operationsMetrics,
        private readonly AdminOperationalAlerts $operationalAlerts
    ) {}

    public function index(): View
    {
        return view('admin.dashboard', [
            'metrics' => $this->metrics->build(),
            'ops' => $this->operationsMetrics->snapshot(),
            'operationalAlerts' => $this->operationalAlerts->build(),
        ]);
    }
}
