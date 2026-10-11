<?php

namespace App\Http\Controllers\Field;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;

/**
 * Serves the field application's service worker from the site root so it can
 * control /field. It precaches the built assets listed in the Vite manifest
 * and caches only the /field shell; no other page or API response is cached,
 * so administrative pages with personal data never reach the device cache.
 */
class ServiceWorkerController extends Controller
{
    public function __invoke(): Response
    {
        $manifestPath = public_path('build/manifest.json');
        $assets = [];
        $version = 'dev';

        if (File::exists($manifestPath)) {
            $contents = File::get($manifestPath);
            $version = substr(hash('sha256', $contents), 0, 16);

            foreach (json_decode($contents, true) ?? [] as $chunk) {
                $assets[] = '/build/'.$chunk['file'];

                foreach ($chunk['css'] ?? [] as $css) {
                    $assets[] = '/build/'.$css;
                }
            }
        }

        $script = view('field.service-worker', [
            'version' => $version,
            'assets' => array_values(array_unique($assets)),
        ])->render();

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
