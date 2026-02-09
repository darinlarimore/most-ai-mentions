<?php

namespace App\Services;

/**
 * Detects AI-generated content using heuristic analysis.
 *
 * Uses free, local heuristics to estimate how much content on a page
 * appears to be AI-generated. No paid API required.
 *
 * Heuristic signals:
 * - Overuse of filler phrases common in LLM output
 * - Repetitive sentence structure patterns
 * - Excessive hedging language
 * - Unnaturally uniform sentence length
 * - Common LLM phrases and patterns
 */
class AiContentDetectionService
{
    /** Phrases that appear disproportionately in AI-generated text */
    public const AI_PHRASES = [
        'in today\'s rapidly evolving',
        'in the ever-evolving',
        'it\'s important to note',
        'it is important to note',
        'it\'s worth noting',
        'in this article, we\'ll',
        'let\'s dive in',
        'let\'s explore',
        'dive deep into',
        'at the end of the day',
        'leverage cutting-edge',
        'harness the power of',
        'unlock the potential',
        'unlock the full potential',
        'game-changer',
        'game changer',
        'revolutionize',
        'revolutionizing',
        'seamlessly integrate',
        'seamless integration',
        'streamline your',
        'elevate your',
        'empower your',
        'supercharge your',
        'in conclusion',
        'to summarize',
        'in summary',
        'whether you\'re a',
        'look no further',
        'not only... but also',
        'comprehensive guide',
        'comprehensive solution',
        'comprehensive overview',
        'robust and scalable',
        'cutting-edge technology',
        'state-of-the-art',
        'next-generation',
        'best-in-class',
        'world-class',
        'delve into',
        'delve deeper',
        'tapestry',
        'landscape',
        'paradigm',
        'synergy',
        'holistic approach',
        'foster innovation',
        'navigate the complexities',
        'digital transformation',
    ];

    /** Hedging phrases overused by LLMs */
    public const HEDGING_PHRASES = [
        'it depends on',
        'there are several',
        'there are many',
        'there are various',
        'however, it\'s important',
        'on the other hand',
        'that being said',
        'having said that',
        'with that in mind',
        'it\'s worth mentioning',
        'arguably',
        'generally speaking',
        'for the most part',
    ];

    /**
     * Analyze text content and return an AI-generation likelihood score.
     *
     * @return array{score: int, signals: array<string, mixed>, breakdown: array<string, int>}
     */
    public function analyze(string $text): array
    {
        if (strlen($text) < 100) {
            return ['score' => 0, 'signals' => [], 'breakdown' => []];
        }

        $text = strtolower($text);
        $sentences = $this->splitSentences($text);

        $phraseScore = $this->scorePhraseMatches($text);
        $hedgingScore = $this->scoreHedging($text);
        $uniformityScore = $this->scoreSentenceUniformity($sentences);
        $repetitionScore = $this->scoreRepetitiveStructure($sentences);
        $buzzwordDensity = $this->scoreBuzzwordDensity($text);

        $totalScore = min(100, (int) (
            $phraseScore * 0.30 +
            $hedgingScore * 0.15 +
            $uniformityScore * 0.20 +
            $repetitionScore * 0.15 +
            $buzzwordDensity * 0.20
        ));

        return [
            'score' => $totalScore,
            'signals' => [
                'ai_phrase_matches' => $this->findMatchedPhrases($text),
                'sentence_count' => count($sentences),
                'avg_sentence_length' => $this->averageSentenceLength($sentences),
            ],
            'breakdown' => [
                'phrase_score' => $phraseScore,
                'hedging_score' => $hedgingScore,
                'uniformity_score' => $uniformityScore,
                'repetition_score' => $repetitionScore,
                'buzzword_density' => $buzzwordDensity,
            ],
        ];
    }

    private function scorePhraseMatches(string $text): int
    {
        $matches = 0;
        foreach (self::AI_PHRASES as $phrase) {
            $matches += substr_count($text, $phrase);
        }

        return min(100, $matches * 12);
    }

    private function scoreHedging(string $text): int
    {
        $matches = 0;
        foreach (self::HEDGING_PHRASES as $phrase) {
            $matches += substr_count($text, $phrase);
        }

        return min(100, $matches * 15);
    }

    private function scoreSentenceUniformity(array $sentences): int
    {
        if (count($sentences) < 5) {
            return 0;
        }

        $lengths = array_map('strlen', $sentences);
        $avg = array_sum($lengths) / count($lengths);

        if ($avg === 0.0) {
            return 0;
        }

        $variance = array_sum(array_map(fn ($l) => ($l - $avg) ** 2, $lengths)) / count($lengths);
        $cv = sqrt($variance) / $avg; // coefficient of variation

        // AI text tends to have very uniform sentence lengths (low CV)
        // Human text is more varied (higher CV)
        if ($cv < 0.2) {
            return 90;
        }
        if ($cv < 0.3) {
            return 60;
        }
        if ($cv < 0.4) {
            return 30;
        }

        return 0;
    }

    private function scoreRepetitiveStructure(array $sentences): int
    {
        if (count($sentences) < 5) {
            return 0;
        }

        $starters = [];
        foreach ($sentences as $sentence) {
            $words = explode(' ', trim($sentence));
            if (count($words) >= 2) {
                $starter = $words[0].' '.$words[1];
                $starters[$starter] = ($starters[$starter] ?? 0) + 1;
            }
        }

        $repeated = array_filter($starters, fn ($count) => $count > 2);
        $repetitionRate = count($repeated) > 0
            ? array_sum($repeated) / count($sentences)
            : 0;

        return min(100, (int) ($repetitionRate * 200));
    }

    private function scoreBuzzwordDensity(string $text): int
    {
        $buzzwords = [
            'innovative', 'leverage', 'synergy', 'paradigm', 'disruptive',
            'scalable', 'robust', 'seamless', 'cutting-edge', 'state-of-the-art',
            'holistic', 'empower', 'optimize', 'streamline', 'transform',
            'revolutionize', 'next-generation', 'best-in-class', 'world-class',
            'mission-critical', 'enterprise-grade', 'future-proof', 'turnkey',
        ];

        $wordCount = str_word_count($text);
        if ($wordCount === 0) {
            return 0;
        }

        $buzzCount = 0;
        foreach ($buzzwords as $word) {
            $buzzCount += substr_count($text, $word);
        }

        $density = $buzzCount / $wordCount * 100;

        return min(100, (int) ($density * 50));
    }

    /**
     * @return list<string>
     */
    private function findMatchedPhrases(string $text): array
    {
        $matched = [];
        foreach (self::AI_PHRASES as $phrase) {
            if (str_contains($text, $phrase)) {
                $matched[] = $phrase;
            }
        }

        return $matched;
    }

    /**
     * @return list<string>
     */
    private function splitSentences(string $text): array
    {
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter(
            array_map('trim', $sentences ?: []),
            fn ($s) => strlen($s) > 10
        ));
    }

    private function averageSentenceLength(array $sentences): float
    {
        if (count($sentences) === 0) {
            return 0;
        }

        return array_sum(array_map('strlen', $sentences)) / count($sentences);
    }
}
