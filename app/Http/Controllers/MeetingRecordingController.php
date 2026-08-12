<?php

namespace App\Http\Controllers;

use App\Mail\MeetingSummaryMail;
use App\Models\Meeting;
use App\Models\MeetingAuditLog;
use App\Models\MeetingRecording;
use App\Services\MeetingSummaryService;
use App\Services\TranscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Recording/transcript/summary for a meeting. Access is scoped to the
 * meeting's owner (or an admin) — meetings in this app are personal
 * records with a free-text list of attendee emails, not linked
 * multi-user accounts with their own permissions, so "authorized
 * participants" access happens via emailing them the summary (see
 * emailSummary()) rather than a login-gated shared view. Every action
 * here writes a MeetingAuditLog row.
 */
class MeetingRecordingController extends Controller
{
    private function authorizeMeeting(Request $request, Meeting $meeting): void
    {
        abort_unless($meeting->user_id === $request->user()->id || $request->user()->isAdmin(), 403);
    }

    private function authorizeRecording(Request $request, MeetingRecording $recording): void
    {
        $this->authorizeMeeting($request, $recording->meeting);
    }

    /**
     * Starts a new recording session — the consent notice must have
     * already been shown and accepted client-side (see the "Recording
     * consent" modal in meetings/notes.blade.php) before this endpoint
     * is ever called; consented_given_at is stamped here as the record
     * of that.
     */
    public function store(Request $request, Meeting $meeting)
    {
        $this->authorizeMeeting($request, $meeting);

        $request->validate(['consent' => ['required', 'accepted']]);

        $recording = $meeting->recordings()->create([
            'recorded_by_user_id' => $request->user()->id,
            'status' => 'recording',
            'consent_given_at' => now(),
        ]);

        MeetingAuditLog::record($meeting->id, $request->user()->id, 'started_recording');

        return response()->json(['recording_id' => $recording->id]);
    }

    /**
     * Upload an existing meeting recording from the user's device. This
     * is separate from the browser MediaRecorder flow so mobile/desktop
     * uploads do not need to create a temporary "recording" row first.
     */
    public function upload(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorizeMeeting($request, $meeting);

        $data = $request->validate([
            'audio' => [
                'required',
                'file',
                'max:204800',
                function (string $attribute, $value, \Closure $fail): void {
                    $mime = strtolower((string) $value->getMimeType());
                    $extension = strtolower((string) $value->getClientOriginalExtension());
                    $recordingExtensions = [
                        'mp3', 'wav', 'wave', 'm4a', 'mp4', 'm4v', 'webm', 'ogg', 'oga', 'opus',
                        'aac', 'flac', 'wma', 'amr', '3gp', '3g2', 'mov', 'mkv', 'avi', 'mpeg',
                        'mpg', 'mpga', 'aiff', 'aif', 'caf', 'ac3', 'wmv', 'ts', 'mts', 'm2ts',
                        'au', 'snd', 'ra', 'ram', 'rm',
                    ];

                    $isMediaMime = str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/');
                    $isKnownRecordingExtension = in_array($extension, $recordingExtensions, true);

                    if (! $isMediaMime && ! $isKnownRecordingExtension) {
                        $fail('The uploaded file must be an audio or video recording.');
                    }
                },
            ],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $audioFile = $request->file('audio');
        $this->validateRecordingFile($audioFile);
        $audioPath = $this->storeRecordingFile($audioFile);

        $recording = $meeting->recordings()->create([
            'recorded_by_user_id' => $request->user()->id,
            'status' => 'completed',
            'consent_given_at' => now(),
            'audio_path' => $audioPath,
            'duration_seconds' => $this->resolveDurationSeconds($audioPath, (int) ($data['duration_seconds'] ?? 0)),
        ]);

        MeetingAuditLog::record($meeting->id, $request->user()->id, 'uploaded_recording', $request->file('audio')->getClientOriginalName());

        return back()->with('success', 'Recording uploaded. You can now transcribe it and generate an AI summary.');
    }

    public function updateStatus(Request $request, MeetingRecording $recording)
    {
        $this->authorizeRecording($request, $recording);

        $data = $request->validate([
            'status' => ['required', 'in:recording,paused'],
            'duration_seconds' => ['required', 'integer', 'min:0'],
        ]);

        $recording->update($data);
        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, $data['status'] === 'paused' ? 'paused_recording' : 'resumed_recording');

        return response()->json(['ok' => true]);
    }

    /**
     * Finalizes the recording — receives the actual audio blob captured
     * by the browser's MediaRecorder API and stores it against this
     * recording row.
     */
    public function stop(Request $request, MeetingRecording $recording)
    {
        $this->authorizeRecording($request, $recording);

        $data = $request->validate([
            'audio' => ['required', 'file', 'max:204800'],
            'duration_seconds' => ['required', 'integer', 'min:0'],
        ]);

        $audioFile = $request->file('audio');
        $this->validateRecordingFile($audioFile);
        $audioPath = $this->storeRecordingFile($audioFile);

        $recording->update([
            'status' => 'completed',
            'audio_path' => $audioPath,
            'duration_seconds' => $this->resolveDurationSeconds($audioPath, (int) $data['duration_seconds']),
        ]);

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'stopped_recording', "Duration: {$recording->formattedDuration()}");

        return response()->json(['ok' => true]);
    }

    public function transcribe(Request $request, MeetingRecording $recording): RedirectResponse
    {
        $this->authorizeRecording($request, $recording);

        if (! $recording->audio_path) {
            return back()->withErrors(['transcription' => 'No audio to transcribe yet.']);
        }

        $recording->update(['transcription_status' => 'processing', 'transcription_error' => null]);

        try {
            $result = app(TranscriptionService::class)->transcribe($request->user(), $recording->audio_path);

            $recording->update([
                'transcript' => $result['transcript'],
                'transcript_segments' => $result['segments'],
                'transcription_status' => 'completed',
                'duration_seconds' => $this->durationFromTranscriptOrExisting($recording, $result['segments'] ?? []),
            ]);

            MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'transcribed_recording');

            return back()->with('success', 'Transcript ready.');
        } catch (\Throwable $e) {
            $recording->update(['transcription_status' => 'failed', 'transcription_error' => $e->getMessage()]);

            return back()->withErrors(['transcription' => $e->getMessage()]);
        }
    }

    public function updateTranscript(Request $request, MeetingRecording $recording): RedirectResponse
    {
        $this->authorizeRecording($request, $recording);

        $data = $request->validate(['transcript' => ['required', 'string']]);
        $recording->update(['transcript' => $data['transcript']]);

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'edited_transcript');

        return back()->with('success', 'Transcript updated.');
    }

    public function generateSummary(Request $request, MeetingRecording $recording): RedirectResponse
    {
        $this->authorizeRecording($request, $recording);

        if (! $recording->transcript) {
            return back()->withErrors(['summary' => 'Transcribe the recording first.']);
        }

        $recording->update(['summary_status' => 'processing', 'summary_error' => null]);

        try {
            $summary = app(MeetingSummaryService::class)->generate($request->user(), $recording->transcript);

            $recording->update(['summary' => $summary, 'summary_status' => 'completed']);

            MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'generated_summary');

            return back()->with('success', 'Summary ready.');
        } catch (\Throwable $e) {
            $recording->update(['summary_status' => 'failed', 'summary_error' => $e->getMessage()]);

            return back()->withErrors(['summary' => $e->getMessage()]);
        }
    }

    /**
     * One-click workflow: create the transcript when needed, then use the
     * transcript to generate the structured AI meeting summary.
     */
    public function transcribeAndSummarize(Request $request, MeetingRecording $recording): RedirectResponse
    {
        $this->authorizeRecording($request, $recording);

        if (! $recording->audio_path && ! $recording->transcript) {
            return back()->withErrors(['processing' => 'Upload or record meeting audio first.']);
        }

        try {
            if (! $recording->transcript) {
                $recording->update(['transcription_status' => 'processing', 'transcription_error' => null]);
                $result = app(TranscriptionService::class)->transcribe($request->user(), $recording->audio_path);
                $recording->update([
                    'transcript' => $result['transcript'],
                    'transcript_segments' => $result['segments'],
                    'transcription_status' => 'completed',
                    'duration_seconds' => $this->durationFromTranscriptOrExisting($recording, $result['segments'] ?? []),
                ]);
                MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'transcribed_recording');
            }

            $recording->update(['summary_status' => 'processing', 'summary_error' => null]);
            $summary = app(MeetingSummaryService::class)->generate($request->user(), $recording->fresh()->transcript);
            $recording->update(['summary' => $summary, 'summary_status' => 'completed']);
            MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'generated_summary');

            return back()->with('success', 'Transcript and AI summary are ready.');
        } catch (\Throwable $e) {
            if ($recording->transcription_status === 'processing') {
                $recording->update(['transcription_status' => 'failed', 'transcription_error' => $e->getMessage()]);
            } else {
                $recording->update(['summary_status' => 'failed', 'summary_error' => $e->getMessage()]);
            }

            return back()->withErrors(['processing' => $e->getMessage()]);
        }
    }

    public function downloadAudio(Request $request, MeetingRecording $recording)
    {
        $this->authorizeRecording($request, $recording);
        abort_unless($recording->audio_path, 404);

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'downloaded_audio');

        $extension = pathinfo($recording->audio_path, PATHINFO_EXTENSION) ?: 'bin';

        return Storage::disk('public')->download(
            $recording->audio_path,
            'meeting-recording-' . $recording->id . '.' . $extension
        );
    }

    public function downloadTranscript(Request $request, MeetingRecording $recording)
    {
        $this->authorizeRecording($request, $recording);
        abort_unless($recording->transcript, 404);

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'downloaded_transcript');

        $lines = collect($recording->transcript_segments)->map(function ($segment) {
            $timestamp = sprintf('[%02d:%02d]', intdiv((int) $segment['start_seconds'], 60), (int) $segment['start_seconds'] % 60);
            $speaker = $segment['speaker'] ? $segment['speaker'] . ': ' : '';

            return "{$timestamp} {$speaker}{$segment['text']}";
        });

        $content = $lines->isNotEmpty() ? $lines->implode("\n") : $recording->transcript;

        return response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="meeting-transcript-' . $recording->id . '.txt"',
        ]);
    }

    public function downloadSummary(Request $request, MeetingRecording $recording)
    {
        $this->authorizeRecording($request, $recording);
        abort_unless($recording->summary, 404);

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'downloaded_summary');

        return response($this->formatSummaryAsText($recording), 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="meeting-summary-' . $recording->id . '.txt"',
        ]);
    }

    public function emailSummary(Request $request, MeetingRecording $recording): RedirectResponse
    {
        $this->authorizeRecording($request, $recording);

        $data = $request->validate([
            'emails' => ['required', 'string'],
        ]);

        $addresses = collect(explode(',', $data['emails']))
            ->map(fn ($email) => trim($email))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));

        if ($addresses->isEmpty()) {
            return back()->withErrors(['emails' => 'Enter at least one valid email address.']);
        }

        foreach ($addresses as $address) {
            Mail::to($address)->send(new MeetingSummaryMail($recording, $request->user()->name));
        }

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'emailed_summary', $addresses->implode(', '));

        return back()->with('success', 'Summary emailed to ' . $addresses->count() . ' participant(s).');
    }

    public function destroy(Request $request, MeetingRecording $recording): RedirectResponse
    {
        $this->authorizeRecording($request, $recording);

        if ($recording->audio_path) {
            Storage::disk('public')->delete($recording->audio_path);
        }

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'deleted_recording');
        $recording->delete();

        return back()->with('success', 'Recording removed.');
    }

    private function formatSummaryAsText(MeetingRecording $recording): string
    {
        $summary = $recording->summary;
        $lines = ['MEETING SUMMARY', ''];

        $lines[] = 'Main Discussion Points:';
        foreach ($summary['main_points'] ?? [] as $point) { $lines[] = '- ' . $point; }
        $lines[] = '';

        $lines[] = 'Decisions Made:';
        foreach ($summary['decisions'] ?? [] as $decision) { $lines[] = '- ' . $decision; }
        $lines[] = '';

        $lines[] = 'Action Items:';
        foreach ($summary['action_items'] ?? [] as $item) {
            $line = '- ' . ($item['task'] ?? '');
            if (! empty($item['assigned_to'])) { $line .= ' (Assigned: ' . $item['assigned_to'] . ')'; }
            if (! empty($item['deadline'])) { $line .= ' (Deadline: ' . $item['deadline'] . ')'; }
            $lines[] = $line;
        }
        $lines[] = '';

        $lines[] = 'Questions Requiring Follow-up:';
        foreach ($summary['questions_for_followup'] ?? [] as $question) { $lines[] = '- ' . $question; }

        return implode("\n", $lines);
    }
    /** Store uploaded media with a real extension. Laravel's default store()
     * can use .bin when Android sends application/octet-stream, and OpenAI
     * rejects an otherwise valid recording because the multipart filename has
     * an unsupported extension. */
    private function storeRecordingFile(\Illuminate\Http\UploadedFile $file): string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension === '') {
            $extension = strtolower((string) $file->extension());
        }
        if ($extension === '') {
            $extension = 'webm';
        }

        $safeExtension = preg_replace('/[^a-z0-9]+/', '', $extension) ?: 'webm';
        $name = (string) \Illuminate\Support\Str::uuid() . '.' . $safeExtension;

        return $file->storeAs('meeting-recordings', $name, 'public');
    }

    private function validateRecordingFile(\Illuminate\Http\UploadedFile $file): void
    {
        if (($file->getSize() ?? 0) < 1024) {
            abort(422, 'The recording is empty or too short. Record at least a few seconds and try again.');
        }

        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $recordingExtensions = [
            'mp3','wav','wave','m4a','mp4','m4v','webm','weba','ogg','oga','opus',
            'aac','flac','wma','amr','awb','3gp','3gpp','3g2','mov','mkv','mka','avi',
            'mpeg','mpg','mpga','aiff','aif','aifc','caf','ac3','eac3','wmv','ts','mts',
            'm2ts','au','snd','ra','ram','rm'
        ];

        if (! str_starts_with($mime, 'audio/')
            && ! str_starts_with($mime, 'video/')
            && ! in_array($extension, $recordingExtensions, true)) {
            abort(422, 'The selected file is not a recognised audio or video recording.');
        }
    }

    /**
     * Prefer the duration reported by the browser/app. When a device cannot
     * read metadata (common with AMR/M4A/3GP uploads), ask ffprobe on the
     * server after the file has been stored. This prevents valid uploads from
     * being permanently displayed as 0:00.
     */
    private function resolveDurationSeconds(string $audioPath, int $clientDuration = 0): int
    {
        if ($clientDuration > 0) {
            return $clientDuration;
        }

        if (! function_exists('shell_exec')) {
            return 0;
        }

        try {
            $binary = trim((string) @shell_exec('command -v ffprobe 2>/dev/null'));
            if ($binary === '') {
                return 0;
            }

            $absolutePath = Storage::disk('public')->path($audioPath);
            if (! is_file($absolutePath)) {
                return 0;
            }

            $command = escapeshellarg($binary)
                . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
                . escapeshellarg($absolutePath)
                . ' 2>/dev/null';

            $seconds = (float) trim((string) @shell_exec($command));
            return $seconds > 0 ? (int) round($seconds) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * OpenAI segments provide an end time. If an older upload had no readable
     * duration metadata, use the transcript's final segment as a reliable
     * fallback as soon as transcription succeeds.
     */
    private function durationFromTranscriptOrExisting(MeetingRecording $recording, array $segments): int
    {
        if ((int) $recording->duration_seconds > 0) {
            return (int) $recording->duration_seconds;
        }

        $max = 0.0;
        foreach ($segments as $segment) {
            $end = (float) ($segment['end_seconds'] ?? $segment['end'] ?? 0);
            if ($end > $max) {
                $max = $end;
            }
        }

        if ($max > 0) {
            return (int) ceil($max);
        }

        return $recording->audio_path
            ? $this->resolveDurationSeconds($recording->audio_path, 0)
            : 0;
    }

}
