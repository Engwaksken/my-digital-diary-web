<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Meeting;
use App\Models\MeetingAuditLog;
use App\Models\MeetingRecording;
use App\Services\MeetingSummaryService;
use App\Services\TranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Mobile equivalent of MeetingRecordingController — same
 * start/pause/resume/stop/transcribe/summarize flow, JSON responses
 * instead of redirects. The Flutter side captures audio with the
 * `record` package and uploads the finished file to stop(), the same
 * shape as the web app's MediaRecorder-captured blob.
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

    public function index(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeMeeting($request, $meeting);

        $recordings = $meeting->recordings()->orderByDesc('id')->get();

        return response()->json(['data' => $recordings->map(fn ($r) => $this->transform($r))]);
    }

    public function store(Request $request, Meeting $meeting): JsonResponse
    {
        $this->authorizeMeeting($request, $meeting);
        $request->validate(['consent' => ['required', 'accepted']]);

        $recording = $meeting->recordings()->create([
            'recorded_by_user_id' => $request->user()->id,
            'status' => 'recording',
            'consent_given_at' => now(),
        ]);

        MeetingAuditLog::record($meeting->id, $request->user()->id, 'started_recording');

        return response()->json(['data' => $this->transform($recording)]);
    }

    public function updateStatus(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        $data = $request->validate([
            'status' => ['required', 'in:recording,paused'],
            'duration_seconds' => ['required', 'integer', 'min:0'],
        ]);

        $recording->update($data);
        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, $data['status'] === 'paused' ? 'paused_recording' : 'resumed_recording');

        return response()->json(['data' => $this->transform($recording)]);
    }

    public function stop(Request $request, MeetingRecording $recording): JsonResponse
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
            'duration_seconds' => $data['duration_seconds'],
        ]);

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'stopped_recording', "Duration: {$recording->formattedDuration()}");

        return response()->json(['data' => $this->transform($recording)]);
    }

    public function transcribe(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        if (! $recording->audio_path) {
            return response()->json(['message' => 'No audio to transcribe yet.'], 422);
        }

        $data = $request->validate([
            'transcription_language' => ['nullable', 'in:auto,en-GB,lg,sw'],
        ]);
        $language = $data['transcription_language'] ?? 'auto';

        $recording->update(['transcription_status' => 'processing', 'transcription_error' => null]);

        try {
            $result = app(TranscriptionService::class)->transcribe($request->user(), $recording->audio_path, $language);

            $recording->update([
                'transcript' => $result['transcript'],
                'transcript_segments' => $result['segments'],
                'transcription_status' => 'completed',
            ]);

            MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'transcribed_recording');

            return response()->json(['data' => $this->transform($recording->fresh())]);
        } catch (\Throwable $e) {
            $recording->update(['transcription_status' => 'failed', 'transcription_error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function updateTranscript(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        $data = $request->validate(['transcript' => ['required', 'string']]);
        $recording->update(['transcript' => $data['transcript']]);

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'edited_transcript');

        return response()->json(['data' => $this->transform($recording)]);
    }

    public function generateSummary(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        if (! $recording->transcript) {
            return response()->json(['message' => 'Transcribe the recording first.'], 422);
        }

        $recording->update(['summary_status' => 'processing', 'summary_error' => null]);

        try {
            $summary = app(MeetingSummaryService::class)->generate($request->user(), $recording->transcript);
            $recording->update(['summary' => $summary, 'summary_status' => 'completed']);

            MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'generated_summary');

            return response()->json(['data' => $this->transform($recording->fresh())]);
        } catch (\Throwable $e) {
            $recording->update(['summary_status' => 'failed', 'summary_error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, MeetingRecording $recording): JsonResponse
    {
        $this->authorizeRecording($request, $recording);

        if ($recording->audio_path) {
            Storage::disk('public')->delete($recording->audio_path);
        }

        MeetingAuditLog::record($recording->meeting_id, $request->user()->id, 'deleted_recording');
        $recording->delete();

        return response()->json(['message' => 'Recording removed.']);
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

    private function transform(MeetingRecording $recording): array
    {
        return [
            'id' => $recording->id,
            'meeting_id' => $recording->meeting_id,
            'status' => $recording->status,
            'duration_seconds' => $recording->duration_seconds,
            'formatted_duration' => $recording->formattedDuration(),
            'audio_url' => $recording->audioUrl(),
            'transcript' => $recording->transcript,
            'transcript_segments' => $recording->transcript_segments,
            'transcription_status' => $recording->transcription_status,
            'transcription_error' => $recording->transcription_error,
            'summary' => $recording->summary,
            'summary_status' => $recording->summary_status,
            'summary_error' => $recording->summary_error,
            'created_at' => $recording->created_at->toIso8601String(),
        ];
    }
}
