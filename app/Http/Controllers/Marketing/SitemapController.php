<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Producer;
use App\Models\Product;
use App\Support\EditorialContent;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * XML sitemap of every public, index-worthy page: the shop front door,
 * about/contact, the B2B landing page, the open catalogue (products and
 * producers) and the editorial recipes. Private surfaces (/app, /auth,
 * checkout) are excluded here and disallowed in robots.txt. Cached for an
 * hour so crawlers never trigger the catalogue queries repeatedly.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function (): string {
            $urls = [];
            $add = function (string $loc, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.5') use (&$urls): void {
                $urls[] = compact('loc', 'lastmod', 'changefreq', 'priority');
            };

            $add(route('public.home'), null, 'daily', '1.0');
            $add(route('public.shop.index'), null, 'daily', '0.9');
            $add(route('public.products.index'), null, 'daily', '0.9');
            $add(route('public.shop-about'), null, 'monthly', '0.7');
            $add(route('public.producers'), null, 'weekly', '0.7');
            $add(route('restaurant.landing'), null, 'monthly', '0.7');
            $add(route('public.shop-contact'), null, 'monthly', '0.4');
            $add(route('public.terms'), null, 'yearly', '0.2');
            $add(route('public.privacy'), null, 'yearly', '0.2');
            $add(route('public.imprint'), null, 'yearly', '0.2');

            Product::query()->visibleInCatalog()->get()->each(function (Product $product) use ($add): void {
                $add(route('public.products.show', $product), $product->updated_at?->toDateString(), 'weekly', '0.8');
            });

            Producer::query()->whereNotNull('slug')->get()->each(function (Producer $producer) use ($add): void {
                $add(route('public.producers.show', $producer), $producer->updated_at?->toDateString(), 'monthly', '0.6');
            });

            foreach (EditorialContent::recipeSlugs() as $slug) {
                $add(route('public.recipes.show', ['slug' => $slug]), null, 'monthly', '0.4');
            }

            $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];
            foreach ($urls as $url) {
                $lines[] = '  <url>';
                $lines[] = '    <loc>'.e($url['loc']).'</loc>';
                if ($url['lastmod'] !== null) {
                    $lines[] = '    <lastmod>'.$url['lastmod'].'</lastmod>';
                }
                $lines[] = '    <changefreq>'.$url['changefreq'].'</changefreq>';
                $lines[] = '    <priority>'.$url['priority'].'</priority>';
                $lines[] = '  </url>';
            }
            $lines[] = '</urlset>';

            return implode("\n", $lines);
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
