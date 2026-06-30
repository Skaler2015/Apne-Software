<?php
// Quick check — no login needed, delete after use
$jsonPath = __DIR__ . '/tools-data.json';
header('Content-Type: text/plain; charset=utf-8');
echo "=== ApneSoftware Tools Check ===\n\n";
echo "File path: " . $jsonPath . "\n";
echo "File exists: " . (file_exists($jsonPath) ? 'YES' : 'NO') . "\n";
if(file_exists($jsonPath)){
    $size = filesize($jsonPath);
    echo "File size: " . number_format($size) . " bytes\n";
    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);
    echo "JSON valid: " . (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO — Error: ' . json_last_error_msg()) . "\n";
    echo "Tools count: " . count($data['tools'] ?? []) . "\n";
    echo "Categories count: " . count($data['categories'] ?? []) . "\n";
    if(!empty($data['tools'])){
        echo "\nFirst 5 tools:\n";
        foreach(array_slice($data['tools'], 0, 5) as $t){
            echo "  - " . $t['name'] . " (" . $t['category'] . ")\n";
        }
    }
}
echo "\n=== Done ===\n";
echo "(Delete this file after checking: public_html/assets/check.php)\n";
