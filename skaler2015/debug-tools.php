<?php
require_once __DIR__ . '/includes/auth.php';
$jsonPath = __DIR__ . '/../assets/tools-data.json';
$raw = file_exists($jsonPath) ? file_get_contents($jsonPath) : 'FILE NOT FOUND';
$data = json_decode($raw, true);
header('Content-Type: text/plain');
echo "File exists: " . (file_exists($jsonPath) ? 'YES' : 'NO') . "\n";
echo "JSON valid: " . (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO - ' . json_last_error_msg()) . "\n";
echo "Total tools: " . count($data['tools'] ?? []) . "\n";
echo "Categories: " . count($data['categories'] ?? []) . "\n";
echo "File size: " . filesize($jsonPath) . " bytes\n";
echo "First tool: " . ($data['tools'][0]['name'] ?? 'NONE') . "\n";
echo "\nFirst 500 chars of file:\n";
echo substr($raw, 0, 500);
