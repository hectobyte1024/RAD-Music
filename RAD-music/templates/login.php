<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_functions.php';

redirectIfLoggedIn();

$title = "Login";
$jsFiles = ['login.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h1>Welcome back</h1>
        <p>Sign in to your RAD Music account</p>
        
        <form id="login-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Sign In</button>
        </form>
        
        <div class="auth-links">
            <a href="/RAD-music/templates/register.php">Don't have an account? Sign up</a>
            <a href="/forgot-password">Forgot password?</a>
        </div>
        
        <div id="login-error" class="alert alert-danger" style="display: none;"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>