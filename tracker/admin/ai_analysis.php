<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

define('CLAUDE_API_KEY', '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');

header('Content-Type: application/json');

if (empty(CLAUDE_API_KEY)) {
    echo json_encode(['error'=>'API_KEY_MISSING']);
    exit;
}

$cid = (int)($_GET['change_id'] ?? 0);
if (!$cid) { echo json_encode(['error'=>'Invalid ID']); exit; }

$row = $pdo->prepare("
    SELECT c.*, p.page_url, w.website_name
    FROM changes c
    LEFT JOIN pages p ON c.page_id=p.id
    LEFT JOIN websites w ON c.website_id=w.id
    WHERE c.id=?
");
$row->execute([$cid]);
$row = $row->fetch();
if (!$row) { echo json_encode(['error'=>'Not found']); exit; }

$old  = mb_substr(strip_tags($row['old_content'] ?? ''), 0, 2000);
$new  = mb_substr(strip_tags($row['new_content'] ?? ''), 0, 2000);
$url  = $row['page_url'] ?? '';
$site = $row['website_name'] ?? '';

$prompt = <<<PROMPT
You are an expert at analyzing Indian government job/exam website page changes.

Website: {$site}
Page URL: {$url}

=== OLD CONTENT ===
{$old}

=== NEW CONTENT ===
{$new}

Compare these two versions carefully and identify EVERY change — not just dates/age.

Look for ALL of these changes:
- New sections or content blocks added
- Removed sections  
- Changed vacancy/post numbers (e.g. "500 posts" changed to "750 posts")
- Changed salary/pay scale
- Changed eligibility criteria
- Changed qualification requirements
- New download links added (result, admit card, answer key, syllabus, cutoff, scorecard, merit list)
- Application status changes (started, closed, extended)
- Exam schedule changes (new dates, postponed, cancelled)
- Interview/document verification schedule
- New notices or corrigendum added
- Changed fee structure
- Changed exam pattern or syllabus
- Important notices or announcements
- Any other meaningful change

Respond ONLY in this JSON (no extra text):
{
  "summary": "precise 1-line summary of main change in English",
  "summary_hindi": "1 line Hindi mein",
  "change_type": "result_out|admit_card|answer_key|date_changed|vacancy_update|new_link|application_open|application_closed|exam_postponed|notice_added|salary_changed|eligibility_changed|new_content|minor_update",
  "important": true or false,
  "what_added": [
    "specific item 1 that is NEW in new content",
    "specific item 2"
  ],
  "what_removed": [
    "specific item that was in OLD but not in NEW"
  ],
  "what_changed": [
    "something: OLD VALUE → NEW VALUE"
  ],
  "key_info": {
    "new_links": ["link description 1", "link description 2"],
    "dates": ["date info 1", "date info 2"],
    "numbers": ["post count, salary, fee changes"],
    "notices": ["any new notices"]
  },
  "action_required": "specific action user should take (or empty)"
}
PROMPT;

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://api.anthropic.com/v1/messages',
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode([
        'model'      => 'claude-haiku-4-5-20251001',
        'max_tokens' => 1000,
        'messages'   => [['role'=>'user','content'=>$prompt]]
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: '.CLAUDE_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) { echo json_encode(['error'=>'Network: '.$curlError]); exit; }

$data = json_decode($response, true);

if ($httpCode === 401) { echo json_encode(['error'=>'Invalid API key']); exit; }
if ($httpCode === 429) { echo json_encode(['error'=>'Rate limit. Wait and retry.']); exit; }
if ($httpCode !== 200) {
    echo json_encode(['error'=>'API error '.$httpCode.': '.($data['error']['message']??'unknown')]);
    exit;
}

if (empty($data['content'][0]['text'])) {
    echo json_encode(['error'=>'Empty API response']);
    exit;
}

$text   = $data['content'][0]['text'];
$text   = preg_replace('/```json|```/', '', $text);
$result = json_decode(trim($text), true);

if (!$result) {
    preg_match('/\{.*\}/s', $text, $m);
    if ($m) $result = json_decode($m[0], true);
}

if (!$result) {
    echo json_encode(['error'=>'Parse error', 'raw'=>substr($text,0,300)]);
    exit;
}

// Auto-save as note
if (!empty($result['summary'])) {
    $note = $result['summary'];
    if (!empty($result['summary_hindi'])) $note .= ' | '.$result['summary_hindi'];
    try {
        $pdo->prepare("UPDATE changes SET note=? WHERE id=? AND (note IS NULL OR note='')")
            ->execute([$note, $cid]);
    } catch(Exception $e) {}
}

echo json_encode($result);
