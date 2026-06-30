<?php
// Auto scan runner — called by cron job
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once 'content_filter.php';

try {
    $sched = $pdo->query("SELECT * FROM scan_schedule WHERE id=1")->fetch();
    if (!$sched || !$sched['is_active']) {
        echo "Schedule disabled"; exit;
    }
    // Check if it's time
    if ($sched['next_run'] && strtotime($sched['next_run']) > time()) {
        echo "Not yet time. Next: " . $sched['next_run']; exit;
    }
    // Update timestamps
    $interval = (int)$sched['interval_hours'];
    $nextRun  = date('Y-m-d H:i:s', strtotime("+{$interval} hours"));
    $pdo->prepare("UPDATE scan_schedule SET last_run=NOW(), next_run=? WHERE id=1")->execute([$nextRun]);

    // Create scan_log table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS scan_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ran_at DATETIME DEFAULT NOW(),
            pages_scanned INT DEFAULT 0,
            changes_found INT DEFAULT 0,
            errors INT DEFAULT 0
        )");
    } catch(Exception $e) {}

    // Run the actual scan inline
    $pages = $pdo->query("
        SELECT p.*, w.website_name,
               CASE
                 WHEN p.page_url LIKE '%result%' OR p.page_url LIKE '%admit%'
                   OR p.page_url LIKE '%answer-key%' OR p.page_url LIKE '%notification%'
                 THEN 1 ELSE 2
               END as priority
        FROM pages p INNER JOIN websites w ON p.website_id = w.id
        WHERE (w.status='active' OR w.status='1')
        AND p.page_url NOT LIKE '%sitemap%'
        ORDER BY priority ASC, p.id DESC
        LIMIT 200
    ")->fetchAll(PDO::FETCH_ASSOC);

    $scanned=0; $changes=0; $errors=0;

    foreach ($pages as $page) {
        $scanned++;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $page['page_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
        ]);
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!$html || $code >= 400) { $errors++; continue; }

        // Smart content extraction
        $content = SmartContentFilter::process($html, $page['page_url']);
        $newHash = md5(SmartContentFilter::normalize(SmartContentFilter::removeDynamic($content)));
        $oldHash = isset($page['content_hash']) ? $page['content_hash'] : '';

        if ($oldHash && $newHash !== $oldHash) {
            $oldContent = isset($page['content']) ? $page['content'] : '';
            $sim = 0;
            similar_text(strtolower($oldContent), strtolower($content), $sim);
            $fr = SmartContentFilter::shouldReport($oldContent, $content, $page['page_url'], 3.0);
            if ($fr['report']) {
                $pdo->prepare("INSERT INTO changes (website_id,page_id,change_type,old_content,new_content,detected_at) VALUES(?,?,'CONTENT_CHANGED',?,?,NOW())")
                    ->execute([$page['website_id'],$page['id'],$oldContent,$content]);
                $changeId = $pdo->lastInsertId();
                $changes++;

                // Auto AI Summary for important pages
                $isImp = preg_match('/(result|admit|answer-key|merit|cutoff|scorecard|notification)/i', $page['page_url']);
                if ($isImp && $changeId) {
                    try {
                        $apiKey = $pdo->query("SELECT setting_value FROM tracker_settings WHERE setting_key='claude_api_key'")->fetchColumn();
                        if (!$apiKey) {
                            // Try from ai_analysis.php constant
                            $apiKey = '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';
                        }
                        if ($apiKey) {
                            $oldSnip = mb_substr(strip_tags($oldContent), 0, 1000);
                            $newSnip = mb_substr(strip_tags($content), 0, 1000);
                            $prompt  = "Indian govt job page changed. Old: {$oldSnip}
New: {$newSnip}
What changed? Reply in 1 line English + 1 line Hindi. Format: EN: ...
HI: ...";
                            $aiCh = curl_init('https://api.anthropic.com/v1/messages');
                            curl_setopt_array($aiCh, [
                                CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15,
                                CURLOPT_POSTFIELDS=>json_encode(['model'=>'claude-haiku-4-5-20251001','max_tokens'=>150,'messages'=>[['role'=>'user','content'=>$prompt]]]),
                                CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-api-key: '.$apiKey,'anthropic-version: 2023-06-01'],
                            ]);
                            $aiResp = curl_exec($aiCh); curl_close($aiCh);
                            $aiData = json_decode($aiResp, true);
                            if (!empty($aiData['content'][0]['text'])) {
                                $note = trim($aiData['content'][0]['text']);
                                $pdo->prepare("UPDATE changes SET note=? WHERE id=?")->execute([$note, $changeId]);
                            }
                        }
                    } catch(Exception $eAi) {}
                }
            }
        }
        $pdo->prepare("UPDATE pages SET content=?,content_hash=?,last_scan=NOW() WHERE id=?")
            ->execute([$content,$newHash,$page['id']]);
    }
    $pdo->prepare("INSERT INTO scan_log (pages_scanned,changes_found,errors) VALUES(?,?,?)")
        ->execute([$scanned,$changes,$errors]);
    echo "Done — scanned:{$scanned} changes:{$changes} errors:{$errors}";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
