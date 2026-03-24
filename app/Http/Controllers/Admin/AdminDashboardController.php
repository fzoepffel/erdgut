<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminDashboardMetrics;
use Illuminate\Contracts\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(
        private readonly AdminDashboardMetrics $metrics
    ) {}

    public function index(): View
    {
        return view('admin.dashboard', [
            'metrics' => $this->metrics->build(),
        ]);
    }
}
