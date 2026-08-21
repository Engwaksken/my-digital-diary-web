<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportAttachmentController extends Controller
{
    /**
     * Download or preview a support attachment for an authenticated user.
     *
     * The route only accepts a file name, never an arbitrary path. This blocks
     * path traversal attempts such as ../../.env while retaining compatibility
     * with support files saved by older versions of the application.
     */
    public function download(Request $request, string $attachment): StreamedResponse
    {
        abort_unless($request->user(), 401);

        $filename = basename(rawurldecode($attachment));

        abort_if($filename === '' || $filename === '.' || $filename === '..', 404);

        $candidates = [
            ['disk' => 'public', 'path' => 'support/attachments/'.$filename],
            ['disk' => 'public', 'path' => 'support-attachments/'.$filename],
            ['disk' => 'local', 'path' => 'support/attachments/'.$filename],
            ['disk' => 'local', 'path' => 'support-attachments/'.$filename],
        ];

        foreach ($candidates as $candidate) {
            $disk = Storage::disk($candidate['disk']);
            $path = $candidate['path'];

            if (! $disk->exists($path)) {
                continue;
            }

            $mime = $disk->mimeType($path) ?: 'application/octet-stream';
            $inline = in_array($mime, [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'text/plain',
            ], true);

            if ($inline) {
                return $disk->response($path, $filename, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
                    'X-Content-Type-Options' => 'nosniff',
                ]);
            }

            return $disk->download($path, $filename, [
                'Content-Type' => $mime,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        abort(404, 'Support attachment not found.');
    }
}
