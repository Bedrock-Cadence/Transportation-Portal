<?php
// FILE: public_html/portal/logout.php

require_once __DIR__ . '/../../app/init.php';

// First, check if a user is actually logged in before proceeding.
if (Auth::isLoggedIn()) {
    // Get the user's ID for logging *before* we destroy the session data.
    $userId = Auth::user('user_id');
    
    // Use our central logging service to create a clear audit trail of the logout event.
    LoggingService::log($userId, null, 'logout_success', 'User successfully logged out.');
}

// Use our central SessionManager to securely destroy the session.
// This single method handles unsetting variables, deleting the cookie, and destroying the session.
SessionManager::destroy();

// Use our central redirect utility to send the user to the login page.
Utils::redirect('login.php');