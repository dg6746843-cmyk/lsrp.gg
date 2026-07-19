<?php
session_start();
$chat_file = "admin_chat_messages.txt";
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged'])) {
    echo json_encode([]);
    exit;
}

$output = [];
if (file_exists($chat_file)) {
    $lines = file($chat_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    // Return last 40 lines to maintain ultra-fast performance metrics
    $lines = array_slice($lines, -40);
    foreach ($lines as $line) {
        $json = json_decode($line, true);
        if ($json) {
            $output[] = $json;
        }
    }
}
echo json_encode($output);
