<?php
// Simple healthcheck that doesn't require Laravel bootstrap.
// Used by Railway healthchecks to verify the web server is running.
header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'message' => 'Dial4Dough web server is running',
    'timestamp' => gmdate('c'),
]);
