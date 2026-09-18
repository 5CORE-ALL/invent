<?php

namespace App\Services\Lqs;

class LqsShopifySeoScorer
{
    public const GOOD = 'good';
    public const OK = 'ok';
    public const BAD = 'bad';
    public const NA = 'na';

    /**
     * @param  array{
     *     title?: ?string,
     *     seo_title?: ?string,
     *     seo_description?: ?string,
     *     body_html?: ?string,
     *     keyphrase?: ?string,
     *     image_alts?: list<string>
     * }  $input
     * @return array{
     *     seo_score: ?int,
     *     seo_rating: string,
     *     readability_score: ?int,
     *     readability_rating: string,
     *     findings: list<string>
     * }
     */
    public function score(array $input): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        $seoTitle = trim((string) ($input['seo_title'] ?? ''));
        $seoDescription = trim((string) ($input['seo_description'] ?? ''));
        $bodyHtml = (string) ($input['body_html'] ?? '');
        $keyphrase = $this->normalizeKeyphrase($input['keyphrase'] ?? null);
        $imageAlts = array_values(array_filter(array_map('strval', $input['image_alts'] ?? [])));

        $plain = $this->plainText($bodyHtml);
        $seo = $this->scoreSeo($title, $seoTitle, $seoDescription, $plain, $keyphrase, $imageAlts);
        $read = $this->scoreReadability($bodyHtml, $plain);

        return [
            'seo_score' => $seo['score'],
            'seo_rating' => $seo['rating'],
            'readability_score' => $read['score'],
            'readability_rating' => $read['rating'],
            'findings' => array_values(array_filter(array_merge($seo['findings'], $read['findings']))),
        ];
    }

    public static function rating(?int $score, bool $analyzed): string
    {
        if (! $analyzed || $score === null) {
            return self::NA;
        }
        if ($score >= 71) {
            return self::GOOD;
        }
        if ($score >= 41) {
            return self::OK;
        }

        return self::BAD;
    }

    public static function ratingLabel(string $rating): string
    {
        return match ($rating) {
            self::GOOD => 'Good',
            self::OK => 'OK',
            self::BAD => 'Needs improvement',
            default => 'Not analyzed',
        };
    }

    public function extractKeyphrase(?array $yoastPayload): string
    {
        if (! is_array($yoastPayload) || $yoastPayload === []) {
            return '';
        }

        $keys = [
            'primary_focus_keyword',
            'primaryFocusKeyword',
            'focus_keyphrase',
            'focusKeyphrase',
            'focus_keyword',
            'focusKeyword',
            'keyphrase',
            'keyword',
            'primary_keyphrase',
        ];

        foreach ($keys as $key) {
            $value = $this->normalizeKeyphrase($yoastPayload[$key] ?? null);
            if ($value !== '') {
                return $value;
            }
        }

        foreach (['keyphrases', 'keywords', 'focus_keyphrases'] as $listKey) {
            $list = $yoastPayload[$listKey] ?? null;
            if (is_array($list) && $list !== []) {
                $first = $list[0] ?? null;
                if (is_array($first)) {
                    $value = $this->normalizeKeyphrase($first['keyphrase'] ?? $first['keyword'] ?? $first['value'] ?? null);
                } else {
                    $value = $this->normalizeKeyphrase($first);
                }
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    /**
     * @return array{score: ?int, rating: string, findings: list<string>}
     */
    private function scoreSeo(
        string $title,
        string $seoTitle,
        string $seoDescription,
        string $plain,
        string $keyphrase,
        array $imageAlts
    ): array {
        $findings = [];
        if ($keyphrase === '') {
            $findings[] = 'SEO not analyzed: no Yoast focus keyphrase.';

            return ['score' => null, 'rating' => self::NA, 'findings' => $findings];
        }

        $score = 0;
        $effectiveTitle = $seoTitle !== '' ? $seoTitle : $title;
        $titleHas = $this->containsPhrase($title, $keyphrase);
        $seoTitleHas = $this->containsPhrase($effectiveTitle, $keyphrase);
        $descHas = $this->containsPhrase($seoDescription, $keyphrase);
        $introHas = $this->containsPhrase(mb_substr($plain, 0, 400), $keyphrase);
        $density = $this->keyphraseDensity($plain, $keyphrase);

        $score += $titleHas ? 18 : 0;
        if (! $titleHas) {
            $findings[] = 'Add the focus keyphrase to the product title.';
        }

        $score += $seoTitleHas ? 18 : 0;
        if ($seoTitleHas && $this->startsWithPhrase($effectiveTitle, $keyphrase)) {
            $score += 4;
        } elseif (! $seoTitleHas) {
            $findings[] = 'Add the focus keyphrase to the SEO title.';
        }

        $score += $descHas ? 14 : 0;
        if (! $descHas) {
            $findings[] = 'Use the focus keyphrase in the meta description.';
        }

        $score += $introHas ? 10 : 0;
        if (! $introHas) {
            $findings[] = 'Mention the focus keyphrase in the first paragraph.';
        }

        if ($density >= 0.5 && $density <= 3.0) {
            $score += 10;
        } elseif ($plain !== '') {
            $findings[] = 'Keyphrase density is '.number_format($density, 1).'%; aim for 0.5–3%.';
            $score += $density > 0 && $density < 5 ? 5 : 0;
        }

        $titleLen = mb_strlen($effectiveTitle);
        if ($titleLen >= 30 && $titleLen <= 60) {
            $score += 10;
        } elseif ($titleLen >= 20 && $titleLen <= 70) {
            $score += 5;
            $findings[] = 'SEO title length is '.$titleLen.' characters; 30–60 is ideal.';
        } else {
            $findings[] = $effectiveTitle === ''
                ? 'Missing SEO title.'
                : 'SEO title length is '.$titleLen.' characters; 30–60 is ideal.';
        }

        $descLen = mb_strlen($seoDescription);
        if ($descLen >= 120 && $descLen <= 155) {
            $score += 10;
        } elseif ($descLen >= 70 && $descLen <= 170) {
            $score += 5;
            $findings[] = 'Meta description length is '.$descLen.' characters; 120–155 is ideal.';
        } else {
            $findings[] = $seoDescription === ''
                ? 'Missing meta description.'
                : 'Meta description length is '.$descLen.' characters; 120–155 is ideal.';
        }

        $wordCount = $this->wordCount($plain);
        if ($wordCount >= 80) {
            $score += 6;
        } elseif ($wordCount >= 40) {
            $score += 3;
        } else {
            $findings[] = 'Product description is too short for SEO analysis.';
        }

        $altHit = false;
        foreach ($imageAlts as $alt) {
            if ($this->containsPhrase($alt, $keyphrase)) {
                $altHit = true;
                break;
            }
        }
        $score += $altHit ? 4 : 0;
        if ($imageAlts !== [] && ! $altHit) {
            $findings[] = 'Add the focus keyphrase to an image alt text.';
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'rating' => self::rating($score, true),
            'findings' => $findings,
        ];
    }

    /**
     * @return array{score: ?int, rating: string, findings: list<string>}
     */
    private function scoreReadability(string $bodyHtml, string $plain): array
    {
        $findings = [];
        $wordCount = $this->wordCount($plain);
        if ($wordCount < 12) {
            $findings[] = 'Readability not analyzed: description is empty or too short.';

            return ['score' => null, 'rating' => self::NA, 'findings' => $findings];
        }

        $score = 0;
        $sentences = $this->sentences($plain);
        $sentenceCount = max(1, count($sentences));
        $longSentences = 0;
        foreach ($sentences as $sentence) {
            if ($this->wordCount($sentence) > 20) {
                $longSentences++;
            }
        }
        $longPct = ($longSentences / $sentenceCount) * 100;
        if ($longPct <= 25) {
            $score += 25;
        } elseif ($longPct <= 40) {
            $score += 12;
            $findings[] = 'Too many sentences are longer than 20 words.';
        } else {
            $findings[] = 'Shorten sentences — '.round($longPct).'% are longer than 20 words.';
        }

        $paragraphs = $this->paragraphs($bodyHtml, $plain);
        $paraWords = [];
        foreach ($paragraphs as $paragraph) {
            $count = $this->wordCount($paragraph);
            if ($count > 0) {
                $paraWords[] = $count;
            }
        }
        $avgPara = $paraWords !== [] ? array_sum($paraWords) / count($paraWords) : $wordCount;
        if ($avgPara <= 100) {
            $score += 15;
        } elseif ($avgPara <= 150) {
            $score += 8;
            $findings[] = 'Some paragraphs are long; keep most under 100 words.';
        } else {
            $findings[] = 'Break up long paragraphs.';
        }

        $transitionHits = 0;
        foreach ($sentences as $sentence) {
            if ($this->hasTransitionWord($sentence)) {
                $transitionHits++;
            }
        }
        $transitionPct = ($transitionHits / $sentenceCount) * 100;
        if ($transitionPct >= 30) {
            $score += 15;
        } elseif ($transitionPct >= 18) {
            $score += 8;
        } else {
            $findings[] = 'Add more transition words (however, because, also, for example).';
        }

        $passiveHits = 0;
        foreach ($sentences as $sentence) {
            if (preg_match('/\b(is|are|was|were|be|been|being)\s+[a-z]+ed\b/i', $sentence)) {
                $passiveHits++;
            }
        }
        $passivePct = ($passiveHits / $sentenceCount) * 100;
        if ($passivePct <= 10) {
            $score += 10;
        } elseif ($passivePct <= 20) {
            $score += 5;
            $findings[] = 'Reduce passive voice.';
        } else {
            $findings[] = 'Too much passive voice.';
        }

        if ($this->hasList($bodyHtml, $plain)) {
            $score += 10;
        } else {
            $findings[] = 'Add a list so shoppers can scan specs or benefits.';
        }

        $hasHeading = (bool) preg_match('/<(h[1-6]|strong)\b/i', $bodyHtml);
        if ($wordCount <= 300 || $hasHeading) {
            $score += 10;
        } else {
            $findings[] = 'Add subheadings; the description is over 300 words.';
        }

        $flesch = $this->flesch($plain, $sentences);
        if ($flesch >= 60) {
            $score += 15;
        } elseif ($flesch >= 50) {
            $score += 10;
        } elseif ($flesch >= 40) {
            $score += 5;
            $findings[] = 'Reading ease is '.round($flesch).'; aim for 60+.';
        } else {
            $findings[] = 'Text is hard to read (Flesch '.round($flesch).'). Use shorter words and sentences.';
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'rating' => self::rating($score, true),
            'findings' => $findings,
        ];
    }

    private function normalizeKeyphrase($value): string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');

        return mb_strlen($value) > 120 ? mb_substr($value, 0, 120) : $value;
    }

    private function plainText(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>', '</h1>', '</h2>', '</h3>'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        return trim(preg_replace("/\n{2,}/", "\n", $text) ?? $text);
    }

    private function containsPhrase(string $haystack, string $phrase): bool
    {
        if ($haystack === '' || $phrase === '') {
            return false;
        }

        return mb_stripos($haystack, $phrase) !== false;
    }

    private function startsWithPhrase(string $haystack, string $phrase): bool
    {
        return mb_stripos(ltrim($haystack), $phrase) === 0;
    }

    private function keyphraseDensity(string $plain, string $keyphrase): float
    {
        $words = $this->wordCount($plain);
        if ($words === 0 || $keyphrase === '') {
            return 0.0;
        }
        $hits = preg_match_all('/'.preg_quote($keyphrase, '/').'/iu', $plain);

        return round((($hits ?: 0) / $words) * 100, 2);
    }

    private function wordCount(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        return str_word_count($text);
    }

    /**
     * @return list<string>
     */
    private function sentences(string $plain): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/', $plain) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $out[] = $part;
            }
        }

        return $out !== [] ? $out : ($plain !== '' ? [$plain] : []);
    }

    /**
     * @return list<string>
     */
    private function paragraphs(string $html, string $plain): array
    {
        if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $html, $matches)) {
            $out = [];
            foreach ($matches[1] as $chunk) {
                $text = trim($this->plainText((string) $chunk));
                if ($text !== '') {
                    $out[] = $text;
                }
            }
            if ($out !== []) {
                return $out;
            }
        }

        $parts = preg_split("/\n+/", $plain) ?: [];

        return array_values(array_filter(array_map('trim', $parts)));
    }

    private function hasTransitionWord(string $sentence): bool
    {
        return (bool) preg_match(
            '/\b(because|therefore|however|also|but|so|then|thus|moreover|furthermore|meanwhile|instead|finally|first|next|after|before|although|while|unless|including|especially|besides|additionally)\b|(for example|in addition|as well|on the other hand)/i',
            $sentence
        );
    }

    private function hasList(string $html, string $plain): bool
    {
        if (preg_match('/<(ul|ol)\b/i', $html)) {
            return true;
        }

        return (bool) preg_match_all('/^\s*[-*•]\s+/m', $plain) && preg_match_all('/^\s*[-*•]\s+/m', $plain) >= 2;
    }

    /**
     * @param  list<string>  $sentences
     */
    private function flesch(string $plain, array $sentences): float
    {
        $words = max(1, $this->wordCount($plain));
        $sentenceCount = max(1, count($sentences));
        $syllables = max(1, $this->syllables($plain));

        return 206.835 - (1.015 * ($words / $sentenceCount)) - (84.6 * ($syllables / $words));
    }

    private function syllables(string $plain): int
    {
        $words = preg_split('/\s+/', strtolower($plain)) ?: [];
        $total = 0;
        foreach ($words as $word) {
            $word = preg_replace('/[^a-z]/', '', $word) ?? '';
            if ($word === '') {
                continue;
            }
            $word = preg_replace('/e$/', '', $word) ?? $word;
            $groups = preg_match_all('/[aeiouy]+/', $word);
            $total += max(1, (int) $groups);
        }

        return $total;
    }
}
