<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SignatureImageService
{
    public function store(?UploadedFile $file, ?string $dataUrl): string
    {
        if ($file) {
            return $file->store('signatures', 'public');
        }

        $dataUrl = trim((string) $dataUrl);
        if ($dataUrl === '') {
            throw ValidationException::withMessages([
                'signature' => 'Draw a signature with your finger/pen or upload a signature image.',
            ]);
        }

        if (!preg_match('#^data:image/(png|jpeg|jpg|webp);base64,(.+)$#i', $dataUrl, $m)) {
            throw ValidationException::withMessages([
                'drawn_signature' => 'The drawn signature image is invalid. Please clear the pad and sign again.',
            ]);
        }

        $bytes = base64_decode(str_replace(' ', '+', $m[2]), true);
        if ($bytes === false || strlen($bytes) < 50) {
            throw ValidationException::withMessages([
                'drawn_signature' => 'The drawn signature could not be read. Please sign again.',
            ]);
        }

        if (strlen($bytes) > 5 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'drawn_signature' => 'The drawn signature is too large.',
            ]);
        }

        $extension = strtolower($m[1]) === 'jpg' ? 'jpeg' : strtolower($m[1]);
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $path = 'signatures/signature_'.bin2hex(random_bytes(12)).'.'.$extension;
        Storage::disk('public')->put($path, $bytes);
        return $path;
    }
}
