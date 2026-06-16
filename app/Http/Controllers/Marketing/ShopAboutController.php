<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Producer;
use Illuminate\Contracts\View\View;

class ShopAboutController extends Controller
{
    /**
     * Show the public "About Erdgut" page for the shop side.
     *
     * Mirrors the editorial almanac treatment of the box About page
     * (public.about) but revamped to present Erdgut and its shop rather
     * than the weekly subscription box. The box About page stays at /about.
     */
    public function __invoke(): View
    {
        // Feature a real producer (with an image) for the "A small circle of
        // hands" section. Falls back to a placeholder in the view when the
        // catalogue has no producer image yet.
        $featuredProducer = Producer::query()
            ->with('translations')
            ->where(function ($query): void {
                $query->where(function ($q): void {
                    $q->whereNotNull('image_path')->where('image_path', '!=', '');
                })->orWhere(function ($q): void {
                    $q->whereNotNull('image_url')->where('image_url', '!=', '');
                });
            })
            ->inRandomOrder()
            ->first();

        return view('marketing.shop-about', [
            'featuredProducer' => $featuredProducer,
        ]);
    }
}
