<?php

namespace App\Http\Controllers;

use App\Mail\MeetingSummaryMail;
use App\Models\Meeting;
use App\Models\MeetingAuditLog;
use App\Models\MeetingRecording;
use App\Services\MeetingSummaryService;
use App\Services\TranscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MeetingRecordingController extends Controller
{
    private const TRANSCRIPTION_FAILURE_MESSAGE =
        'We could not transcribe this recording. Please try again with a supported audio file under 25 MB.';

    private function authorizeMeeting(Request $request, Meeting $meeting): void
    {
        abort_unless(
            (int) $meeting->user_id === (int) $request->user()->id
            || $request->user()->isAdmin(),
            403
        );
    }

    private function authorizeRecording(
        Request $request,
        MeetingRecording $recording
    ): void {
        $recording->loadMissing('meeting');

        $this->authorizeMeeting(
            $request,
            $recording->meeting
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Start recording session
    |--------------------------------------------------------------------------
    */

    public function store(
        Request $request,
        Meeting $meeting
    ): JsonResponse {
        $this->authorizeMeeting(
            $request,
            $meeting
        );

        $request->validate([
            'consent' => [
                'required',
                'accepted',
            ],
        ]);

        $recording =
            $meeting
                ->recordings()
                ->create([
                    'recorded_by_user_id' =>
                        $request->user()->id,

                    'status' =>
                        'recording',

                    'consent_given_at' =>
                        now(),
                ]);

        MeetingAuditLog::record(
            $meeting->id,
            $request->user()->id,
            'started_recording'
        );

        return response()->json([
            'ok' => true,
            'recording_id' => $recording->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Upload existing recording
    |--------------------------------------------------------------------------
    */

    public function upload(
        Request $request,
        Meeting $meeting
    ): RedirectResponse {
        $this->authorizeMeeting(
            $request,
            $meeting
        );

        $data =
            $request->validate([
                'audio' => [
                    'required',
                    'file',
                    'max:204800',
                ],

                'duration_seconds' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],
            ]);

        /** @var UploadedFile $audioFile */
        $audioFile =
            $request->file('audio');

        $this->validateRecordingFile(
            $audioFile
        );

        $audioPath =
            $this->storeRecordingFile(
                $audioFile
            );

        $recording =
            $meeting
                ->recordings()
                ->create([
                    'recorded_by_user_id' =>
                        $request->user()->id,

                    'status' =>
                        'completed',

                    'consent_given_at' =>
                        now(),

                    'audio_path' =>
                        $audioPath,

                    'duration_seconds' =>
                        (int) (
                            $data['duration_seconds']
                            ?? 0
                        ),
                ]);

        MeetingAuditLog::record(
            $meeting->id,
            $request->user()->id,
            'uploaded_recording',
            $audioFile->getClientOriginalName()
        );

        return back()->with(
            'success',
            'Recording uploaded successfully. You can now transcribe it and generate an AI summary.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Pause / resume
    |--------------------------------------------------------------------------
    */

    public function updateStatus(
        Request $request,
        MeetingRecording $recording
    ): JsonResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        $data =
            $request->validate([
                'status' => [
                    'required',
                    'in:recording,paused',
                ],

                'duration_seconds' => [
                    'required',
                    'integer',
                    'min:0',
                ],
            ]);

        $recording->update(
            $data
        );

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            $data['status'] === 'paused'
                ? 'paused_recording'
                : 'resumed_recording'
        );

        return response()->json([
            'ok' => true,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Stop browser recording
    |--------------------------------------------------------------------------
    */

    public function stop(
        Request $request,
        MeetingRecording $recording
    ): JsonResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        $request->validate([
            'recording' => [
                'nullable',
                'file',
                'max:204800',
            ],

            'audio' => [
                'nullable',
                'file',
                'max:204800',
            ],

            'duration_seconds' => [
                'required',
                'integer',
                'min:0',
            ],

            'capture_type' => [
                'nullable',
                'in:microphone,mixed',
            ],

            'meeting_audio_captured' => [
                'nullable',
                'boolean',
            ],

            'microphone_audio_captured' => [
                'nullable',
                'boolean',
            ],
        ]);

        /** @var UploadedFile|null $audioFile */
        $audioFile =
            $request->file('recording')
            ?: $request->file('audio');

        if (! $audioFile) {
            return response()->json([
                'message' =>
                    'No recording file was received.',
            ], 422);
        }

        if (
            $request->input('capture_type')
            === 'mixed'
        ) {
            if (
                ! $request->boolean(
                    'meeting_audio_captured'
                )
            ) {
                return response()->json([
                    'message' =>
                        'Meeting audio was not captured. Select the meeting tab and enable Share tab audio.',
                ], 422);
            }

            if (
                ! $request->boolean(
                    'microphone_audio_captured'
                )
            ) {
                return response()->json([
                    'message' =>
                        'Microphone audio was not captured.',
                ], 422);
            }
        }

        $this->validateRecordingFile(
            $audioFile
        );

        $audioPath =
            $this->storeRecordingFile(
                $audioFile
            );

        if (
            $recording->audio_path
            && Storage::disk('public')
                ->exists(
                    $recording->audio_path
                )
        ) {
            Storage::disk('public')
                ->delete(
                    $recording->audio_path
                );
        }

        $recording->update([
            'status' =>
                'completed',

            'audio_path' =>
                $audioPath,

            'duration_seconds' =>
                (int) $request->input(
                    'duration_seconds',
                    0
                ),
        ]);

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'stopped_recording',
            'Duration: '
            . $recording->formattedDuration()
        );

        return response()->json([
            'ok' => true,
            'recording_id' => $recording->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Transcription
    |--------------------------------------------------------------------------
    */

    public function transcribe(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        if (! $recording->audio_path) {
            return back()->withErrors([
                'transcription' =>
                    'No audio to transcribe yet.',
            ]);
        }

        $data =
            $request->validate([
                'transcription_language' => [
                    'nullable',
                    'in:auto,en-GB,lg,sw',
                ],
            ]);

        $language =
            $data['transcription_language']
            ?? 'auto';

        $recording->update([
            'transcription_status' =>
                'processing',

            'transcription_error' =>
                null,
        ]);

        try {
            $result =
                app(
                    TranscriptionService::class
                )->transcribe(
                    $request->user(),
                    $recording->audio_path,
                    $language
                );

            $recording->update([
                'transcript' =>
                    $result['transcript'],

                'transcript_segments' =>
                    $result['segments'],

                'transcription_status' =>
                    'completed',
            ]);

            MeetingAuditLog::record(
                $recording->meeting_id,
                $request->user()->id,
                'transcribed_recording'
            );

            return back()->with(
                'success',
                'Transcript ready using '
                . $this->transcriptionLanguageLabel(
                    $language
                )
                . '.'
            );
        } catch (\Throwable $e) {
            report($e);

            $recording->update([
                'transcription_status' =>
                    'failed',

                'transcription_error' =>
                    self::TRANSCRIPTION_FAILURE_MESSAGE,
            ]);

            return back()->withErrors([
                'transcription' =>
                    self::TRANSCRIPTION_FAILURE_MESSAGE,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update transcript
    |--------------------------------------------------------------------------
    */

    public function updateTranscript(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        $data =
            $request->validate([
                'transcript' => [
                    'required',
                    'string',
                ],
            ]);

        $recording->update([
            'transcript' =>
                $data['transcript'],
        ]);

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'edited_transcript'
        );

        return back()->with(
            'success',
            'Transcript updated.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Generate AI summary
    |--------------------------------------------------------------------------
    */

    public function generateSummary(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        if (! $recording->transcript) {
            return back()->withErrors([
                'summary' =>
                    'Transcribe the recording first.',
            ]);
        }

        $recording->update([
            'summary_status' =>
                'processing',

            'summary_error' =>
                null,
        ]);

        try {
            $summary =
                app(
                    MeetingSummaryService::class
                )->generate(
                    $request->user(),
                    $recording->transcript
                );

            $recording->update([
                'summary' =>
                    $summary,

                'summary_status' =>
                    'completed',
            ]);

            MeetingAuditLog::record(
                $recording->meeting_id,
                $request->user()->id,
                'generated_summary'
            );

            return back()->with(
                'success',
                'AI summary ready.'
            );
        } catch (\Throwable $e) {
            report($e);

            $recording->update([
                'summary_status' =>
                    'failed',

                'summary_error' =>
                    $e->getMessage(),
            ]);

            return back()->withErrors([
                'summary' =>
                    $e->getMessage(),
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Transcribe and summarise
    |--------------------------------------------------------------------------
    */

    public function transcribeAndSummarize(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        if (
            ! $recording->audio_path
            && ! $recording->transcript
        ) {
            return back()->withErrors([
                'processing' =>
                    'Upload or record meeting audio first.',
            ]);
        }

        $data =
            $request->validate([
                'transcription_language' => [
                    'nullable',
                    'in:auto,en-GB,lg,sw',
                ],
            ]);

        $language =
            $data['transcription_language']
            ?? 'auto';

        try {
            if (! $recording->transcript) {
                $recording->update([
                    'transcription_status' =>
                        'processing',

                    'transcription_error' =>
                        null,
                ]);

                $result =
                    app(
                        TranscriptionService::class
                    )->transcribe(
                        $request->user(),
                        $recording->audio_path,
                        $language
                    );

                $recording->update([
                    'transcript' =>
                        $result['transcript'],

                    'transcript_segments' =>
                        $result['segments'],

                    'transcription_status' =>
                        'completed',
                ]);

                MeetingAuditLog::record(
                    $recording->meeting_id,
                    $request->user()->id,
                    'transcribed_recording'
                );
            }

            $recording->update([
                'summary_status' =>
                    'processing',

                'summary_error' =>
                    null,
            ]);

            $summary =
                app(
                    MeetingSummaryService::class
                )->generate(
                    $request->user(),
                    $recording
                        ->fresh()
                        ->transcript
                );

            $recording->update([
                'summary' =>
                    $summary,

                'summary_status' =>
                    'completed',
            ]);

            MeetingAuditLog::record(
                $recording->meeting_id,
                $request->user()->id,
                'generated_summary'
            );

            return back()->with(
                'success',
                'Transcript and AI summary are ready.'
            );
        } catch (\Throwable $e) {
            report($e);

            if (
                $recording->transcription_status
                === 'processing'
            ) {
                $recording->update([
                    'transcription_status' =>
                        'failed',

                    'transcription_error' =>
                        self::TRANSCRIPTION_FAILURE_MESSAGE,
                ]);
                $message = self::TRANSCRIPTION_FAILURE_MESSAGE;
            } else {
                $recording->update([
                    'summary_status' =>
                        'failed',

                    'summary_error' =>
                        $e->getMessage(),
                ]);
                $message = $e->getMessage();
            }

            return back()->withErrors([
                'processing' =>
                    $message,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Stream audio
    |--------------------------------------------------------------------------
    */

    public function streamAudio(
        Request $request,
        MeetingRecording $recording
    ) {
        $this->authorizeRecording(
            $request,
            $recording
        );

        abort_unless(
            $recording->audio_path,
            404
        );

        $disk =
            Storage::disk('public');

        abort_unless(
            $disk->exists(
                $recording->audio_path
            ),
            404,
            'Recording file not found.'
        );

        $absolutePath =
            $disk->path(
                $recording->audio_path
            );

        $extension =
            strtolower(
                pathinfo(
                    $recording->audio_path,
                    PATHINFO_EXTENSION
                )
            );

        try {
            $mime =
                $disk->mimeType(
                    $recording->audio_path
                );
        } catch (\Throwable $e) {
            $mime =
                null;
        }

        if (
            ! is_string($mime)
            || $mime === ''
            || $mime === 'application/octet-stream'
        ) {
            $mime =
                match ($extension) {
                    'mp3', 'mpga' =>
                        'audio/mpeg',

                    'wav', 'wave' =>
                        'audio/wav',

                    'm4a' =>
                        'audio/mp4',

                    'mp4', 'm4v' =>
                        'video/mp4',

                    'webm', 'weba' =>
                        'audio/webm',

                    'ogg', 'oga' =>
                        'audio/ogg',

                    'opus' =>
                        'audio/ogg; codecs=opus',

                    'aac' =>
                        'audio/aac',

                    'flac' =>
                        'audio/flac',

                    'mov' =>
                        'video/quicktime',

                    'mpeg', 'mpg' =>
                        'video/mpeg',

                    default =>
                        'application/octet-stream',
                };
        }

        return response()->file(
            $absolutePath,
            [
                'Content-Type' =>
                    $mime,

                'Content-Disposition' =>
                    'inline; filename="meeting-recording-'
                    . $recording->id
                    . '.'
                    . (
                        $extension
                        ?: 'audio'
                    )
                    . '"',

                'Accept-Ranges' =>
                    'bytes',

                'Cache-Control' =>
                    'private, no-store, max-age=0',

                'X-Content-Type-Options' =>
                    'nosniff',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Download audio
    |--------------------------------------------------------------------------
    */

    public function downloadAudio(
        Request $request,
        MeetingRecording $recording
    ) {
        $this->authorizeRecording(
            $request,
            $recording
        );

        abort_unless(
            $recording->audio_path,
            404
        );

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'downloaded_audio'
        );

        $extension =
            pathinfo(
                $recording->audio_path,
                PATHINFO_EXTENSION
            ) ?: 'bin';

        return Storage::disk('public')
            ->download(
                $recording->audio_path,
                'meeting-recording-'
                . $recording->id
                . '.'
                . $extension
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Download transcript
    |--------------------------------------------------------------------------
    */

    public function downloadTranscript(
        Request $request,
        MeetingRecording $recording
    ) {
        $this->authorizeRecording(
            $request,
            $recording
        );

        abort_unless(
            $recording->transcript,
            404
        );

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'downloaded_transcript'
        );

        $segments =
            collect(
                $recording->transcript_segments
                ?? []
            );

        $lines =
            $segments->map(
                function ($segment) {
                    $seconds =
                        (int) (
                            $segment['start_seconds']
                            ?? 0
                        );

                    $timestamp =
                        sprintf(
                            '[%02d:%02d]',
                            intdiv(
                                $seconds,
                                60
                            ),
                            $seconds % 60
                        );

                    $speaker =
                        ! empty(
                            $segment['speaker']
                        )
                            ? $segment['speaker']
                                . ': '
                            : '';

                    return $timestamp
                        . ' '
                        . $speaker
                        . (
                            $segment['text']
                            ?? ''
                        );
                }
            );

        $content =
            $lines->isNotEmpty()
                ? $lines->implode("\n")
                : $recording->transcript;

        return response(
            $content,
            200,
            [
                'Content-Type' =>
                    'text/plain; charset=UTF-8',

                'Content-Disposition' =>
                    'attachment; filename="meeting-transcript-'
                    . $recording->id
                    . '.txt"',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Download summary
    |--------------------------------------------------------------------------
    */

    public function downloadSummary(
        Request $request,
        MeetingRecording $recording
    ) {
        $this->authorizeRecording(
            $request,
            $recording
        );

        abort_unless(
            $recording->summary,
            404
        );

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'downloaded_summary'
        );

        return response(
            $this->formatSummaryAsText(
                $recording
            ),
            200,
            [
                'Content-Type' =>
                    'text/plain; charset=UTF-8',

                'Content-Disposition' =>
                    'attachment; filename="meeting-summary-'
                    . $recording->id
                    . '.txt"',
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Email notes / transcript / summary
    |--------------------------------------------------------------------------
    */

    public function emailSummary(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        $recording->loadMissing(
            'meeting'
        );

        $data =
            $request->validate([
                'emails' => [
                    'required',
                    'string',
                    'max:5000',
                ],

                'include_notes' => [
                    'nullable',
                    'boolean',
                ],

                'include_transcript' => [
                    'nullable',
                    'boolean',
                ],

                'include_summary' => [
                    'nullable',
                    'boolean',
                ],

                'message' => [
                    'nullable',
                    'string',
                    'max:3000',
                ],
            ]);

        $addresses =
            collect(
                preg_split(
                    '/[,;\n]+/',
                    $data['emails']
                )
            )
                ->map(
                    fn ($email) =>
                        trim(
                            (string) $email
                        )
                )
                ->filter()
                ->unique()
                ->values();

        $invalidAddresses =
            $addresses->filter(
                fn ($email) =>
                    ! filter_var(
                        $email,
                        FILTER_VALIDATE_EMAIL
                    )
            );

        if (
            $invalidAddresses
                ->isNotEmpty()
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'emails' =>
                        'Invalid email address: '
                        . $invalidAddresses
                            ->implode(', '),
                ]);
        }

        if (
            $addresses
                ->isEmpty()
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'emails' =>
                        'Enter at least one valid recipient email address.',
                ]);
        }

        $includeNotes =
            $request->boolean(
                'include_notes'
            );

        $includeTranscript =
            $request->boolean(
                'include_transcript'
            );

        $includeSummary =
            $request->boolean(
                'include_summary'
            );

        if (
            ! $includeNotes
            && ! $includeTranscript
            && ! $includeSummary
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'share' =>
                        'Select at least one item to share.',
                ]);
        }

        if (
            $includeTranscript
            && ! filled(
                $recording->transcript
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'share' =>
                        'A transcript has not been generated for this recording yet.',
                ]);
        }

        if (
            $includeSummary
            && empty(
                $recording->summary
            )
        ) {
            return back()
                ->withInput()
                ->withErrors([
                    'share' =>
                        'An AI summary has not been generated for this recording yet.',
                ]);
        }

        foreach (
            $addresses as $address
        ) {
            Mail::to(
                $address
            )->send(
                new MeetingSummaryMail(
                    recording:
                        $recording,

                    senderName:
                        $request->user()
                            ->name
                        ?? 'Meeting organiser',

                    includeNotes:
                        $includeNotes,

                    includeTranscript:
                        $includeTranscript,

                    includeSummary:
                        $includeSummary,

                    personalMessage:
                        $data['message']
                        ?? null
                )
            );
        }

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'shared_meeting_information',
            json_encode([
                'recording_id' =>
                    $recording->id,

                'recipients' =>
                    $addresses->all(),

                'included' => [
                    'notes' =>
                        $includeNotes,

                    'transcript' =>
                        $includeTranscript,

                    'summary' =>
                        $includeSummary,
                ],
            ])
        );

        return back()->with(
            'success',
            'Meeting information emailed successfully to '
            . $addresses->count()
            . (
                $addresses->count() === 1
                    ? ' recipient.'
                    : ' recipients.'
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete
    |--------------------------------------------------------------------------
    */

    public function destroy(
        Request $request,
        MeetingRecording $recording
    ): RedirectResponse {
        $this->authorizeRecording(
            $request,
            $recording
        );

        if (
            $recording->audio_path
        ) {
            Storage::disk('public')
                ->delete(
                    $recording->audio_path
                );
        }

        MeetingAuditLog::record(
            $recording->meeting_id,
            $request->user()->id,
            'deleted_recording'
        );

        $recording->delete();

        return back()->with(
            'success',
            'Recording removed.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function transcriptionLanguageLabel(
        string $language
    ): string {
        return match ($language) {
            'en-GB' =>
                'UK English',

            'lg' =>
                'Luganda',

            'sw' =>
                'Kiswahili',

            default =>
                'automatic language detection',
        };
    }

    private function formatSummaryAsText(
        MeetingRecording $recording
    ): string {
        $summary =
            (array) (
                $recording->summary
                ?? []
            );

        $lines = [
            'MEETING SUMMARY',
            '',
        ];

        $lines[] =
            'Main Discussion Points:';

        foreach (
            $summary['main_points']
            ?? [] as $point
        ) {
            $lines[] =
                '- ' . $point;
        }

        $lines[] = '';

        $lines[] =
            'Decisions Made:';

        foreach (
            $summary['decisions']
            ?? [] as $decision
        ) {
            $lines[] =
                '- ' . $decision;
        }

        $lines[] = '';

        $lines[] =
            'Action Items:';

        foreach (
            $summary['action_items']
            ?? [] as $item
        ) {
            if (
                is_string($item)
            ) {
                $lines[] =
                    '- ' . $item;

                continue;
            }

            $line =
                '- '
                . (
                    $item['task']
                    ?? ''
                );

            if (
                ! empty(
                    $item['assigned_to']
                )
            ) {
                $line .=
                    ' | Assigned to: '
                    . $item['assigned_to'];
            }

            if (
                ! empty(
                    $item['deadline']
                )
            ) {
                $line .=
                    ' | Deadline: '
                    . $item['deadline'];
            }

            $lines[] =
                $line;
        }

        $lines[] = '';

        $lines[] =
            'Questions Requiring Follow-up:';

        foreach (
            $summary[
                'questions_for_followup'
            ] ?? [] as $question
        ) {
            $lines[] =
                '- ' . $question;
        }

        return implode(
            "\n",
            $lines
        );
    }

    private function storeRecordingFile(
        UploadedFile $file
    ): string {
        $extension =
            strtolower(
                (string)
                $file
                    ->getClientOriginalExtension()
            );

        if (
            $extension === ''
        ) {
            $extension =
                strtolower(
                    (string)
                    $file->extension()
                );
        }

        if (
            $extension === ''
            || $extension === 'bin'
        ) {
            $mime =
                strtolower(
                    (string)
                    $file->getMimeType()
                );

            $extension =
                match (true) {
                    str_contains(
                        $mime,
                        'webm'
                    ) =>
                        'webm',

                    str_contains(
                        $mime,
                        'ogg'
                    ) =>
                        'ogg',

                    str_contains(
                        $mime,
                        'mpeg'
                    ) =>
                        'mp3',

                    str_contains(
                        $mime,
                        'wav'
                    ) =>
                        'wav',

                    str_contains(
                        $mime,
                        'mp4'
                    ) =>
                        'm4a',

                    default =>
                        'webm',
                };
        }

        $safeExtension =
            preg_replace(
                '/[^a-z0-9]+/',
                '',
                $extension
            ) ?: 'webm';

        $name =
            (string) Str::uuid()
            . '.'
            . $safeExtension;

        return $file->storeAs(
            'meeting-recordings',
            $name,
            'public'
        );
    }

    private function validateRecordingFile(
        UploadedFile $file
    ): void {
        if (
            ($file->getSize() ?? 0)
            < 1024
        ) {
            abort(
                422,
                'The recording is empty or too short. Record at least a few seconds and try again.'
            );
        }

        $mime =
            strtolower(
                (string)
                $file->getMimeType()
            );

        $extension =
            strtolower(
                (string)
                $file
                    ->getClientOriginalExtension()
            );

        $recordingExtensions = [
            'mp3',
            'wav',
            'wave',
            'm4a',
            'mp4',
            'm4v',
            'webm',
            'weba',
            'ogg',
            'oga',
            'opus',
            'aac',
            'flac',
            'wma',
            'amr',
            'awb',
            '3gp',
            '3gpp',
            '3g2',
            'mov',
            'mkv',
            'mka',
            'avi',
            'mpeg',
            'mpg',
            'mpga',
            'aiff',
            'aif',
            'aifc',
            'caf',
            'ac3',
            'eac3',
            'wmv',
            'ts',
            'mts',
            'm2ts',
            'au',
            'snd',
            'ra',
            'ram',
            'rm',
        ];

        if (
            ! str_starts_with(
                $mime,
                'audio/'
            )
            && ! str_starts_with(
                $mime,
                'video/'
            )
            && $mime
                !== 'application/octet-stream'
            && ! in_array(
                $extension,
                $recordingExtensions,
                true
            )
        ) {
            abort(
                422,
                'The selected file is not a recognised audio or video recording.'
            );
        }
    }
}