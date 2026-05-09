<?php
/**
 * POST /api/frontend/logout.php
 */

require_once __DIR__ . '/../../includes/helpers.php';
setCorsHeaders();

startSession();
session_destroy();

jsonResponse(true, 'Logout berhasil');
