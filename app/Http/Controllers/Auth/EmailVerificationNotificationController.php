<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Drop-in replacement for Breeze's default EmailVerificationNotificationController
 * (the "resend verification email" button on the "please verify your
 * email" notice page). Identical to Breeze's version except the actual
 * send is wrapped in a try/catch — see RegisteredUserController and
 * AuthenticatedSessionController for the same fix and the full reasoning:
 * mail sending happens SYNCHRONOUSLY whenever QUEUE_CONNECTION=sync, so an
 * SMTP rejection (a 550 "classified as spam" response is a real, observed
 * failure mode with this app's mail server) used to crash this endpoint
 * with a 500 rather than showing the user a normal "we couldn't send that
 * right now" message.
 */
class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (Throwable $e) {
            Log::warning('Could not resend the email verification notification.', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'verification' => 'We could not send a verification email right now. Please try again in a moment.',
            ]);
        }

        return back()->with('status', 'verification-link-sent');
    }
}
