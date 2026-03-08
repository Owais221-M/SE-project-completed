<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP — CryptoVault</title>
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="index.css">
    <style>
        .otp-icon {
            font-size: 3rem;
            margin-bottom: var(--space-md);
            display: block;
            text-align: center;
        }
        .otp-timer {
            text-align: center;
            font-size: 0.85rem;
            color: var(--accent);
            font-weight: 600;
            padding: 8px 16px;
            background: var(--accent-glow);
            border-radius: var(--radius-sm);
            margin-bottom: var(--space-md);
        }
        .otp-timer.expired {
            color: var(--red);
            background: var(--red-bg);
        }
        .otp-input {
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 8px;
            font-weight: 700;
        }
        .resend-section {
            text-align: center;
            margin-top: var(--space-md);
        }
    </style>
    <script>
        let expiry = <?php echo $_SESSION['otp_expiry'] ?? time(); ?>;
        function countdown() {
            const timer = document.getElementById("timer");
            const now = Math.floor(Date.now() / 1000);
            let secondsLeft = expiry - now;

            if (secondsLeft <= 0) {
                timer.innerText = "OTP has expired";
                timer.classList.add("expired");
                document.getElementById("otp-form").style.display = "none";
                return;
            }

            let min = Math.floor(secondsLeft / 60);
            let sec = secondsLeft % 60;
            timer.innerText = `Expires in ${min}:${sec < 10 ? '0' : ''}${sec}`;
            setTimeout(countdown, 1000);
        }
        window.onload = countdown;
    </script>
</head>
<body>

<div class="auth-wrapper">
    <div class="auth-header">
        <div class="auth-logo">🔐</div>
        <h1>Verify Your Identity</h1>
        <p>We sent a 6-digit code to your email</p>
    </div>

    <div class="auth-card">
        <div id="timer" class="otp-timer">Loading...</div>

        <form id="otp-form" action="verify_otp.php" method="post" class="auth-form">
            <div class="field">
                <label for="otp">Enter OTP Code</label>
                <input type="text" name="otp" id="otp" class="otp-input" placeholder="• • • • • •" maxlength="6" required autocomplete="one-time-code">
            </div>
            <button type="submit" class="g-btn g-btn-primary">Verify & Sign In</button>
        </form>

        <div class="resend-section">
            <form action="resend_otp.php" method="post">
                <button type="submit" class="g-btn g-btn-ghost">Didn't receive it? Resend OTP</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
