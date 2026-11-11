<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .container { border: 1px solid #ddd; border-radius: 8px; padding: 30px; background: #fafafa; }
        .button { display: inline-block; padding: 12px 24px; background: #0056b3; color: white; text-decoration: none; border-radius: 4px; margin: 20px 0; }
        .footer { margin-top: 30px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Password Reset Request</h2>
        <p>Hello,</p>
        <p>We received a request to reset the password for your ODCI Document Hub account.</p>
        <p>Use the secure link below within one hour:</p>
        <p style="text-align: center;">
            <a href="{{ $resetUrl }}" class="button">Reset Password</a>
        </p>
        <p>Or copy this link: <br><small>{{ $resetUrl }}</small></p>
        <p>If you did not request this change, you can ignore this email.</p>
        <div class="footer">
            <p>CvSU Naic Campus Document Hub</p>
        </div>
    </div>
</body>
</html>
