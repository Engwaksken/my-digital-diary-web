<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class SocialMediaPost extends Model
{
    protected $fillable = [
        'user_id', 'campaign_id', 'title', 'caption', 'hashtags',
        'media_type', 'media_path', 'platforms', 'platform_content',
        'scheduled_at', 'published_at', 'status', 'approval_status',
        'posting_mode', 'last_error', 'publishing_results',
        'reminder_sent_at', 'posting_started_at', 'posting_notification_sent_at',
    ];

    protected $casts = [
        'platforms' => 'array',
        'platform_content' => 'array',
        'publishing_results' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
        'posting_started_at' => 'datetime',
        'posting_notification_sent_at' => 'datetime',
        'posting_attempts' => 'integer',
        'next_posting_attempt_at' => 'datetime',
    ];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(SocialMediaCampaign::class, 'campaign_id'); }
    public function metrics(): HasMany { return $this->hasMany(SocialMediaPostMetric::class, 'social_media_post_id'); }
    public function metricSnapshots(): HasMany { return $this->hasMany(SocialMediaMetricSnapshot::class, 'social_media_post_id'); }

    public function attachedLink(): ?string
    {
        $url = trim((string) data_get($this->platform_content, 'link_url', ''));
        return $url !== '' ? $url : null;
    }

    public function publicMediaUrl(): ?string
    {
        $path = trim((string) $this->media_path);
        if ($path === '') return null;
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) return $path;
        $url = Storage::disk('public')->url($path);
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }

    public function callToAction(): ?string
    {
        $value = trim((string) data_get($this->platform_content, 'call_to_action', ''));
        return $value !== '' ? $value : null;
    }

    /**
     * Canonical full post text used by manual sharing and every automatic
     * publisher. Keep composition here so every platform fetches the same
     * title, caption, CTA, hashtags and attached link.
     */
    public function shareText(): string
    {
        return collect([
            trim((string) $this->title),
            trim((string) $this->caption),
            $this->callToAction(),
            $this->attachedLink(),
            trim((string) $this->hashtags),
        ])->filter(fn ($value) => trim((string) $value) !== '')
          ->implode("\n\n");
    }

    public function fullPostPayload(): array
    {
        return [
            // Media is intentionally first in the payload so web/mobile
            // previews and publishers can render the visual before the copy.
            'media_type' => $this->media_type,
            'media_url' => $this->publicMediaUrl(),
            'title' => trim((string) $this->title),
            'caption' => trim((string) $this->caption),
            'call_to_action' => $this->callToAction(),
            'link_url' => $this->attachedLink(),
            'hashtags' => trim((string) $this->hashtags),
            'text' => $this->shareText(),
        ];
    }
}
