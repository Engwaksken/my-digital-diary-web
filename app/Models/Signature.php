<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Signature extends Model
{
    protected $fillable = ['user_id', 'label', 'file_path'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Return the saved signature as an inline data URI for web previews.
     *
     * This deliberately does not depend on the public /storage symlink,
     * which means signatures still display on hosts where the file exists
     * in storage/app/public but direct public-disk URLs are blocked or 404.
     */
    public function dataUri(): ?string
    {
        if (! $this->file_path) {
            return null;
        }

        try {
            $disk = Storage::disk('public');

            if (! $disk->exists($this->file_path)) {
                return null;
            }

            $contents = $disk->get($this->file_path);
            $mime = $disk->mimeType($this->file_path) ?: 'image/png';

            return 'data:' . $mime . ';base64,' . base64_encode($contents);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

    public function displayLabel(): string
    {
        return $this->label ?: 'Signature #' . $this->id;
    }
}
