<?php
/**
 * logout_beacon.php
 * Called via navigator.sendBeacon() when the user closes the tab/window.
 * Also handles explicit AJAX logout requests.
 * Returns 204 No Content — no body needed.
 */
require_once '../src/auth.php';
startSession();

if (!empty($_SESSION['user_id'])) {
    logout();
}

http_response_code(204);
