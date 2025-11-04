<?php
/**
 * Security Headers
 * Include this file at the top of all public-facing pages to add security headers
 */

// Content Security Policy
header("Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
    "font-src 'self' https://fonts.gstatic.com; " .
    "img-src 'self' data: https:; " .
    "connect-src 'self' https://api2.authorize.net; " .
    "frame-src 'self' https://accept.authorize.net; " .
    "base-uri 'self'; " .
    "form-action 'self' https://accept.authorize.net;"
);

// Prevent clickjacking
header("X-Frame-Options: SAMEORIGIN");

// Prevent MIME type sniffing
header("X-Content-Type-Options: nosniff");

// Enable browser XSS protection
header("X-XSS-Protection: 1; mode=block");

// Referrer Policy
header("Referrer-Policy: strict-origin-when-cross-origin");

// Permissions Policy (formerly Feature Policy)
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");

// Force HTTPS in production (uncomment when HTTPS is enabled)
if (config('SESSION_COOKIE_SECURE', false)) {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
}
