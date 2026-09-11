<!doctype html>
<html>
<body style="font-family:Arial,sans-serif;color:#0f172a;line-height:1.6">
    <h2>Your My Digital Diary workspace account is ready</h2>

    <p>
        You have been added to
        <strong>{{ $organization->name }}</strong>
        as <strong>{{ ucfirst($role) }}</strong>.
    </p>

    <p>
        Sign in using:
        <br>
        <strong>Email:</strong> {{ $memberUser->email }}
    </p>

    <p>
        Your workspace owner created a temporary password for you.
        For security, that password is not included in this email.
        Ask the owner for it through a separate trusted channel.
    </p>

    <p>
        <a href="{{ route('login') }}">Sign in to My Digital Diary</a>
    </p>

    <p>
        After signing in, you can change your password from your profile.
        While you belong to this workspace, the email address used for your
        membership is locked and cannot be changed from your profile.
    </p>
</body>
</html>
