<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Turns a recorded audio file into text via OpenAI's Whisper API — this
 * is the ONE piece of the meetings feature that genuinely can't work
 * without a real third-party credential, same situation as payment
 * gateways and calendar OAuth elsewhere in this app. There's no way to
 * fabricate actual speech-to-text without calling a real provider.
 *
 * Reuses the SAME key resolution as AiPlannerService (user's own key
 * first, else the site's shared default) but only when that key's
 * provider is specifically 'openai' — Whisper is an OpenAI-specific
 * endpoint, so an Anthropic or other custom-provider key can't be used
 * here even though it works fine for AI Planner's text generation.
 *
 * Speaker identification: OpenAI's base Whisper endpoint does NOT
 * identify separate speakers — it returns segments with timestamps, but
 * every segment's "speaker" comes back null here. True diarization needs
 * a different provider (e.g. AssemblyAI, Deepgram) that isn't wired up.
 * The transcript_segments schema has room for a speaker label so this
 * can be upgraded later without another schema change.
 */
class TranscriptionService
{
    /**
     * @return array{transcript: string, segments: array}
     * @throws RuntimeException
     */
    public function transcribe(User $user, string $audioPath): array
    {
        $apiKey = $this->resolveOpenAiKey($user);

        if (! $apiKey) {
            throw new RuntimeException(
                'Transcription needs an OpenAI API key (Whisper) — add one under "API Keys" with provider OpenAI, ' .
                'or ask your admin to set OpenAI as the default AI provider in Settings.'
            );
        }

        $fullPath = Storage::disk('public')->path($audioPath);
        if (! is_file($fullPath)) {
            throw new RuntimeException('The recorded audio file could not be found on disk.');
        }
        if (filesize($fullPath) < 1024) {
            throw new RuntimeException('The recording is empty or too short to transcribe. Record at least a few seconds and try again.');
        }

        [$uploadPath, $temporary] = $this->prepareForOpenAi($fullPath);

        try {
            $response = Http::withToken($apiKey)
                ->timeout(180)
                ->attach('file', file_get_contents($uploadPath), basename($uploadPath))
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => 'whisper-1',
                    'response_format' => 'verbose_json',
                    'timestamp_granularities[]' => 'segment',
                ]);
        } finally {
            if ($temporary && is_file($uploadPath)) {
                @unlink($uploadPath);
            }
        }

        if ($response->failed()) {
            throw new RuntimeException('OpenAI transcription error: ' . ($response->json('error.message') ?? $response->body()));
        }

        $data = $response->json();
        $segments = collect($data['segments'] ?? [])->map(fn ($segment) => [
            'start_seconds' => round($segment['start'] ?? 0, 1),
            'end_seconds' => round($segment['end'] ?? 0, 1),
            'speaker' => null, // see class docblock — not available from this endpoint
            'text' => trim($segment['text'] ?? ''),
        ])->values()->all();

        return [
            'transcript' => (string) ($data['text'] ?? ''),
            'segments' => $segments,
        ];
    }

    /**
     * OpenAI accepts a defined set of recording containers. Keep valid files
     * unchanged, repair generic/.bin filenames by MIME detection, and use
     * ffmpeg when the source is another real recorder format (AMR, WMA, AAC,
     * MOV, MKV, etc.). This lets uploads remain broad without sending an
     * unsupported filename/container to the transcription endpoint.
     *
     * @return array{0:string,1:bool} path, isTemporary
     */
    private function prepareForOpenAi(string $fullPath): array
    {
        $supported = ['flac','m4a','mp3','mp4','mpeg','mpga','oga','ogg','wav','webm'];
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        if (in_array($extension, $supported, true)) {
            return [$fullPath, false];
        }

        $mime = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($fullPath));
        $mimeExtensions = [
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/mp4' => 'm4a',
            'video/mp4' => 'mp4',
            'audio/x-m4a' => 'm4a',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/webm' => 'webm',
            'video/webm' => 'webm',
            'audio/ogg' => 'ogg',
            'application/ogg' => 'ogg',
            'audio/flac' => 'flac',
            'audio/x-flac' => 'flac',
        ];

        if (isset($mimeExtensions[$mime])) {
            $target = tempnam(sys_get_temp_dir(), 'meeting_audio_');
            if ($target === false) {
                throw new RuntimeException('Could not prepare the recording for transcription.');
            }
            $renamed = $target . '.' . $mimeExtensions[$mime];
            @unlink($target);
            if (! copy($fullPath, $renamed)) {
                throw new RuntimeException('Could not prepare the recording for transcription.');
            }
            return [$renamed, true];
        }

        $ffmpeg = trim((string) @shell_exec('command -v ffmpeg 2>/dev/null'));
        if ($ffmpeg !== '') {
            $target = tempnam(sys_get_temp_dir(), 'meeting_audio_');
            if ($target === false) {
                throw new RuntimeException('Could not prepare the recording for transcription.');
            }
            $mp3 = $target . '.mp3';
            @unlink($target);
            $command = escapeshellarg($ffmpeg) . ' -y -i ' . escapeshellarg($fullPath)
                . ' -vn -ac 1 -ar 16000 -b:a 64k ' . escapeshellarg($mp3) . ' 2>&1';
            @exec($command, $output, $exitCode);
            if ($exitCode === 0 && is_file($mp3) && filesize($mp3) >= 1024) {
                return [$mp3, true];
            }
            @unlink($mp3);
        }

        throw new RuntimeException(
            'This recording format was uploaded successfully but must be converted before transcription. ' .
            'Use MP3, M4A, WAV, WebM, MP4, MPEG, OGG or FLAC, or enable ffmpeg on the server for automatic conversion.'
        );
    }

    private function resolveOpenAiKey(User $user): ?string
    {
        $credential = $user->activeApiCredential();
        if ($credential && $credential->provider === 'openai') {
            return $credential->api_key;
        }

        $settings = SiteSetting::current();
        if ($settings->hasDefaultAiKey() && $settings->default_ai_provider === 'openai') {
            return $settings->default_ai_api_key;
        }

        return null;
    }
}
