<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\AnnouncementNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

class AdminAnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::with('creator')->latest()->paginate(20);

        return view('admin.announcements.index', ['announcements' => $announcements]);
    }

    public function create(): View
    {
        return view('admin.announcements.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $announcement = Announcement::create([
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'body' => $data['body'],
        ]);

        // Chunked rather than loading every user into memory at once —
        // each notification is still queued individually either way
        // (see AnnouncementNotification's ShouldQueue), this just
        // caps how many User models exist in memory per batch while
        // dispatching them.
        User::orderBy('id')->chunk(100, function ($users) use ($announcement) {
            Notification::send($users, new AnnouncementNotification($announcement));
        });

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement sent to every user.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $deleted = Announcement::whereIn('id', $data['ids'])->delete();

        return back()->with('success', $deleted === 1 ? '1 announcement deleted.' : "{$deleted} announcements deleted.");
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }
}
