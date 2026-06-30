<?php
/**
 * Smart Content Filter Library
 * Import karo: require_once 'content_filter.php';
 */

class SmartContentFilter {

    // ── Step 1: Remove structural noise ──────────────────
    public static function removeNoise($html) {
        if (!$html) return '';

        // Remove scripts, styles, noscript
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is',   '', $html);
        $html = preg_replace('/<noscript\b[^>]*>.*?<\/noscript>/is','', $html);

        // Remove structural noise tags completely
        foreach (['nav','header','footer','aside','form'] as $tag) {
            $html = preg_replace('/<'.$tag.'\b[^>]*>.*?<\/'.$tag.'>/is', '', $html);
        }

        // Remove noise divs by class/id pattern
        $noisePatterns = [
            'nav','navbar','menu','sidebar','footer','header','breadcrumb',
            'advertisement','banner','cookie','popup','modal','social',
            'share-btn','whatsapp','telegram','download-app','app-banner',
            'follow-us','subscription','newsletter','related-posts',
            'comment','widget','tags-list','author-bio','pagination',
        ];
        $noiseRegex = implode('|', $noisePatterns);
        for ($i = 0; $i < 3; $i++) { // Run multiple passes
            $html = preg_replace_callback(
                '/<(div|section|ul|aside|nav|header|footer)\b([^>]*)>(.*?)<\/\1>/is',
                function($m) use ($noiseRegex) {
                    if (preg_match('/(?:class|id)=["\'][^"\']*(?:'.$noiseRegex.')[^"\']*["\']/', $m[2])) {
                        return '';
                    }
                    return $m[0];
                },
                $html
            );
        }

        return $html;
    }

    // ── Step 2: Extract main content intelligently ────────
    public static function extractMain($html, $url = '') {
        $candidates = [];

        // Try article tag first (most reliable)
        if (preg_match('/<article\b[^>]*>(.*?)<\/article>/is', $html, $m)) {
            $candidates[] = ['text' => self::cleanText($m[1]), 'score' => 100];
        }

        // Try common content class patterns
        $contentClasses = [
            'post-content','entry-content','article-content','article-body',
            'post-body','content-body','td-post-content','jeg_post_content',
            'single-post-content','article__content','post__content',
            'the-content','main-content','page-content','content-area',
            'blog-content','news-content','story-content',
        ];
        foreach ($contentClasses as $cls) {
            if (preg_match('/<[^>]+class=["\'][^"\']*\b'.$cls.'\b[^"\']*["\'][^>]*>(.*?)<\/(?:div|article|section)>/is', $html, $m)) {
                $text = self::cleanText($m[1]);
                if (strlen($text) > 200) {
                    $candidates[] = ['text' => $text, 'score' => 90];
                    break;
                }
            }
        }

        // Try main tag
        if (preg_match('/<main\b[^>]*>(.*?)<\/main>/is', $html, $m)) {
            $text = self::cleanText($m[1]);
            if (strlen($text) > 200) $candidates[] = ['text' => $text, 'score' => 80];
        }

        // Try ID-based content divs
        $contentIds = ['content','main','post','article','entry','primary','post-content','the-post'];
        foreach ($contentIds as $id) {
            if (preg_match('/<div[^>]+id=["\']'.$id.'["\'][^>]*>(.*?)<\/div>/is', $html, $m)) {
                $text = self::cleanText($m[1]);
                if (strlen($text) > 200) { $candidates[] = ['text'=>$text,'score'=>75]; break; }
            }
        }

        // Pick best candidate
        if ($candidates) {
            usort($candidates, function($a,$b){ return $b['score'] - $a['score']; });
            $best = $candidates[0]['text'];
            if (strlen($best) > 150) return $best;
        }

        // Fallback: whole page stripped
        return self::cleanText($html);
    }

    // ── Step 3: Remove dynamic content ───────────────────
    public static function removeDynamic($text) {
        $patterns = [
            // Timestamps — all formats
            '/\b(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)\s+\d{1,2},?\s+\d{4}\s+\d{1,2}:\d{2}\s*[AP]M/i',
            '/\d{1,2}:\d{2}\s*[AP]M/i',
            '/\d+\s+(?:second|minute|hour|day|week|month)s?\s+ago/i',
            '/(?:Updated|Modified|Published)\s*[:\-]?\s*[\w\s,]+\d{4}/i',
            '/Post\s+Date\s*:\s*[\w\s,]+\d{4}[^<\n]*/i',

            // App download prompts
            '/Download\s+(?:the\s+)?(?:SarkariResult\s+)?(?:Mobile\s+)?App\s+Now[^.]*\.?/i',
            '/(?:Install|Get)\s+(?:Our\s+)?App[^.]*\./i',

            // Social follow
            '/(?:Follow|Join)\s+(?:Us|Our)[^.]+(?:WhatsApp|Telegram|Instagram|YouTube)[^.]*\./i',
            '/FOLLOW\s+US\s+ON[^.]+/i',
            '/Add\s+(?:FJA|Us)\s+(?:on|as)[^.]+/i',

            // Site-specific noise
            '/SarkariResult\.(?:com|com\.cm)[^\s]*/i',
            '/FreeJobAlert\.Com[^\s]*/i',
            '/As\s+Preferred\s+Source[^.]*\.?/i',

            // View/share counts
            '/\d{1,6}\s*(?:views?|shares?|comments?|likes?|reads?)\b/i',

            // Copyright
            '/©\s*\d{4}\s+[^.]+\./i',
            '/All\s+Rights?\s+Reserved\.?/i',

            // Author bylines
            '/\bBy\s+[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+\b/',
            '/Posted\s+by\s+[A-Z][a-z]+[^.]+/i',

            // WhatsApp/Telegram buttons text
            '/(?:WhatsApp|Telegram)\s+(?:Group|Channel|Join)[^.]*\.?/i',

            // Ad markers
            '/\[?(?:Advertisement|Sponsored|Ad|Promoted)\]?/i',
        ];

        foreach ($patterns as $p) {
            $text = preg_replace($p, ' ', $text);
        }

        return preg_replace('/\s+/', ' ', trim($text));
    }

    // ── Step 4: Normalize for comparison ─────────────────
    public static function normalize($text) {
        // Lowercase
        $text = mb_strtolower($text, 'UTF-8');
        // Remove HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Remove punctuation (keep alphanumeric + spaces)
        $text = preg_replace('/[^a-z0-9\s]/u', ' ', $text);
        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', trim($text));
        return $text;
    }

    // ── Step 5: Calculate meaningful diff ────────────────
    public static function getDiff($oldNorm, $newNorm) {
        if (!$oldNorm && !$newNorm) return 0.0;
        if (!$oldNorm || !$newNorm) return 100.0;
        if ($oldNorm === $newNorm) return 0.0;

        $sim = 0.0;
        similar_text($oldNorm, $newNorm, $sim);
        return round(100.0 - $sim, 2);
    }

    // ── Step 6: Semantic fingerprint ─────────────────────
    // Remove common words, keep only meaningful keywords
    public static function fingerprint($text) {
        $stopWords = ['the','a','an','is','are','was','were','be','been','being',
            'have','has','had','do','does','did','will','would','could','should',
            'may','might','must','can','this','that','these','those','it','its',
            'in','on','at','by','for','with','about','from','of','to','and','or',
            'but','not','no','yes','all','any','each','every','both','more','most',
            'other','some','such','than','then','too','very','just','been','also',
            'into','through','during','before','after','above','below','between',
            'out','off','over','under','again','further','once','here','there',
            'when','where','why','how','which','who','what','whom'];

        $words = preg_split('/\s+/', strtolower($text));
        $meaningful = [];
        foreach ($words as $w) {
            $w = preg_replace('/[^a-z0-9]/', '', $w);
            if (strlen($w) >= 4 && !in_array($w, $stopWords)) {
                $meaningful[$w] = true;
            }
        }
        ksort($meaningful);
        return implode(' ', array_keys($meaningful));
    }

    // ── Step 7: Detect change type ───────────────────────
    public static function detectChangeType($oldText, $newText, $url) {
        $combined = strtolower($url . ' ' . $newText);
        $oldLow   = strtolower($oldText);
        $newLow   = strtolower($newText);

        // Check what's NEW in new content vs old
        if (preg_match('/(final\s*result|result\s*out|result\s*declared|merit\s*list)/i', $newText)
            && !preg_match('/(final\s*result|result\s*out|result\s*declared|merit\s*list)/i', $oldText))
            return ['type'=>'Result', 'priority'=>10, 'important'=>true];

        if (preg_match('/(admit\s*card|hall\s*ticket).*(out|available|download|released)/i', $newText)
            && !preg_match('/(admit\s*card|hall\s*ticket).*(out|available)/i', $oldText))
            return ['type'=>'Admit Card', 'priority'=>9, 'important'=>true];

        if (preg_match('/(answer\s*key).*(out|released|available)/i', $newText)
            && !preg_match('/(answer\s*key).*(out|released)/i', $oldText))
            return ['type'=>'Answer Key', 'priority'=>8, 'important'=>true];

        if (preg_match('/(last\s*date.*extend|date\s*extended)/i', $newText))
            return ['type'=>'Date Extended', 'priority'=>8, 'important'=>true];

        if (preg_match('/(cut\s*off|cutoff).*(out|released)/i', $newText)
            && !preg_match('/(cut\s*off|cutoff).*(out|released)/i', $oldText))
            return ['type'=>'Cut Off', 'priority'=>7, 'important'=>true];

        if (preg_match('/(score\s*card|marks.*available)/i', $newText)
            && !preg_match('/(score\s*card|marks.*available)/i', $oldText))
            return ['type'=>'Score Card', 'priority'=>7, 'important'=>true];

        if (preg_match('/(apply\s*online.*(start|open)|application.*(start|open))/i', $newText)
            && !preg_match('/(apply\s*online.*(start|open))/i', $oldText))
            return ['type'=>'Application Open', 'priority'=>7, 'important'=>true];

        if (preg_match('/(vacancy.*increas|increas.*vacanc)/i', $newText))
            return ['type'=>'Vacancy Update', 'priority'=>6, 'important'=>true];

        if (preg_match('/(syllabus|exam\s*pattern).*(out|released)/i', $newText)
            && !preg_match('/(syllabus).*(out|released)/i', $oldText))
            return ['type'=>'Syllabus', 'priority'=>5, 'important'=>false];

        // URL-based fallback
        if (stripos($url,'result')!==false) return ['type'=>'Result Update', 'priority'=>6, 'important'=>true];
        if (stripos($url,'admit')!==false)  return ['type'=>'Admit Card', 'priority'=>5, 'important'=>true];
        if (stripos($url,'answer-key')!==false) return ['type'=>'Answer Key', 'priority'=>5, 'important'=>true];
        if (stripos($url,'notification')!==false) return ['type'=>'Notification', 'priority'=>4, 'important'=>false];
        if (stripos($url,'recruitment')!==false) return ['type'=>'Recruitment', 'priority'=>3, 'important'=>false];

        return ['type'=>'Update', 'priority'=>2, 'important'=>false];
    }

    // ── Helper ────────────────────────────────────────────
    private static function cleanText($html) {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/\s+/', ' ', trim($text));
    }

    // ── Full pipeline ─────────────────────────────────────
    public static function process($html, $url = '') {
        $cleaned   = self::removeNoise($html);
        $main      = self::extractMain($cleaned, $url);
        $noNoise   = self::removeDynamic($main);
        return $noNoise;
    }

    public static function shouldReport($oldRaw, $newRaw, $url, $threshold = 3.0) {
        $oldClean = self::removeDynamic(self::cleanText($oldRaw));
        $newClean = self::removeDynamic(self::cleanText($newRaw));

        $oldNorm = self::normalize($oldClean);
        $newNorm = self::normalize($newClean);

        // Identical after normalization
        if ($oldNorm === $newNorm) return ['report'=>false, 'reason'=>'identical_normalized', 'diff'=>0];

        // Fingerprint comparison
        $oldFP = self::fingerprint($oldNorm);
        $newFP = self::fingerprint($newNorm);
        if ($oldFP === $newFP) return ['report'=>false, 'reason'=>'identical_fingerprint', 'diff'=>0];

        $diff = self::getDiff($oldNorm, $newNorm);

        if ($diff < $threshold) return ['report'=>false, 'reason'=>'below_threshold', 'diff'=>$diff];

        return ['report'=>true, 'reason'=>'meaningful_change', 'diff'=>$diff];
    }
}
