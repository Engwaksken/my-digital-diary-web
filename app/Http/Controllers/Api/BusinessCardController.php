<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessCard;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BusinessCardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $card = BusinessCard::where('user_id', $request->user()->id)->first();
        return response()->json(['data' => $card ? $this->transform($card) : null]);
    }

    public function update(Request $request): JsonResponse
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
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
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
        $newLogoPath = null;
        $oldLogoPath = $card?->logo_path;
        $removeLogo = (bool) ($data['remove_logo'] ?? false);

        try {
            if ($request->hasFile('photo')) {
                $newPhotoPath = $request->file('photo')->store('business-cards', 'public');
            }

            if ($request->hasFile('logo')) {
                $newLogoPath = $request->file('logo')->store('business-cards/logos', 'public');
            }

            $card = DB::transaction(function () use ($data, $user, $card, $newPhotoPath, $newLogoPath, $removeLogo) {
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
                    'social_links' => array_filter([
                        'facebook' => $data['social_facebook'] ?? null,
                        'twitter' => $data['social_twitter'] ?? null,
                        'linkedin' => $data['social_linkedin'] ?? null,
                        'instagram' => $data['social_instagram'] ?? null,
                    ], fn ($value) => $value !== null && $value !== ''),
                ];

                if (Schema::hasColumn('business_cards', 'card_color')) {
                    $payload['card_color'] = $data['card_color'] ?? null;
                }
                if (Schema::hasColumn('business_cards', 'card_color_secondary')) {
                    $payload['card_color_secondary'] = $data['card_color_secondary'] ?? null;
                }
                if ($newPhotoPath) {
                    $payload['photo_path'] = $newPhotoPath;
                }
                if (Schema::hasColumn('business_cards', 'logo_path')) {
                    if ($newLogoPath) {
                        $payload['logo_path'] = $newLogoPath;
                    } elseif ($removeLogo) {
                        $payload['logo_path'] = null;
                    }
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

            if ($oldLogoPath && ($removeLogo || ($newLogoPath && $oldLogoPath !== $newLogoPath))) {
                Storage::disk('public')->delete($oldLogoPath);
            }

            return response()->json([
                'message' => 'Business card saved successfully.',
                'data' => $this->transform($card),
            ]);
        } catch (Throwable $e) {
            if ($newPhotoPath) {
                Storage::disk('public')->delete($newPhotoPath);
            }
            if ($newLogoPath) {
                Storage::disk('public')->delete($newLogoPath);
            }
            report($e);

            return response()->json([
                'message' => 'Business card could not be saved. Please try again.',
            ], 500);
        }
    }

    public function togglePublished(Request $request): JsonResponse
    {
        $card = BusinessCard::where('user_id', $request->user()->id)->firstOrFail();
        $card->update(['is_published' => ! $card->is_published]);
        return response()->json(['data' => $this->transform($card->fresh())]);
    }

    private function transform(BusinessCard $card): array
    {
        return [
            'id' => $card->id,
            'name' => $card->name,
            'title' => $card->title,
            'company' => $card->company,
            'phone' => $card->phone,
            'whatsapp_phone' => $card->whatsapp_phone,
            'email' => $card->email,
            'website' => $card->website,
            'address' => $card->address,
            'bio' => $card->bio,
            'social_links' => $card->social_links ?? [],
            'photo_url' => $card->photoUrl(),
            'logo_url' => $card->logoUrl(),
            'public_url' => $card->publicUrl(),
            'qr_code_url' => $card->qrCodeUrl(),
            'is_published' => (bool) $card->is_published,
            'card_color' => $card->cardColor(),
            'card_color_secondary' => $card->cardColorSecondary(),
        ];
    }


    public function photo(Request $request)
    {
        $card = BusinessCard::where('user_id', $request->user()->id)->firstOrFail();

        if (! $card->photo_path || ! Storage::disk('public')->exists($card->photo_path)) {
            abort(404, 'Business card photo not found.');
        }

        $disk = Storage::disk('public');
        $mime = $disk->mimeType($card->photo_path) ?: 'image/jpeg';

        return response($disk->get($card->photo_path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function logo(Request $request)
    {
        $card = BusinessCard::where('user_id', $request->user()->id)->firstOrFail();

        if (! $card->logo_path || ! Storage::disk('public')->exists($card->logo_path)) {
            abort(404, 'Business card logo not found.');
        }

        $disk = Storage::disk('public');
        $mime = $disk->mimeType($card->logo_path) ?: 'image/png';

        return response($disk->get($card->logo_path), 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function downloadPdf(Request $request)
    {
        $card = BusinessCard::where('user_id', $request->user()->id)->firstOrFail();
        $pdf = Pdf::loadView('business-card.pdf', compact('card'));
        return $pdf->download(Str::slug($card->name) . '-business-card.pdf');
    }
}
