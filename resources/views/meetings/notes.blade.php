@extends('layouts.app')

@section('title', 'Meeting Notes — ' . $meeting->title)

@section('content')
    <a href="{{ route('meetings.index') }}" class="text-sm text-[var(--brand-1)] hover:underline flex items-center gap-1 mb-4">
        <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
        Back to Meetings
    </a>

    <div class="flex items-center gap-3 mb-2">
        <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center shadow-sm shrink-0">
            <i class="fa-solid fa-note-sticky text-xl" aria-hidden="true"></i>
        </div>
        <div>
            <h1 class="text-2xl font-bold text-slate-800 tracking-tight">{{ $meeting->title }}</h1>
            <p class="text-sm text-slate-500">
                {{ $meeting->start_at->format('l, M j, Y \a\t g:i A') }}
                @if ($meeting->location) &middot; {{ $meeting->location }} @endif
            </p>
        </div>
    </div>

    <div class="max-w-2xl mt-6 space-y-6">
        {{-- ================= RECORDING ================= --}}
        <div id="record-meeting" class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 scroll-mt-24">
            <h2 class="font-semibold text-slate-800 mb-1 flex items-center gap-2">
                <i class="fa-solid fa-microphone text-rose-500" aria-hidden="true"></i>
                Recording
            </h2>
            <p class="text-sm text-slate-500 mb-4">Record this meeting or upload an audio/video recording from your device, then transcribe it and generate an AI summary.</p>

            <div id="pm-rec-idle-controls" class="flex items-center gap-3">
                <button type="button" onclick="document.getElementById('pm-consent-modal').showModal()"
                        class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                    <i class="fa-solid fa-circle text-rose-300" aria-hidden="true"></i> Start Recording
                </button>
                <form method="POST" action="{{ route('meetings.recordings.upload', $meeting->id) }}" enctype="multipart/form-data" class="inline-flex">
                    @csrf
                    <label class="cursor-pointer text-slate-600 hover:text-slate-800 px-4 py-2.5 rounded-lg text-sm font-medium border border-slate-200 hover:border-slate-300 transition-colors">
                        <i class="fa-solid fa-upload" aria-hidden="true"></i> Upload Recording
                        <input type="file" name="audio" accept="audio/*,video/*" class="hidden" onchange="this.form.submit()">
                    </label>
                </form>
            </div>

            <div id="pm-rec-active-controls" class="hidden items-center gap-4" style="display: none;">
                <span class="flex items-center gap-2 text-rose-600 font-medium text-sm">
                    <span id="pm-rec-indicator" class="w-2.5 h-2.5 rounded-full bg-rose-500 animate-pulse"></span>
                    <span id="pm-rec-status-label">Recording</span>
                </span>
                <span id="pm-rec-timer" class="font-mono text-lg text-slate-700">00:00</span>
                <button type="button" id="pm-rec-pause-btn" onclick="pmPauseRecording()" class="text-sm text-slate-600 hover:text-slate-800">
                    <i class="fa-solid fa-pause" aria-hidden="true"></i> Pause
                </button>
                <button type="button" id="pm-rec-resume-btn" onclick="pmResumeRecording()" class="hidden text-sm text-slate-600 hover:text-slate-800">
                    <i class="fa-solid fa-play" aria-hidden="true"></i> Resume
                </button>
                <button type="button" onclick="pmStopRecording()" class="text-sm text-rose-600 hover:text-rose-800 font-medium">
                    <i class="fa-solid fa-stop" aria-hidden="true"></i> Stop
                </button>
            </div>

            <p id="pm-rec-error" class="hidden text-sm text-rose-600 mt-3"></p>

            {{-- Consent notice — shown BEFORE any recording starts. --}}
            <dialog id="pm-consent-modal" class="rounded-2xl p-0 pm-dialog-sm shadow-2xl backdrop:bg-slate-900/50">
                <div class="p-6">
                    <h3 class="text-lg font-bold text-slate-800 mb-3">
                        <i class="fa-solid fa-shield-halved text-amber-500" aria-hidden="true"></i>
                        Recording Consent
                    </h3>
                    <p class="text-sm text-slate-600 mb-4">
                        You're about to record this meeting's audio. Make sure everyone present is aware and has
                        agreed to being recorded, in line with your local laws on recording consent. The
                        recording, its transcript, and any AI-generated summary will be stored under this
                        meeting and are only accessible to you and site administrators.
                    </p>
                    <div class="flex items-center gap-3">
                        <button type="button" onclick="pmAcceptConsentAndStart()" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium">
                            I Confirm — Start Recording
                        </button>
                        <button type="button" onclick="document.getElementById('pm-consent-modal').close()" class="text-sm text-slate-500 hover:text-slate-700">
                            Cancel
                        </button>
                    </div>
                </div>
            </dialog>
        </div>

        {{-- ================= PAST RECORDINGS ================= --}}
        <div id="transcripts-summary" class="scroll-mt-24"></div>
        @foreach ($recordings as $recording)
            @php
                $recordingSummary = $recording->summary;
                if (is_string($recordingSummary)) {
                    $decodedSummary = json_decode($recordingSummary, true);
                    $recordingSummary = is_array($decodedSummary) ? $decodedSummary : [];
                }
                $recordingSummary = is_array($recordingSummary) ? $recordingSummary : [];
            @endphp
            <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-slate-800">
                        <i class="fa-solid fa-waveform-lines text-slate-400" aria-hidden="true"></i>
                        Recording — {{ $recording->created_at->copy()->timezone('Africa/Nairobi')->format('M j, Y g:i A') }} ({{ $recording->formattedDuration() }})
                    </h3>
                    <form method="POST" action="{{ route('meeting-recordings.destroy', $recording->id) }}" onsubmit="return confirm('Delete this recording, its transcript, and its summary?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-xs text-rose-500 hover:underline">
                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Delete
                        </button>
                    </form>
                </div>

                @if ($recording->audioUrl())
                    <audio controls src="{{ $recording->audioUrl() }}" class="w-full mb-3"></audio>
                    <a href="{{ route('meeting-recordings.audio', $recording->id) }}" class="text-xs text-[var(--brand-1)] hover:underline">
                        <i class="fa-solid fa-download" aria-hidden="true"></i> Download audio
                    </a>
                @endif

                @if ($recording->audio_path)
                    <form method="POST" action="{{ route('meeting-recordings.process', $recording->id) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm hover:shadow-md transition-all">
                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                            {{ $recording->transcript ? 'Refresh AI Summary' : 'Transcribe & AI Summarize' }}
                        </button>
                    </form>
                @endif
                @error('processing') <p role="alert" class="text-sm text-rose-600 mt-2">{{ $message }}</p> @enderror

                {{-- ---- Transcript ---- --}}
                <div class="border-t border-slate-100 mt-4 pt-4">
                    <h4 class="text-sm font-semibold text-slate-700 mb-2">Transcript</h4>

                    @if (filled($recording->transcript))
                        <div class="flex items-center gap-2 mb-2">
                            <label for="transcript-search-{{ $recording->id }}" class="sr-only">Search transcript</label>
                            <input type="search" id="transcript-search-{{ $recording->id }}" placeholder="Search this transcript..."
                                   oninput="pmSearchTranscript({{ $recording->id }}, this.value)" class="pm-input text-sm flex-1">
                            <button type="button" onclick="pmCopyTranscript({{ $recording->id }})" class="text-sm text-[var(--brand-1)] hover:underline whitespace-nowrap">
                                <i class="fa-solid fa-copy" aria-hidden="true"></i> Copy
                            </button>
                            <a href="{{ route('meeting-recordings.transcript.download', $recording->id) }}" class="text-sm text-[var(--brand-1)] hover:underline whitespace-nowrap">
                                <i class="fa-solid fa-download" aria-hidden="true"></i> Download
                            </a>
                        </div>

                        @if (! empty($recording->transcript_segments))
                            <div id="transcript-view-{{ $recording->id }}" class="max-h-64 overflow-y-auto border border-slate-200 rounded-lg p-3 text-sm space-y-2 mb-2">
                                @foreach ($recording->transcript_segments as $segment)
                                    <p class="pm-transcript-line" data-text="{{ strtolower((string) ($segment['text'] ?? '')) }}">
                                        <span class="text-slate-400 font-mono text-xs">
                                            [{{ sprintf('%02d:%02d', intdiv((int) ($segment['start_seconds'] ?? 0), 60), (int) ($segment['start_seconds'] ?? 0) % 60) }}]
                                        </span>
                                        @if (!empty($segment['speaker']))
                                            <span class="font-medium text-slate-700">{{ $segment['speaker'] ?? '' }}:</span>
                                        @endif
                                        {{ $segment['text'] ?? '' }}
                                    </p>
                                @endforeach
                            </div>
                            <p class="text-xs text-slate-400 mb-2">
                                Speaker labels aren't available from the current transcription setup — see notes.
                            </p>
                        @endif

                        <form method="POST" action="{{ route('meeting-recordings.transcript.update', $recording->id) }}">
                            @csrf
                            @method('PUT')
                            <label for="transcript-edit-{{ $recording->id }}" class="block text-xs text-slate-500 mb-1">Edit transcript text</label>
                            <textarea id="transcript-edit-{{ $recording->id }}" name="transcript" rows="4" class="pm-input text-sm">{{ $recording->transcript }}</textarea>
                            <button type="submit" class="mt-2 text-sm bg-white border border-slate-300 text-slate-700 px-3 py-1.5 rounded-lg hover:bg-slate-50 transition-colors">
                                Save Edits
                            </button>
                        </form>
                    @elseif ($recording->transcription_status === 'processing')
                        <p class="text-sm text-slate-500"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Transcribing...</p>
                    @else
                        @if ($recording->transcription_status === 'failed' && $recording->transcription_error)
                            <p class="text-sm text-rose-600 mb-2"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ $recording->transcription_error }}</p>
                        @endif
                        <form method="POST" action="{{ route('meeting-recordings.transcribe', $recording->id) }}">
                            @csrf
                            <button type="submit" class="text-sm bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-50 transition-colors">
                                <i class="fa-solid fa-file-waveform" aria-hidden="true"></i> Transcribe Recording
                            </button>
                        </form>
                    @endif
                </div>

                {{-- ---- Summary ---- --}}
                @if ($recording->transcript)
                    <div class="border-t border-slate-100 mt-4 pt-4">
                        <h4 class="text-sm font-semibold text-slate-700 mb-2">AI Summary</h4>

                        @if (!empty($recordingSummary))
                            <div class="space-y-3 text-sm mb-3">
                                @if (! empty($recordingSummary['main_points']))
                                    <div>
                                        <p class="font-medium text-slate-700">Main Discussion Points</p>
                                        <ul class="list-disc list-inside text-slate-600">
                                            @foreach ($recordingSummary['main_points'] as $point)<li>{{ $point }}</li>@endforeach
                                        </ul>
                                    </div>
                                @endif
                                @if (! empty($recordingSummary['decisions']))
                                    <div>
                                        <p class="font-medium text-slate-700">Decisions Made</p>
                                        <ul class="list-disc list-inside text-slate-600">
                                            @foreach ($recordingSummary['decisions'] as $decision)<li>{{ $decision }}</li>@endforeach
                                        </ul>
                                    </div>
                                @endif
                                @if (! empty($recordingSummary['action_items']))
                                    <div>
                                        <p class="font-medium text-slate-700">Action Items</p>
                                        <ul class="list-disc list-inside text-slate-600">
                                            @foreach ($recordingSummary['action_items'] as $item)
                                                <li>
                                                    {{ $item['task'] ?? '' }}
                                                    @if (! empty($item['assigned_to'])) <span class="text-slate-400">— {{ $item['assigned_to'] }}</span> @endif
                                                    @if (! empty($item['deadline'])) <span class="text-amber-600">(due {{ $item['deadline'] }})</span> @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                                @if (! empty($recordingSummary['questions_for_followup']))
                                    <div>
                                        <p class="font-medium text-slate-700">Questions Requiring Follow-up</p>
                                        <ul class="list-disc list-inside text-slate-600">
                                            @foreach ($recordingSummary['questions_for_followup'] as $question)<li>{{ $question }}</li>@endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                <a href="{{ route('meeting-recordings.summary.download', $recording->id) }}" class="text-sm text-[var(--brand-1)] hover:underline">
                                    <i class="fa-solid fa-download" aria-hidden="true"></i> Download summary
                                </a>
                            </div>

                            <form method="POST" action="{{ route('meeting-recordings.email-summary', $recording->id) }}" class="flex flex-col sm:flex-row items-start gap-2">
                                @csrf
                                <input type="text" name="emails" placeholder="participant1@example.com, participant2@example.com" class="pm-input text-sm flex-1">
                                <button type="submit" class="text-sm bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-50 transition-colors whitespace-nowrap">
                                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Email to Participants
                                </button>
                            </form>
                            @error('emails') <p role="alert" class="text-sm text-rose-600 mt-1">{{ $message }}</p> @enderror
                        @elseif ($recording->summary_status === 'processing')
                            <p class="text-sm text-slate-500"><i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i> Generating summary...</p>
                        @else
                            @if ($recording->summary_status === 'failed' && $recording->summary_error)
                                <p class="text-sm text-rose-600 mb-2"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> {{ $recording->summary_error }}</p>
                            @endif
                            <form method="POST" action="{{ route('meeting-recordings.summarize', $recording->id) }}">
                                @csrf
                                <button type="submit" class="text-sm bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-50 transition-colors">
                                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Generate Summary
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach

        @if ($recordings->hasPages())
            <nav aria-label="Meeting recordings pagination" class="mt-4">
                {{ $recordings->links() }}
            </nav>
        @endif

        {{-- ================= DOWNLOAD & SHARE ================= --}}
        <div class="pm-card-bg shadow-sm border border-slate-100 rounded-xl p-6 mt-6">
            <h2 class="font-semibold text-slate-800 mb-1">Download &amp; Share Meeting Notes</h2>
            <p class="text-sm text-slate-500 mb-4">The PDF, email and WhatsApp share now include your meeting notes, saved transcript and AI summary.</p>

            <div class="flex flex-wrap gap-3 mb-4">
                <a href="{{ route('meetings.notes.pdf', $meeting->id) }}"
                   class="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 transition-colors">
                    <i class="fa-solid fa-file-pdf text-rose-600" aria-hidden="true"></i> Download PDF
                </a>
                <a href="https://wa.me/?text={{ rawurlencode($shareText) }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 bg-white border border-slate-300 text-slate-700 px-4 py-2 rounded-lg text-sm hover:bg-slate-50 transition-colors">
                    <i class="fa-brands fa-whatsapp text-emerald-600" aria-hidden="true"></i> Share via WhatsApp
                </a>
            </div>

            <form method="POST" action="{{ route('meetings.notes.email', $meeting->id) }}" class="space-y-2">
                @csrf
                <label for="meeting-share-emails" class="text-sm font-medium text-slate-700">Share via Email</label>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input id="meeting-share-emails" type="text" name="share_emails" value="{{ old('share_emails', $meeting->attendees) }}"
                           placeholder="name@example.com, another@example.com" class="pm-input text-sm flex-1">
                    <button type="submit" class="btn-primary text-white px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Send Meeting Notes
                    </button>
                </div>
                @error('share_emails') <p role="alert" class="text-sm text-rose-600">{{ $message }}</p> @enderror
            </form>
        </div>
    </div>

    <script>
        // ===== Recording (MediaRecorder API — no library needed) =====
        var pmMediaRecorder = null;
        var pmMediaStream = null;
        var pmAudioChunks = [];
        var pmRecordingId = null;
        var pmElapsedSeconds = 0;
        var pmTimerInterval = null;
        var pmMeetingId = {{ $meeting->id }};

        function pmCsrfToken() {
            return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        }

        function pmShowRecError(message) {
            var el = document.getElementById('pm-rec-error');
            el.textContent = message;
            el.classList.remove('hidden');
        }

        function pmFormatTimer(seconds) {
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }

        function pmAcceptConsentAndStart() {
            document.getElementById('pm-consent-modal').close();
            pmStartRecording();
        }

        // Reuses the exact same start()+stop() server flow live
        // recording uses below — the backend doesn't care whether the
        // audio came from the microphone or an already-existing file,
        // so no new endpoint was needed for this at all. Duration is
        // read from the file itself (via a throwaway <audio> element's
        // loadedmetadata event) since there's no live timer for an
        // upload the way there is for a live recording.
        function pmUploadRecording(fileInput) {
            var file = fileInput.files[0];
            if (!file) { return; }

            var probe = document.createElement('video');
            probe.muted = true;
            probe.preload = 'metadata';
            probe.onloadedmetadata = function () {
                var durationSeconds = Number.isFinite(probe.duration) ? Math.round(probe.duration) : 0;
                URL.revokeObjectURL(probe.src);
                pmSubmitUploadedRecording(file, durationSeconds);
                fileInput.value = '';
            };
            probe.onerror = function () {
                // Some formats/browsers can't report duration this way —
                // still proceed rather than blocking the whole upload
                // over a value the server only stores for display.
                URL.revokeObjectURL(probe.src);
                pmSubmitUploadedRecording(file, 0);
                fileInput.value = '';
            };
            probe.src = URL.createObjectURL(file);
        }

        function pmSubmitUploadedRecording(file, durationSeconds) {
            fetch('/meetings/' + pmMeetingId + '/recordings', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': pmCsrfToken(), 'Accept': 'application/json' },
                body: (function () { var fd = new FormData(); fd.append('consent', '1'); return fd; })(),
            }).then(function (response) {
                if (!response.ok) { throw new Error('Could not start the recording session.'); }
                return response.json();
            }).then(function (data) {
                var formData = new FormData();
                formData.append('audio', file, file.name);
                formData.append('duration_seconds', durationSeconds);

                return fetch('/meeting-recordings/' + data.recording_id + '/stop', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': pmCsrfToken(), 'Accept': 'application/json' },
                    body: formData,
                });
            }).then(function (response) {
                if (!response.ok) { throw new Error('Could not upload the recording.'); }
                window.location.reload();
            }).catch(function (err) {
                pmShowRecError(err.message);
            });
        }

        function pmStartRecording() {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                pmShowRecError('This browser does not support audio recording. Try a recent version of Chrome, Firefox, Edge, or Safari.');
                return;
            }

            navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
                pmMediaStream = stream;
                pmAudioChunks = [];
                var preferredTypes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
                var selectedType = preferredTypes.find(function (type) {
                    return typeof MediaRecorder.isTypeSupported !== 'function' || MediaRecorder.isTypeSupported(type);
                });
                pmMediaRecorder = selectedType ? new MediaRecorder(stream, { mimeType: selectedType }) : new MediaRecorder(stream);
                pmMediaRecorder.ondataavailable = function (e) { if (e.data.size > 0) { pmAudioChunks.push(e.data); } };

                fetch('/meetings/' + pmMeetingId + '/recordings', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': pmCsrfToken(), 'Accept': 'application/json' },
                    body: (function () { var fd = new FormData(); fd.append('consent', '1'); return fd; })(),
                }).then(function (response) {
                    if (!response.ok) { throw new Error('Could not start the recording session.'); }
                    return response.json();
                }).then(function (data) {
                    pmRecordingId = data.recording_id;
                    pmMediaRecorder.start();
                    pmElapsedSeconds = 0;
                    pmTimerInterval = setInterval(function () {
                        pmElapsedSeconds++;
                        document.getElementById('pm-rec-timer').textContent = pmFormatTimer(pmElapsedSeconds);
                    }, 1000);

                    document.getElementById('pm-rec-idle-controls').classList.add('hidden');
                    var activeControls = document.getElementById('pm-rec-active-controls');
                    activeControls.classList.remove('hidden');
                    activeControls.style.display = 'flex';
                }).catch(function (err) {
                    pmShowRecError(err.message);
                    stream.getTracks().forEach(function (t) { t.stop(); });
                });
            }).catch(function () {
                pmShowRecError('Microphone access was denied or is unavailable — check your browser/device permissions.');
            });
        }

        function pmPauseRecording() {
            if (!pmMediaRecorder || pmMediaRecorder.state !== 'recording') { return; }
            pmMediaRecorder.pause();
            clearInterval(pmTimerInterval);
            document.getElementById('pm-rec-status-label').textContent = 'Paused';
            document.getElementById('pm-rec-indicator').classList.remove('animate-pulse');
            document.getElementById('pm-rec-pause-btn').classList.add('hidden');
            document.getElementById('pm-rec-resume-btn').classList.remove('hidden');

            fetch('/meeting-recordings/' + pmRecordingId + '/status', {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': pmCsrfToken(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ status: 'paused', duration_seconds: pmElapsedSeconds }),
            });
        }

        function pmResumeRecording() {
            if (!pmMediaRecorder || pmMediaRecorder.state !== 'paused') { return; }
            pmMediaRecorder.resume();
            pmTimerInterval = setInterval(function () {
                pmElapsedSeconds++;
                document.getElementById('pm-rec-timer').textContent = pmFormatTimer(pmElapsedSeconds);
            }, 1000);
            document.getElementById('pm-rec-status-label').textContent = 'Recording';
            document.getElementById('pm-rec-indicator').classList.add('animate-pulse');
            document.getElementById('pm-rec-pause-btn').classList.remove('hidden');
            document.getElementById('pm-rec-resume-btn').classList.add('hidden');

            fetch('/meeting-recordings/' + pmRecordingId + '/status', {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': pmCsrfToken(), 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ status: 'recording', duration_seconds: pmElapsedSeconds }),
            });
        }

        function pmStopRecording() {
            if (!pmMediaRecorder) { return; }
            clearInterval(pmTimerInterval);

            pmMediaRecorder.onstop = function () {
                if (!pmAudioChunks.length) {
                    pmShowRecError('No audio was captured. Please record for at least a few seconds and try again.');
                    return;
                }
                var actualType = (pmMediaRecorder && pmMediaRecorder.mimeType) || pmAudioChunks[0].type || 'audio/webm';
                var cleanType = actualType.split(';')[0];
                var extension = cleanType === 'audio/mp4' ? 'm4a' : (cleanType.indexOf('ogg') !== -1 ? 'ogg' : 'webm');
                var blob = new Blob(pmAudioChunks, { type: actualType });
                if (blob.size < 1024) {
                    pmShowRecError('The recording is empty or too short. Please record for at least a few seconds.');
                    return;
                }
                var formData = new FormData();
                formData.append('audio', blob, 'recording.' + extension);
                formData.append('duration_seconds', pmElapsedSeconds);

                fetch('/meeting-recordings/' + pmRecordingId + '/stop', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': pmCsrfToken(), 'Accept': 'application/json' },
                    body: formData,
                }).then(function (response) {
                    if (!response.ok) { throw new Error('Could not save the recording.'); }
                    window.location.reload();
                }).catch(function (err) {
                    pmShowRecError(err.message);
                });

                pmMediaStream.getTracks().forEach(function (t) { t.stop(); });
            };

            pmMediaRecorder.stop();
        }

        // ===== Transcript search/copy =====
        function pmSearchTranscript(recordingId, query) {
            var lowerQuery = query.toLowerCase();
            document.querySelectorAll('#transcript-view-' + recordingId + ' .pm-transcript-line').forEach(function (line) {
                var matches = !lowerQuery || line.dataset.text.indexOf(lowerQuery) !== -1;
                line.style.display = matches ? '' : 'none';
            });
        }

        function pmCopyTranscript(recordingId) {
            var view = document.getElementById('transcript-view-' + recordingId);
            var text = view
                ? Array.prototype.map.call(view.querySelectorAll('.pm-transcript-line'), function (el) { return el.textContent.trim(); }).join('\n')
                : document.getElementById('transcript-edit-' + recordingId).value;
            navigator.clipboard?.writeText(text);
        }
    </script>
@endsection
