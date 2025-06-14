<?php
// utils.php

function logEvent(string $message): void {
    $logDir = __DIR__ . '/../storage/logs/';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $file = $logDir . 'app_' . date('Y-m-d') . '.log';
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;

    file_put_contents($file, $entry, FILE_APPEND);
}
