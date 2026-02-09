<?php

namespace App\Services;

class HtmlAnnotationService
{
    /**
     * Annotate raw HTML with highlighted AI keyword mentions and a floating score panel.
     *
     * @param  array<int, array{text: string, font_size: int|float, has_animation: bool, has_glow: bool, context: string}>  $mentionDetails
     * @param  array{total_score: int, mention_score: int, font_size_score: int, animation_score: int, visual_effects_score: int, lighthouse_perf_bonus: int, lighthouse_a11y_bonus: int, ai_mention_count: int, animation_count: int, glow_effect_count: int, rainbow_border_count: int}  $scoreBreakdown
     * @param  array<int, array{url: string, confidence: int, signals: list<string>, breakdown: array<string, int>}>  $aiImageDetails
     */
    public function annotate(string $html, array $mentionDetails, array $scoreBreakdown, array $aiImageDetails = []): string
    {
        if (trim($html) === '') {
            return '';
        }

        $html = $this->highlightKeywords($html);
        $html = $this->highlightAiImages($html, $aiImageDetails);
        $html = $this->injectOverlay($html, $mentionDetails, $scoreBreakdown);

        return $html;
    }

    /**
     * Find AI keywords in the visible text and wrap them with highlight markup.
     */
    private function highlightKeywords(string $html): string
    {
        $keywords = HypeScoreCalculator::AI_KEYWORDS;

        // Sort by length descending so longer phrases match first
        usort($keywords, fn (string $a, string $b) => strlen($b) <=> strlen($a));

        // Build a regex that matches any keyword, case-insensitive,
        // but only outside of HTML tags.
        $escapedKeywords = array_map(fn (string $kw) => preg_quote($kw, '/'), $keywords);
        $pattern = '/('.implode('|', $escapedKeywords).')/i';

        // Split HTML into tags and text nodes, only highlight in text nodes
        $parts = preg_split('/(<[^>]*>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return $html;
        }

        $result = '';
        $insideScript = false;
        $insideStyle = false;

        foreach ($parts as $part) {
            // Track script/style tags to avoid highlighting inside them
            if (preg_match('/<script\b/i', $part)) {
                $insideScript = true;
            }
            if (preg_match('/<\/script>/i', $part)) {
                $insideScript = false;
                $result .= $part;

                continue;
            }
            if (preg_match('/<style\b/i', $part)) {
                $insideStyle = true;
            }
            if (preg_match('/<\/style>/i', $part)) {
                $insideStyle = false;
                $result .= $part;

                continue;
            }

            // If inside a tag, script, or style, pass through unchanged
            if ($part[0] === '<' || $insideScript || $insideStyle) {
                $result .= $part;

                continue;
            }

            // Highlight keywords in text nodes
            $result .= preg_replace(
                $pattern,
                '<mark class="maim-highlight" data-maim-keyword="$1">$1</mark>',
                $part
            ) ?? $part;
        }

        return $result;
    }

    /**
     * Wrap detected AI-generated <img> tags with a red border and confidence badge.
     *
     * @param  array<int, array{url: string, confidence: int, signals: list<string>, breakdown: array<string, int>}>  $aiImageDetails
     */
    private function highlightAiImages(string $html, array $aiImageDetails): string
    {
        if (empty($aiImageDetails)) {
            return $html;
        }

        // Build a lookup of detected image URLs
        $detectedUrls = [];
        foreach ($aiImageDetails as $detail) {
            $detectedUrls[$detail['url']] = $detail['confidence'];
        }

        // Find and wrap <img> tags whose src matches detected URLs
        return preg_replace_callback('/<img\b[^>]*>/is', function ($match) use ($detectedUrls) {
            $tag = $match[0];

            // Extract src attribute
            if (preg_match('/\bsrc=["\']([^"\']*)["\']/', $tag, $srcMatch)) {
                $src = $srcMatch[1];

                foreach ($detectedUrls as $url => $confidence) {
                    if ($src === $url || str_contains($url, $src) || str_contains($src, basename(parse_url($url, PHP_URL_PATH) ?? ''))) {
                        $borderColor = $confidence >= 70 ? '#ef4444' : ($confidence >= 40 ? '#f97316' : '#eab308');

                        return '<div class="maim-ai-image-wrapper" style="position:relative;display:inline-block;border:3px solid '.$borderColor.';border-radius:6px;overflow:hidden;">'
                            .$tag
                            .'<span style="position:absolute;top:4px;right:4px;background:'.$borderColor.';color:white;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:700;font-family:system-ui,sans-serif;z-index:1;">'
                            .$confidence.'% AI</span></div>';
                    }
                }
            }

            return $tag;
        }, $html) ?? $html;
    }

    /**
     * Inject the CSS overlay stylesheet and floating score panel into the HTML.
     *
     * @param  array<int, array{text: string, font_size: int|float, has_animation: bool, has_glow: bool, context: string}>  $mentionDetails
     * @param  array<string, mixed>  $scoreBreakdown
     */
    private function injectOverlay(string $html, array $mentionDetails, array $scoreBreakdown): string
    {
        $css = $this->buildOverlayCss();
        $panel = $this->buildScorePanel($mentionDetails, $scoreBreakdown);

        // Inject CSS before </head> or at the start
        if (stripos($html, '</head>') !== false) {
            $html = str_ireplace('</head>', $css.'</head>', $html);
        } else {
            $html = $css.$html;
        }

        // Inject panel before </body> or at the end
        if (stripos($html, '</body>') !== false) {
            $html = str_ireplace('</body>', $panel.'</body>', $html);
        } else {
            $html .= $panel;
        }

        return $html;
    }

    private function buildOverlayCss(): string
    {
        return <<<'CSS'
<style id="maim-overlay-styles">
.maim-highlight {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    color: #1a1a1a !important;
    padding: 1px 4px;
    border-radius: 3px;
    font-weight: bold;
    box-shadow: 0 0 8px rgba(251, 191, 36, 0.5);
    cursor: pointer;
    position: relative;
    transition: box-shadow 0.2s ease;
}
.maim-highlight:hover {
    box-shadow: 0 0 16px rgba(251, 191, 36, 0.8), 0 0 30px rgba(251, 191, 36, 0.4);
}
.maim-highlight:hover::after {
    content: '🤖 +10 pts • "' attr(data-maim-keyword) '"';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #1a1a2e;
    color: #fbbf24;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    z-index: 999999;
    pointer-events: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}
#maim-score-panel {
    position: fixed;
    top: 12px;
    right: 12px;
    width: 300px;
    max-height: calc(100vh - 24px);
    overflow-y: auto;
    background: #1a1a2e;
    color: #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    z-index: 999999;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
    font-size: 13px;
    line-height: 1.5;
    box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    border: 1px solid rgba(251, 191, 36, 0.3);
}
#maim-score-panel h2 {
    margin: 0 0 12px;
    font-size: 16px;
    font-weight: 700;
    color: #fbbf24;
    display: flex;
    align-items: center;
    gap: 6px;
}
#maim-score-panel .maim-total {
    font-size: 36px;
    font-weight: 800;
    text-align: center;
    margin: 8px 0 16px;
    background: linear-gradient(135deg, #fbbf24, #ef4444);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
#maim-score-panel .maim-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
#maim-score-panel .maim-row:last-child {
    border-bottom: none;
}
#maim-score-panel .maim-label {
    color: #94a3b8;
    font-size: 12px;
}
#maim-score-panel .maim-value {
    font-weight: 600;
    font-size: 13px;
}
#maim-score-panel .maim-value.positive { color: #4ade80; }
#maim-score-panel .maim-value.neutral { color: #94a3b8; }
#maim-score-panel .maim-section {
    margin-top: 12px;
    padding-top: 8px;
    border-top: 1px solid rgba(251, 191, 36, 0.2);
}
#maim-score-panel .maim-section-title {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #fbbf24;
    margin-bottom: 6px;
}
#maim-score-panel .maim-toggle {
    position: absolute;
    top: 12px;
    right: 12px;
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 16px;
    padding: 2px;
    line-height: 1;
}
#maim-score-panel .maim-toggle:hover {
    color: #e2e8f0;
}
#maim-score-panel.collapsed {
    width: auto;
    max-height: none;
}
#maim-score-panel.collapsed .maim-body {
    display: none;
}
#maim-mention-list {
    max-height: 200px;
    overflow-y: auto;
    margin-top: 6px;
}
#maim-mention-list .maim-mention-item {
    background: rgba(255,255,255,0.05);
    border-radius: 6px;
    padding: 6px 8px;
    margin-bottom: 4px;
    font-size: 11px;
}
#maim-mention-list .maim-mention-text {
    font-weight: 600;
    color: #fbbf24;
}
#maim-mention-list .maim-mention-meta {
    color: #64748b;
    font-size: 10px;
    margin-top: 2px;
}
.maim-badge {
    display: inline-block;
    padding: 1px 6px;
    border-radius: 9999px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 4px;
}
.maim-badge-animated { background: #7c3aed; color: white; }
.maim-badge-glow { background: #d97706; color: white; }
</style>
CSS;
    }

    /**
     * @param  array<int, array{text: string, font_size: int|float, has_animation: bool, has_glow: bool, context: string}>  $mentionDetails
     * @param  array<string, mixed>  $scoreBreakdown
     */
    private function buildScorePanel(array $mentionDetails, array $scoreBreakdown): string
    {
        $totalScore = (int) ($scoreBreakdown['total_score'] ?? 0);
        $mentionScore = (int) ($scoreBreakdown['mention_score'] ?? 0);
        $fontSizeScore = (int) ($scoreBreakdown['font_size_score'] ?? 0);
        $animationScore = (int) ($scoreBreakdown['animation_score'] ?? 0);
        $visualEffectsScore = (int) ($scoreBreakdown['visual_effects_score'] ?? 0);
        $perfBonus = (int) ($scoreBreakdown['lighthouse_perf_bonus'] ?? 0);
        $a11yBonus = (int) ($scoreBreakdown['lighthouse_a11y_bonus'] ?? 0);
        $mentionCount = (int) ($scoreBreakdown['ai_mention_count'] ?? count($mentionDetails));
        $animationCount = (int) ($scoreBreakdown['animation_count'] ?? 0);
        $glowCount = (int) ($scoreBreakdown['glow_effect_count'] ?? 0);
        $rainbowCount = (int) ($scoreBreakdown['rainbow_border_count'] ?? 0);
        $aiImageCount = (int) ($scoreBreakdown['ai_image_count'] ?? 0);
        $aiImageBonus = (int) ($scoreBreakdown['ai_image_hype_bonus'] ?? 0);

        $mentionItems = '';
        foreach (array_slice($mentionDetails, 0, 50) as $mention) {
            $text = htmlspecialchars($mention['text'] ?? '', ENT_QUOTES, 'UTF-8');
            $fontSize = (int) ($mention['font_size'] ?? 16);
            $context = htmlspecialchars(mb_substr($mention['context'] ?? '', 0, 80), ENT_QUOTES, 'UTF-8');
            $badges = '';
            if (! empty($mention['has_animation'])) {
                $badges .= '<span class="maim-badge maim-badge-animated">animated</span>';
            }
            if (! empty($mention['has_glow'])) {
                $badges .= '<span class="maim-badge maim-badge-glow">glow</span>';
            }
            $mentionItems .= <<<HTML
<div class="maim-mention-item">
    <div><span class="maim-mention-text">{$text}</span>{$badges}</div>
    <div class="maim-mention-meta">{$fontSize}px &middot; {$context}</div>
</div>
HTML;
        }

        $remainingCount = max(0, count($mentionDetails) - 50);
        $remainingNote = $remainingCount > 0 ? "<div style='color:#64748b;font-size:10px;text-align:center;padding:4px;'>+ {$remainingCount} more mentions</div>" : '';

        return <<<HTML
<div id="maim-score-panel">
    <button class="maim-toggle" onclick="this.parentElement.classList.toggle('collapsed')" title="Toggle panel">&times;</button>
    <h2>🤖 Hype Score</h2>
    <div class="maim-body">
        <div class="maim-total">{$totalScore}</div>

        <div class="maim-section">
            <div class="maim-section-title">Score Breakdown</div>
            <div class="maim-row">
                <span class="maim-label">AI Mentions ({$mentionCount})</span>
                <span class="maim-value positive">+{$mentionScore}</span>
            </div>
            <div class="maim-row">
                <span class="maim-label">Font Size Bonus</span>
                <span class="maim-value positive">+{$fontSizeScore}</span>
            </div>
            <div class="maim-row">
                <span class="maim-label">Animations ({$animationCount})</span>
                <span class="maim-value positive">+{$animationScore}</span>
            </div>
            <div class="maim-row">
                <span class="maim-label">Visual Effects</span>
                <span class="maim-value positive">+{$visualEffectsScore}</span>
            </div>
            <div class="maim-row">
                <span class="maim-label">Glow Effects ({$glowCount})</span>
                <span class="maim-value neutral">(included above)</span>
            </div>
            <div class="maim-row">
                <span class="maim-label">Rainbow Borders ({$rainbowCount})</span>
                <span class="maim-value neutral">(included above)</span>
            </div>
            <div class="maim-row">
                <span class="maim-label">Lighthouse Perf Bonus</span>
                <span class="maim-value positive">+{$perfBonus}</span>
            </div>
            <div class="maim-row">
                <span class="maim-label">Lighthouse A11y Bonus</span>
                <span class="maim-value positive">+{$a11yBonus}</span>
            </div>
            <div class="maim-row">
                <span class="maim-label">AI Images ({$aiImageCount})</span>
                <span class="maim-value positive">+{$aiImageBonus}</span>
            </div>
        </div>

        <div class="maim-section">
            <div class="maim-section-title">AI Mentions Found</div>
            <div id="maim-mention-list">
                {$mentionItems}
                {$remainingNote}
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var highlights = document.querySelectorAll('.maim-highlight');
    var counter = document.createElement('div');
    counter.style.cssText = 'position:fixed;bottom:12px;right:12px;background:#1a1a2e;color:#fbbf24;padding:8px 14px;border-radius:8px;font-family:system-ui;font-size:12px;font-weight:600;z-index:999999;box-shadow:0 4px 12px rgba(0,0,0,0.3);border:1px solid rgba(251,191,36,0.3);';
    counter.textContent = highlights.length + ' AI keywords highlighted';
    document.body.appendChild(counter);
})();
</script>
HTML;
    }
}
