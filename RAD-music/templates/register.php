<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_functions.php';

redirectIfLoggedIn();

$title = "Register";
$jsFiles = ['register.js'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="auth-container">
    <div class="auth-card">
        <h1>Create your account</h1>
        <p>Join the RAD Music community</p>
        
        <form id="register-form">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
            
            <div class="form-group">
                <label for="confirm-password">Confirm Password</label>
                <input type="password" id="confirm-password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block">Create Account</button>
        </form>
        
        <div class="auth-links">
            <a href="/login">Already have an account? Sign in</a>
        </div>
        
        <div id="register-error" class="alert alert-danger" style="display: none;"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>