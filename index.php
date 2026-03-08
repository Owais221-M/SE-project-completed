<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CryptoVault — Trading Platform</title>
    <link rel="stylesheet" href="global.css">
    <link rel="stylesheet" href="index.css">
</head>
<body>

<div class="auth-wrapper">
    <!-- Logo & Header -->
    <div class="auth-header">
        <div class="auth-logo">₿</div>
        <h1>CryptoVault</h1>
        <p>Trade smarter. Manage risk. Stay ahead.</p>
    </div>

    <!-- Auth Card -->
    <div class="auth-card">
        <!-- Login Form -->
        <form action="login.php" method="POST" class="auth-form" id="loginForm">
            <div class="field">
                <label for="loginEmail">Email</label>
                <input type="email" id="loginEmail" name="email" placeholder="you@example.com" required>
            </div>
            <div class="field">
                <label for="loginPassword">Password</label>
                <input type="password" id="loginPassword" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="g-btn g-btn-primary">Sign In</button>
            <p class="auth-footer">Don't have an account? <a href="#" id="showRegisterForm">Create one</a></p>
        </form>

        <!-- Register Form -->
        <form action="register.php" method="POST" class="auth-form hidden" id="registerForm">
            <div class="field">
                <label for="registerName">Full Name</label>
                <input type="text" id="registerName" name="username" placeholder="John Doe" required>
            </div>
            <div class="field">
                <label for="registerEmail">Email</label>
                <input type="email" id="registerEmail" name="email" placeholder="you@example.com" required>
            </div>
            <div class="field">
                <label for="registerPassword">Password</label>
                <input type="password" id="registerPassword" name="password" placeholder="Create a strong password" required>
            </div>
            <button type="submit" class="g-btn g-btn-primary">Create Account</button>
            <p class="auth-footer">Already have an account? <a href="#" id="showLoginForm">Sign in</a></p>
        </form>
    </div>

    <!-- Feature Highlights -->
    <div class="auth-features">
        <div class="auth-feature">
            <span class="feat-icon">🔒</span>
            OTP Secured
        </div>
        <div class="auth-feature">
            <span class="feat-icon">📊</span>
            Live Charts
        </div>
        <div class="auth-feature">
            <span class="feat-icon">⚡</span>
            Instant Trades
        </div>
        <div class="auth-feature">
            <span class="feat-icon">🛡️</span>
            Risk Management
        </div>
    </div>
</div>

<script src="script.js"></script>
</body>
</html>