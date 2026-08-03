<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

final class RobotsController extends Controller
{
    /**
     * robots.txt for search engines — references the absolute sitemap URL.
     *
     * Served from a route (not a static public/robots.txt file) so the
     * sitemap URL stays absolute and correct behind the production proxy.
     */
    public function __invoke(): Response
    {
        return response("User-agent: *\nAllow: /\n\nSitemap: ".url('/sitemap.xml')."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
