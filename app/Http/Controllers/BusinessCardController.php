<?php

namespace App\Http\Controllers;

use App\Models\BusinessCard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class BusinessCardController extends Controller
{
    public function edit(Request $request): View
    {
        $card = BusinessCard::where('user_id', $request->user()->id)->first();

        $scanStats = null;
        if ($card) {
            $period = $request->query('scan_period');
            $from = $request->query('scan_from');
            $to = $request->query('scan_to');
            $filteredQuery = $card->scans();

            match ($period) {
                'daily' => $filteredQuery->whereDate('created_at', now()->toDateString()),
                'weekly' => $filteredQuery->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
                'monthly' => $filteredQuery->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
                'range' => ($from && $to)
                    ? $filteredQuery->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                    : null,
                default => null,
            };

            $scanStats = [
                'today' => $card->scans()->whereDate('created_at', now()->toDateString())->count(),
                'this_week' => $card->scans()->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
                'total' => $card->scans()->count(),
                'filtered' => $filteredQuery->count(),
                'period' => $period,
                'from' => $from,
                'to' => $to,
            ];
        }

        return view('business-card.edit', compact('card', 'scanStats'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'whatsapp_phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'social_facebook' => ['nullable', 'string', 'max:255'],
            'social_twitter' => ['nullable', 'string', 'max:255'],
            'social_linkedin' => ['nullable', 'string', 'max:255'],
            'social_instagram' => ['nullable', 'string', 'max:255'],
            'card_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'card_color_secondary' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $user = $request->user();
        $card = BusinessCard::where('user_id', $user->id)->first();
        $newPhotoPath = null;
        $oldPhotoPath = $card?->photo_path;

        try {
            if ($request->hasFile('photo')) {
                $newPhotoPath = $request->file('photo')->store('business-cards', 'public');
            }

            $card = DB::transaction(function () use ($data, $user, $card, $newPhotoPath) {
                $socialLinks = array_filter([
                    'facebook' => $data['social_facebook'] ?? null,
                    'twitter' => $data['social_twitter'] ?? null,
                    'linkedin' => $data['social_linkedin'] ?? null,
                    'instagram' => $data['social_instagram'] ?? null,
                ], fn ($value) => $value !== null && $value !== '');

                $payload = [
                    'name' => $data['name'],
                    'title' => $data['title'] ?? null,
                    'company' => $data['company'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'whatsapp_phone' => $data['whatsapp_phone'] ?? null,
                    'email' => $data['email'] ?? null,
                    'website' => $data['website'] ?? null,
                    'address' => $data['address'] ?? null,
                    'bio' => $data['bio'] ?? null,
                    'social_links' => $socialLinks,
                ];

                // Keep the page usable even if the optional color migration
                // has not yet been run on an older production database.
                if (Schema::hasColumn('business_cards', 'card_color')) {
                    $payload['card_color'] = $data['card_color'] ?? null;
                }
                if (Schema::hasColumn('business_cards', 'card_color_secondary')) {
                    $payload['card_color_secondary'] = $data['card_color_secondary'] ?? null;
                }
                if ($newPhotoPath) {
                    $payload['photo_path'] = $newPhotoPath;
                }

                if ($card) {
                    if (! $card->slug) {
                        $payload['slug'] = BusinessCard::generateUniqueSlug($data['name']);
                    }
                    $card->fill($payload)->save();
                } else {
                    $payload['user_id'] = $user->id;
                    $payload['slug'] = BusinessCard::generateUniqueSlug($data['name']);
                    $card = BusinessCard::create($payload);
                }

                return $card->fresh();
            });

            if ($newPhotoPath && $oldPhotoPath && $oldPhotoPath !== $newPhotoPath) {
                Storage::disk('public')->delete($oldPhotoPath);
            }

            return redirect()
                ->route('business-card.edit')
                ->with('success', 'Business card saved successfully. Your public page and QR code are ready.');
        } catch (Throwable $e) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }
            report($e);

            return back()
                ->withInput()
                ->with('error', 'Business card could not be saved. Please try again.');
        }
    }

    public function togglePublished(Request $request): RedirectResponse
    {
        $card = BusinessCard::where('user_id', $request->user()->id)->firstOrFail();
        $card->update(['is_published' => ! $card->is_published]);

        return back()->with('success', $card->is_published ? 'Your card is now public.' : 'Your card is now hidden from the public link.');
    }

    public function showPublic(Request $request, string $slug): View
    {
        $card = BusinessCard::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $card->scans()->create(['ip_address' => $request->ip()]);

        return view('business-card.public', compact('card'));
    }

    public function downloadPdf(Request $request)
    {
        $card = BusinessCard::where('user_id', $request->user()->id)->firstOrFail();
        $pdf = Pdf::loadView('business-card.pdf', compact('card'));

        return $pdf->download(\Illuminate\Support\Str::slug($card->name) . '-business-card.pdf');
    }
}
