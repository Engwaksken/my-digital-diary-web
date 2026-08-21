<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * High-quality meeting transcription with explicit language hints.
 *
 * Preferred order:
 *  1. gpt-transcribe (best current general-purpose transcription)
 *  2. gpt-4o-transcribe
 *  3. whisper-1 fallback for older accounts / timestamped segments
 *
 * Language choices exposed by My Digital Diary:
 *  - en-GB => British/UK English (API hint: en)
 *  - lg    => Luganda
 *  - sw    => Kiswahili
 *  - auto  => no forced language hint
 */
class TranscriptionService
{
    /** @return array{transcript:string,segments:array,model:string,language:string} */
    public function transcribe(User $user, string $audioPath, string $language = 'auto'): array
    {
        $apiKey = $this->resolveOpenAiKey($user);

        if (! $apiKey) {
            throw new RuntimeException(
                'Transcription needs an OpenAI API key — add one under "API Keys" with provider OpenAI, ' .
                'or ask your admin to configure OpenAI as the default AI provider.'
            );
        }

        $language = $this->normaliseLanguageChoice($language);
        $fullPath = Storage::disk('public')->path($audioPath);

        if (! is_file($fullPath)) {
            throw new RuntimeException('The recorded audio file could not be found on disk.');
        }
        if (filesize($fullPath) < 1024) {
            throw new RuntimeException('The recording is empty or too short to transcribe. Record at least a few seconds and try again.');
        }

        [$uploadPath, $temporary] = $this->prepareForOpenAi($fullPath);

        try {
            $errors = [];

            // Current recommended general-purpose model. It supports expected
            // languages + context, which materially helps accents and local terms.
            $response = $this->requestGptTranscribe($apiKey, $uploadPath, $language);
            if ($response->successful()) {
                return $this->normaliseTextResponse($response->json(), 'gpt-transcribe', $language);
            }
            $errors[] = 'gpt-transcribe: ' . $this->responseError($response);

            // Compatible high-accuracy fallback for accounts where gpt-transcribe
            // is unavailable. Uses the singular ISO-639-1 language hint.
            $response = $this->requestGpt4oTranscribe($apiKey, $uploadPath, $language);
            if ($response->successful()) {
                return $this->normaliseTextResponse($response->json(), 'gpt-4o-transcribe', $language);
            }
            $errors[] = 'gpt-4o-transcribe: ' . $this->responseError($response);

            // Last fallback preserves Whisper timestamp segments.
            $response = $this->requestWhisper($apiKey, $uploadPath, $language);
            if ($response->successful()) {
                return $this->normaliseWhisperResponse($response->json(), $language);
            }
            $errors[] = 'whisper-1: ' . $this->responseError($response);

            throw new RuntimeException('OpenAI transcription failed. ' . implode(' | ', $errors));
        } finally {
            if ($temporary && is_file($uploadPath)) {
                @unlink($uploadPath);
            }
        }
    }

    private function requestGptTranscribe(string $apiKey, string $uploadPath, string $language): Response
    {
        $fields = [
            'model' => 'gpt-transcribe',
            'response_format' => 'json',
            'prompt' => $this->promptFor($language),
        ];

        if (($code = $this->apiLanguageCode($language)) !== null) {
            $fields['languages[]'] = $code;
        }

        foreach ($this->keywordsFor($language) as $index => $keyword) {
            $fields["keywords[{$index}]"] = $keyword;
        }

        return Http::withToken($apiKey)
            ->timeout(240)
            ->attach('file', file_get_contents($uploadPath), basename($uploadPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', $fields);
    }

    private function requestGpt4oTranscribe(string $apiKey, string $uploadPath, string $language): Response
    {
        $fields = [
            'model' => 'gpt-4o-transcribe',
            'response_format' => 'json',
            'prompt' => $this->promptFor($language),
        ];

        if (($code = $this->apiLanguageCode($language)) !== null) {
            $fields['language'] = $code;
        }

        return Http::withToken($apiKey)
            ->timeout(240)
            ->attach('file', file_get_contents($uploadPath), basename($uploadPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', $fields);
    }

    private function requestWhisper(string $apiKey, string $uploadPath, string $language): Response
    {
        $fields = [
            'model' => 'whisper-1',
            'response_format' => 'verbose_json',
            'timestamp_granularities[]' => 'segment',
            'temperature' => 0,
            'prompt' => $this->promptFor($language),
        ];

        if (($code = $this->apiLanguageCode($language)) !== null) {
            $fields['language'] = $code;
        }

        return Http::withToken($apiKey)
            ->timeout(240)
            ->attach('file', file_get_contents($uploadPath), basename($uploadPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', $fields);
    }

    /** @return array{transcript:string,segments:array,model:string,language:string} */
    private function normaliseTextResponse(array $data, string $model, string $language): array
    {
        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '') {
            throw new RuntimeException('The transcription provider returned an empty transcript. Check microphone volume and background noise, then retry.');
        }

        // gpt-transcribe / gpt-4o-transcribe return high-quality text rather
        // than Whisper-style timestamp segments. Keep the existing UI contract
        // by exposing the complete transcript as one safe segment.
        return [
            'transcript' => $text,
            'segments' => [[
                'start_seconds' => 0.0,
                'end_seconds' => 0.0,
                'speaker' => null,
                'text' => $text,
            ]],
            'model' => $model,
            'language' => $language,
        ];
    }

    /** @return array{transcript:string,segments:array,model:string,language:string} */
    private function normaliseWhisperResponse(array $data, string $language): array
    {
        $text = trim((string) ($data['text'] ?? ''));
        $segments = collect($data['segments'] ?? [])->map(fn ($segment) => [
            'start_seconds' => round((float) ($segment['start'] ?? 0), 1),
            'end_seconds' => round((float) ($segment['end'] ?? 0), 1),
            'speaker' => null,
            'text' => trim((string) ($segment['text'] ?? '')),
        ])->filter(fn ($segment) => $segment['text'] !== '')->values()->all();

        if ($text === '') {
            throw new RuntimeException('The transcription provider returned an empty transcript. Check microphone volume and background noise, then retry.');
        }

        return [
            'transcript' => $text,
            'segments' => $segments,
            'model' => 'whisper-1',
            'language' => $language,
        ];
    }

    private function normaliseLanguageChoice(string $language): string
    {
        $language = trim($language);
        return in_array($language, ['auto', 'en-GB', 'lg', 'sw'], true) ? $language : 'auto';
    }

    private function apiLanguageCode(string $language): ?string
    {
        return match ($language) {
            'en-GB' => 'en',
            'lg' => 'lg',
            'sw' => 'sw',
            default => null,
        };
    }

    private function promptFor(string $language): string
    {
        return match ($language) {
            'en-GB' => 'A professional meeting in British English. Preserve names, numbers, dates, amounts, acronyms, project names and action items accurately. Use UK English spelling and punctuation. Do not invent words that were not spoken.',
            'lg' => 'Olukiiko mu Luganda. Wandiika ebigambo ebyogeddwa nga bwe biri, amannya, ennamba, ennaku z\'omwezi, ssente n\'amannya ga pulogulaamu nga bituufu. Tokola bigambo bitayogeddwa.',
            'sw' => 'Mkutano wa Kiswahili. Andika maneno yaliyosemwa kwa usahihi, pamoja na majina, nambari, tarehe, kiasi cha fedha, vifupisho na majina ya miradi. Usiongeze maneno ambayo hayakusemwa.',
            default => 'A professional meeting. Preserve names, numbers, dates, amounts, acronyms, project names and action items accurately. Keep the language actually spoken and do not invent words.',
        };
    }

    /** @return string[] */
    private function keywordsFor(string $language): array
    {
        return match ($language) {
            'en-GB' => ['My Digital Diary', 'Uganda', 'UGX', 'action item', 'follow-up'],
            'lg' => ['My Digital Diary', 'Uganda', 'UGX'],
            'sw' => ['My Digital Diary', 'Uganda', 'UGX'],
            default => ['My Digital Diary', 'Uganda', 'UGX'],
        };
    }

    private function responseError(Response $response): string
    {
        return (string) ($response->json('error.message') ?? $response->body());
    }

    /**
     * OpenAI accepts a defined set of recording containers. Keep valid files
     * unchanged, repair generic/.bin filenames by MIME detection, and use
     * ffmpeg when the source is another recorder format.
     *
     * @return array{0:string,1:bool}
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
            'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/mp4' => 'm4a',
            'video/mp4' => 'mp4', 'audio/x-m4a' => 'm4a', 'audio/wav' => 'wav',
            'audio/x-wav' => 'wav', 'audio/webm' => 'webm', 'video/webm' => 'webm',
            'audio/ogg' => 'ogg', 'application/ogg' => 'ogg', 'audio/flac' => 'flac',
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
            $wav = $target . '.wav';
            @unlink($target);
            $command = escapeshellarg($ffmpeg) . ' -y -i ' . escapeshellarg($fullPath)
                . ' -vn -ac 1 -ar 16000 -c:a pcm_s16le ' . escapeshellarg($wav) . ' 2>&1';
            @exec($command, $output, $exitCode);
            if ($exitCode === 0 && is_file($wav) && filesize($wav) >= 1024) {
                return [$wav, true];
            }
            @unlink($wav);
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
