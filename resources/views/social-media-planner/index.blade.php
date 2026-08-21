@extends('layouts.app')

@section('title', 'Social Media Planner')


@push('styles')
<style>
    /* =========================================================
       SOCIAL MEDIA PLANNER - RESPONSIVE TABLE
       Keep table columns horizontal on phones.
       The table scrolls left/right instead of squeezing words
       into vertical letters.
    ========================================================= */
    #social-media-planner {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    #social-media-planner .smp-table-card {
        width: 100%;
        max-width: 100%;
        min-width: 0;
        overflow: hidden;
    }

    #social-media-planner .smp-table-scroll {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior-x: contain;
        scrollbar-width: thin;
    }

    #social-media-planner .smp-table {
        width: 100% !important;
        min-width: 940px !important;
        border-collapse: collapse !important;
        border-spacing: 0 !important;
        table-layout: auto !important;
    }

    /*
     * Override any global mobile CSS that changes table elements
     * into block/grid/card layouts.
     */
    #social-media-planner .smp-table thead {
        display: table-header-group !important;
    }

    #social-media-planner .smp-table tbody {
        display: table-row-group !important;
    }

    #social-media-planner .smp-table tr {
        display: table-row !important;
        width: auto !important;
    }

    #social-media-planner .smp-table th,
    #social-media-planner .smp-table td {
        display: table-cell !important;
        float: none !important;
        width: auto !important;
        max-width: none !important;
        box-sizing: border-box;
        vertical-align: top;
        word-break: normal !important;
        overflow-wrap: normal !important;
        hyphens: none !important;
    }

    /* Checkbox */
    #social-media-planner .smp-table th:nth-child(1),
    #social-media-planner .smp-table td:nth-child(1) {
        width: 52px !important;
        min-width: 52px !important;
        text-align: center;
    }

    /* Post */
    #social-media-planner .smp-table th:nth-child(2),
    #social-media-planner .smp-table td:nth-child(2) {
        width: 300px !important;
        min-width: 300px !important;
    }

    /* Platforms */
    #social-media-planner .smp-table th:nth-child(3),
    #social-media-planner .smp-table td:nth-child(3) {
        width: 190px !important;
        min-width: 190px !important;
    }

    /* Schedule */
    #social-media-planner .smp-table th:nth-child(4),
    #social-media-planner .smp-table td:nth-child(4) {
        width: 180px !important;
        min-width: 180px !important;
        white-space: nowrap !important;
    }

    /* Status */
    #social-media-planner .smp-table th:nth-child(5),
    #social-media-planner .smp-table td:nth-child(5) {
        width: 145px !important;
        min-width: 145px !important;
        white-space: nowrap !important;
    }

    /* Actions */
    #social-media-planner .smp-table th:nth-child(6),
    #social-media-planner .smp-table td:nth-child(6) {
        width: 210px !important;
        min-width: 210px !important;
    }

    #social-media-planner .smp-table th {
        white-space: nowrap !important;
        line-height: 1.25;
    }

    #social-media-planner .smp-table td {
        line-height: 1.45;
    }

    #social-media-planner .smp-post-title,
    #social-media-planner .smp-post-caption {
        word-break: normal !important;
        overflow-wrap: anywhere !important;
        white-space: normal !important;
    }

    #social-media-planner .smp-platforms {
        display: flex !important;
        flex-wrap: wrap !important;
        align-items: center;
        gap: .25rem;
        min-width: 0;
    }

    #social-media-planner .smp-platform-badge {
        display: inline-flex !important;
        align-items: center;
        width: auto !important;
        max-width: 100%;
        white-space: nowrap !important;
        word-break: keep-all !important;
    }

    #social-media-planner .smp-actions {
        display: flex !important;
        flex-wrap: wrap !important;
        justify-content: flex-end;
        align-items: center;
        gap: .5rem;
        white-space: nowrap;
    }

    #social-media-planner .smp-actions button,
    #social-media-planner .smp-actions form,
    #social-media-planner .smp-actions form button {
        width: auto !important;
        min-width: 0 !important;
        display: inline-flex !important;
        align-items: center;
        white-space: nowrap !important;
    }

    #social-media-planner .smp-scroll-hint {
        display: none;
    }

    @media (max-width: 767.98px) {
        #social-media-planner {
            overflow-x: hidden;
        }

        #social-media-planner .apple-surface {
            max-width: 100%;
        }

        #social-media-planner .smp-table-card {
            border-radius: 1rem;
        }

        #social-media-planner .smp-table-toolbar {
            padding: .75rem;
            gap: .75rem;
        }

        #social-media-planner .smp-table-toolbar label,
        #social-media-planner .smp-table-toolbar button {
            white-space: nowrap;
        }

        #social-media-planner .smp-scroll-hint {
            display: flex;
            align-items: center;
            gap: .4rem;
            padding: .55rem .8rem;
            border-bottom: 1px solid rgb(226 232 240);
            background: rgb(248 250 252);
            color: rgb(100 116 139);
            font-size: 11px;
            font-weight: 700;
        }

        #social-media-planner .smp-table {
            /* Intentionally wider than the phone viewport. */
            width: 940px !important;
            min-width: 940px !important;
        }

        #social-media-planner .smp-table th,
        #social-media-planner .smp-table td {
            padding: .75rem !important;
        }

        #social-media-planner .smp-actions {
            justify-content: flex-start;
        }

        #social-media-planner .pm-dialog {
            width: calc(100vw - 20px) !important;
            max-width: calc(100vw - 20px) !important;
            max-height: calc(100dvh - 20px) !important;
            margin: auto !important;
        }

        #social-media-planner .pm-modal-content,
        #social-media-planner .pm-modal-body {
            min-width: 0 !important;
            max-width: 100% !important;
        }
    }
</style>
@endpush


@section('content')
@php
    $userTimezone = auth()->user()->timezone ?: 'Africa/Kampala';
@endphp

<div class="space-y-4" id="social-media-planner">
    <div class="apple-surface rounded-2xl p-4 sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="text-xs font-black uppercase tracking-[.12em] text-slate-400">
                    Communication Planner
                </div>
                <h1 class="mt-1 text-xl font-black">Social Media Planner</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Schedule content, receive a reminder when it is due, then post it from the device you use for that account.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route('profile.social-media') }}"
                   class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold">
                    <i class="fa-solid fa-user-gear mr-1"></i> Accounts
                </a>

                <a href="{{ route('social-media-planner.reports.index') }}"
                   class="apple-btn rounded-xl px-4 py-2.5 text-sm font-bold">
                    <i class="fa-solid fa-chart-column mr-1"></i> Reports
                </a>

                <button type="button"
                        onclick="openPostModal()"
                        class="btn-primary rounded-xl px-4 py-2.5 text-sm font-bold text-white">
                    <i class="fa-solid fa-plus mr-1"></i> New Post
                </button>
            </div>
        </div>

        <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800">
            <i class="fa-solid fa-circle-info mr-1"></i>
            Scheduled posts become <strong>Ready to Post</strong> at the selected time and trigger an in-app/push reminder.
            WhatsApp Status/Channels and any account without an authorised publishing API use <strong>Post Now</strong>.
        </div>
    </div>

    <form method="POST"
          action="{{ route('social-media-planner.bulk-destroy') }}"
          id="bulk-delete-form"
          class="hidden">
        @csrf
        @method('DELETE')
    </form>

    <div class="apple-surface rounded-2xl overflow-hidden smp-table-card">
            <div class="smp-table-toolbar p-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3">
                <label class="flex items-center gap-2 text-xs font-bold">
                    <input type="checkbox" id="select-all-posts">
                    Select all
                </label>

                <button type="submit"
                        form="bulk-delete-form"
                        id="bulk-delete-button"
                        class="rounded-xl bg-rose-600 px-3 py-2 text-xs font-bold text-white disabled:opacity-40"
                        disabled>
                    <i class="fa-solid fa-trash mr-1"></i>
                    Delete selected
                </button>
            </div>

            <div class="smp-scroll-hint">
                <i class="fa-solid fa-arrows-left-right"></i>
                Swipe left or right to view all table columns.
            </div>

            <div class="smp-table-scroll">
                <table class="smp-table text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3"></th>
                            <th class="px-4 py-3 text-left">Post</th>
                            <th class="px-4 py-3 text-left">Platforms</th>
                            <th class="px-4 py-3 text-left">Schedule</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                    @forelse($posts as $post)
                        @php
                            $editPayload = [
                                'id' => (int) $post->id,
                                'title' => (string) ($post->title ?? ''),
                                'caption' => (string) ($post->caption ?? ''),
                                'hashtags' => (string) ($post->hashtags ?? ''),
                                'platforms' => array_values((array) ($post->platforms ?? [])),
                                'scheduled_at' => $post->scheduled_at
                                    ? $post->scheduled_at->timezone($userTimezone)->format('Y-m-d\TH:i')
                                    : '',
                                'posting_mode' => (string) ($post->posting_mode ?? 'manual'),
                                'media_type' => (string) ($post->media_type ?? 'text'),
                                'media_url' => $post->publicMediaUrl(),
                                'link_url' => $post->attachedLink(),
                            ];

                            $encodedEditPayload = base64_encode(
                                json_encode(
                                    $editPayload,
                                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                ) ?: '{}'
                            );

                            $sharePayload = base64_encode(
                                json_encode([
                                    'id' => (int) $post->id,
                                    'title' => (string) ($post->title ?? ''),
                                    'text' => $post->shareText(),
                                    'media_url' => $post->publicMediaUrl(),
                                    'link_url' => $post->attachedLink(),
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}'
                            );

                            $status = (string) ($post->status ?? 'draft');
                            $statusClass = match ($status) {
                                'published' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'ready_to_share' => 'bg-sky-50 text-sky-700 border-sky-200',
                                'scheduled' => 'bg-amber-50 text-amber-700 border-amber-200',
                                default => 'bg-slate-50 text-slate-600 border-slate-200',
                            };
                        @endphp

                        <tr>
                            <td class="px-4 py-3 align-top">
                                <input type="checkbox"
                                       class="post-checkbox"
                                       form="bulk-delete-form"
                                       name="ids[]"
                                       value="{{ $post->id }}">
                            </td>

                            <td class="px-4 py-3 align-top">
                                <div class="smp-post-title font-bold text-slate-800">{{ $post->title }}</div>
                                <div class="smp-post-caption mt-1 max-w-xl text-xs text-slate-500 line-clamp-2">
                                    {{ $post->caption }}
                                </div>
                                @if($post->media_path || $post->attachedLink())
                                    <div class="mt-2 flex flex-wrap gap-2 text-[11px]">
                                        @if($post->media_path)
                                            <a href="{{ $post->publicMediaUrl() }}" target="_blank" rel="noopener" class="font-bold text-sky-700">
                                                <i class="fa-solid {{ $post->media_type === 'video' ? 'fa-video' : 'fa-image' }} mr-1"></i>{{ ucfirst($post->media_type) }}
                                            </a>
                                        @endif
                                        @if($post->attachedLink())
                                            <a href="{{ $post->attachedLink() }}" target="_blank" rel="noopener" class="font-bold text-indigo-700">
                                                <i class="fa-solid fa-link mr-1"></i>Attached link
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <td class="px-4 py-3 align-top">
                                <div class="smp-platforms">
                                @foreach((array) $post->platforms as $platform)
                                    <span class="smp-platform-badge rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-600">
                                        @if($platform === 'x')
                                            <i class="fa-brands fa-x-twitter mr-1" aria-hidden="true"></i>X (Twitter)
                                        @else
                                            {{ ucwords(str_replace('_', ' ', $platform)) }}
                                        @endif
                                    </span>
                                @endforeach
                                </div>
                            </td>

                            <td class="px-4 py-3 align-top text-xs text-slate-600 whitespace-nowrap">
                                @if($post->scheduled_at)
                                    {{ $post->scheduled_at->timezone($userTimezone)->format('d M Y, g:i A') }}
                                @else
                                    Draft
                                @endif
                            </td>

                            <td class="px-4 py-3 align-top">
                                <span class="inline-flex rounded-full border px-2 py-1 text-[10px] font-black uppercase {{ $statusClass }}">
                                    {{ $status === 'ready_to_share' ? 'Ready to Post' : str_replace('_', ' ', $status) }}
                                </span>
                            </td>

                            <td class="px-4 py-3 align-top">
                                <div class="smp-actions">
                                    <button type="button"
                                            class="js-edit-social-post text-xs font-bold text-sky-700"
                                            data-post="{{ $encodedEditPayload }}">
                                        <i class="fa-solid fa-pen-to-square mr-1"></i>Edit
                                    </button>

                                    @if($status !== 'published')
                                        <button type="button"
                                                class="js-post-now text-xs font-bold text-teal-700"
                                                data-post="{{ $sharePayload }}"
                                                data-url="{{ route('social-media-planner.post-now', $post) }}">
                                            <i class="fa-solid fa-paper-plane mr-1"></i>Post now
                                        </button>

                                        <form method="POST"
                                              action="{{ route('social-media-planner.mark-published', $post) }}"
                                              class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="text-xs font-bold text-emerald-700">
                                                <i class="fa-solid fa-circle-check mr-1"></i>Mark posted
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">
                                No social media posts yet.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

        <div class="p-4">{{ $posts->links() }}</div>
    </div>
</div>

<dialog id="social-post-modal" class="pm-dialog rounded-2xl">
    <form method="POST"
          action="{{ route('social-media-planner.store') }}"
          id="social-post-form"
          enctype="multipart/form-data"
          class="pm-modal-content">
        @csrf
        <input type="hidden" name="_method" id="post-method" value="POST">

        <div class="pm-modal-header">
            <div>
                <h2 class="pm-modal-title" id="post-modal-title">Schedule Social Media Post</h2>
                <p class="pm-modal-subtitle">Create once and choose where it should appear.</p>
            </div>
            <button type="button"
                    class="pm-modal-close"
                    onclick="document.getElementById('social-post-modal').close()">&times;</button>
        </div>

        <div class="pm-modal-body">
            <div class="grid gap-3 md:grid-cols-2">
                <div class="md:col-span-2">
                    <label class="text-xs font-bold">Post title</label>
                    <input name="title" id="post-title" required class="pm-input mt-1 w-full">
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs font-bold">Caption</label>
                    <textarea name="caption" id="post-caption" rows="5" class="pm-input mt-1 w-full"></textarea>
                </div>

                <div>
                    <label class="text-xs font-bold">Hashtags</label>
                    <textarea name="hashtags" id="post-hashtags" rows="3" class="pm-input mt-1 w-full"></textarea>
                </div>

                <div>
                    <label class="text-xs font-bold">Schedule</label>
                    <input type="datetime-local"
                           name="scheduled_at"
                           id="post-schedule"
                           class="pm-input mt-1 w-full">
                </div>

                <div class="md:col-span-2 rounded-xl border border-slate-200 p-3">
                    <label class="text-xs font-bold">Attach image or video</label>
                    <input type="file"
                           name="attachment"
                           id="post-attachment"
                           accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm"
                           class="pm-input mt-1 w-full">
                    <p class="mt-1 text-[11px] text-slate-500">Images and videos up to 50 MB. A new upload replaces the current media.</p>
                    <div id="current-media-row" class="mt-2 hidden items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-xs">
                        <a id="current-media-link" href="#" target="_blank" rel="noopener" class="font-bold text-sky-700">View current media</a>
                        <label class="flex items-center gap-2 font-bold text-rose-600">
                            <input type="checkbox" name="remove_media" value="1" id="remove-media"> Remove
                        </label>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs font-bold">Attach link</label>
                    <input type="url" name="link_url" id="post-link-url" placeholder="https://example.com/..." class="pm-input mt-1 w-full">
                    <p class="mt-1 text-[11px] text-slate-500">The link is included with the post text and can be used together with an image or video.</p>
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs font-bold">Posting method</label>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-3">
                            <input type="radio"
                                   name="posting_mode"
                                   value="automatic"
                                   id="posting-mode-automatic">
                            <span>
                                <strong class="block text-xs">Automatic posting</strong>
                                <span class="block text-[11px] text-slate-500">
                                    Laravel posts at the scheduled time when the selected account has authorised API access.
                                </span>
                            </span>
                        </label>

                        <label class="flex items-start gap-2 rounded-xl border border-slate-200 p-3">
                            <input type="radio"
                                   name="posting_mode"
                                   value="manual"
                                   id="posting-mode-manual"
                                   checked>
                            <span>
                                <strong class="block text-xs">Remind me to post</strong>
                                <span class="block text-[11px] text-slate-500">
                                    Move the item to Ready to Post and send a reminder.
                                </span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label class="text-xs font-bold">Platforms</label>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    @foreach([
                        'instagram' => 'Instagram',
                        'facebook' => 'Facebook',
                        'x' => 'X (Twitter)',
                        'tiktok' => 'TikTok',
                        'linkedin' => 'LinkedIn',
                        'whatsapp_status' => 'WhatsApp Status',
                        'whatsapp_channel' => 'WhatsApp Channel',
                    ] as $value => $label)
                        <label class="flex items-center gap-2 rounded-xl border border-slate-200 p-3 text-xs font-bold">
                            <input type="checkbox"
                                   name="platforms[]"
                                   value="{{ $value }}"
                                   class="platform-checkbox">
                            {{ $label }}
                        </label>
                    @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="pm-modal-footer">
            <button type="button"
                    class="apple-btn apple-btn-small"
                    onclick="document.getElementById('social-post-modal').close()">
                Cancel
            </button>
            <button class="btn-primary rounded-xl px-4 py-2 text-sm font-bold text-white">
                Save Post
            </button>
        </div>
    </form>
</dialog>

<script>
(function () {
    'use strict';

    const modal = document.getElementById('social-post-modal');
    const form = document.getElementById('social-post-form');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function decodePayload(value) {
        try {
            const binary = atob(value || '');
            const bytes = Uint8Array.from(binary, c => c.charCodeAt(0));
            return JSON.parse(new TextDecoder().decode(bytes));
        } catch (_) {
            return {};
        }
    }

    window.openPostModal = function (post = null) {
        form.action = post
            ? `{{ url('/social-media-planner') }}/${post.id}`
            : `{{ route('social-media-planner.store') }}`;

        document.getElementById('post-method').value = post ? 'PUT' : 'POST';
        document.getElementById('post-modal-title').textContent =
            post ? 'Edit Social Media Post' : 'Schedule Social Media Post';

        document.getElementById('post-title').value = post?.title || '';
        document.getElementById('post-caption').value = post?.caption || '';
        document.getElementById('post-hashtags').value = post?.hashtags || '';
        document.getElementById('post-schedule').value = post?.scheduled_at || '';
        document.getElementById('post-link-url').value = post?.link_url || '';
        document.getElementById('post-attachment').value = '';
        document.getElementById('remove-media').checked = false;

        const mediaRow = document.getElementById('current-media-row');
        const mediaLink = document.getElementById('current-media-link');
        if (post?.media_url) {
            mediaRow.classList.remove('hidden');
            mediaRow.classList.add('flex');
            mediaLink.href = post.media_url;
            mediaLink.textContent = post.media_type === 'video' ? 'View current video' : 'View current image';
        } else {
            mediaRow.classList.add('hidden');
            mediaRow.classList.remove('flex');
            mediaLink.href = '#';
        }

        const mode = post?.posting_mode || 'manual';
        const automatic = document.getElementById('posting-mode-automatic');
        const manual = document.getElementById('posting-mode-manual');

        if (automatic) automatic.checked = mode === 'automatic';
        if (manual) manual.checked = mode !== 'automatic';

        const selected = Array.isArray(post?.platforms)
            ? post.platforms
            : [];

        document.querySelectorAll('.platform-checkbox').forEach(input => {
            input.checked = selected.includes(input.value);
        });

        modal?.showModal();
    };

    document.querySelectorAll('.js-edit-social-post').forEach(button => {
        button.addEventListener('click', () => {
            window.openPostModal(
                decodePayload(button.dataset.post)
            );
        });
    });

    document.querySelectorAll('.js-post-now').forEach(button => {
        button.addEventListener('click', async () => {
            const post = decodePayload(button.dataset.post);

            try {
                await fetch(button.dataset.url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf
                    }
                });
            } catch (_) {}

            const text = post.text || post.title || '';

            if (navigator.share) {
                try {
                    const shareData = {
                        title: post.title || 'Social media post',
                        text
                    };
                    if (post.link_url) shareData.url = post.link_url;
                    await navigator.share(shareData);
                    return;
                } catch (_) {}
            }

            try {
                await navigator.clipboard.writeText(text);
                const original = button.innerHTML;
                button.innerHTML = '<i class="fa-solid fa-check mr-1"></i>Copied';
                setTimeout(() => button.innerHTML = original, 1600);
            } catch (_) {
                window.prompt('Copy this post:', text);
            }
        });
    });

    const all = document.getElementById('select-all-posts');
    const boxes = [...document.querySelectorAll('.post-checkbox')];
    const bulkButton = document.getElementById('bulk-delete-button');

    function updateBulkState() {
        const selected = boxes.filter(box => box.checked).length;
        if (bulkButton) bulkButton.disabled = selected === 0;

        if (all) {
            all.checked = boxes.length > 0 && selected === boxes.length;
            all.indeterminate = selected > 0 && selected < boxes.length;
        }
    }

    all?.addEventListener('change', () => {
        boxes.forEach(box => box.checked = all.checked);
        updateBulkState();
    });

    boxes.forEach(box => {
        box.addEventListener('change', updateBulkState);
    });

    updateBulkState();
})();
</script>
@endsection
