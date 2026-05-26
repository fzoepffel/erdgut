<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * @param  bool  $hideAppFooter  Opt-out for pages that render their own
     *                               footer (e.g. the dashboard's editorial
     *                               green band). When true, the default
     *                               <x-app-footer> is suppressed in the
     *                               layout.
     */
    public function __construct(
        public bool $hideAppFooter = false,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.app');
    }
}
