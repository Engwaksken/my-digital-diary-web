<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class BrandingAssetController extends Controller
{
    public function show(string $asset): Response
    {
        abort_unless(in_array($asset, ['logo', 'favicon'], true), 404);

        $settings = SiteSetting::current();
        $path = $asset === 'logo' ? $settings->logo_path : $settings->favicon_path;

        abort_if(! $path || ! Storage::disk('public')->exists($path), 404);

        $disk = Storage::disk('public');
        $mime = $disk->mimeType($path) ?: ($asset === 'favicon' ? 'image/x-icon' : 'image/png');

        return response($disk->get($path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'public, max-age=86400, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
