<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AutomatedTaskSopPageAi
{
    /**
     * @param  array{text?: string, kind?: string, fetched?: bool, link?: string}  $material
     * @return array{title: string, html: string, scenes: array<int, array{caption: string, prompt: string}>}
     */
    public static function elaborate(object $task, array $material): array
    {
        $fallback = self::fallback($task, $material);
        $system = 'You write internal Standard Operating Procedure web pages for an e-commerce / marketplace operations team (Amazon, eBay, Shopify, ads, warehouse, customer service). '
            .'If source material from a Google Doc, Google Sheet, or file is provided, that source is the authority. '
            .'Organize and elaborate THAT content into a clear SOP: purpose, who, when, numbered steps taken from the source, checks. '
            .'Do not invent a different procedure when source text exists. Do not drop sheet rows or document steps. You may clarify wording. '
            .'If the source is a table/sheet, turn each meaningful row into a step or keep a summary table. '
            .'If there is no source text, write a practical SOP from the task title only. '
            .'Do not invent private company passwords, account IDs, or fake URLs. '
            .'Also invent 3 visual scenes that illustrate THIS exact task. Each scene caption is short; each prompt describes a clean instructional illustration with no readable UI text, no logos, no watermarks. '
            .'Return ONLY valid JSON: {"title":"string","html":"string","scenes":[{"caption":"string","prompt":"string"}]}. '
            .'html may use h2, h3, p, ol, ul, li, table, thead, tbody, tr, th, td, strong, em, a, br. No script, iframe, style, img, or forms.';

        $sourceText = trim((string) ($material['text'] ?? ''));
        $sourceKind = (string) ($material['kind'] ?? 'url');
        $sourceLink = trim((string) ($material['link'] ?? ($task->link3 ?? '')));
        $fetched = ! empty($material['fetched']);

        $userMsg = "Task title: ".trim((string) ($task->title ?? 'SOP'))."\n"
            .'Group: '.trim((string) ($task->group ?? ''))."\n"
            .'Description: '.trim((string) ($task->description ?? ''))."\n"
            .'Assignor: '.trim((string) ($task->assignor ?? ''))."\n"
            .'Assignee: '.trim((string) ($task->assign_to ?? ''))."\n"
            .'Schedule: '.trim((string) ($task->schedule_type ?? '')).' '.trim((string) ($task->schedule_time ?? ''))."\n"
            .'Priority: '.trim((string) ($task->priority ?? ''))."\n"
            .'Source SOP URL: '.$sourceLink."\n"
            .'Source type: '.$sourceKind."\n"
            .'Source file/doc was readable: '.($fetched ? 'yes' : 'no')."\n\n"
            ."Source material (use this as the real SOP; empty only if the file could not be opened):\n"
            .($sourceText !== '' ? $sourceText : '(no source text — the linked Doc/Sheet could not be read. Write a short SOP from the task title and tell the reader to open the original SOP link.)');

        $ai = self::callAiJson($system, $userMsg, 90, 0.35);
        if ($ai['text'] === null) {
            Log::warning('SOP page AI failed', ['error' => $ai['error'], 'task_id' => $task->id ?? null]);

            return $fallback;
        }

        $decoded = json_decode($ai['text'], true);
        if (! is_array($decoded)) {
            return $fallback;
        }

        $title = trim((string) ($decoded['title'] ?? ''));
        $html = trim((string) ($decoded['html'] ?? $decoded['body'] ?? ''));
        if ($html === '') {
            return $fallback;
        }

        $html = AutomatedTaskSopPageBuilder::sanitizeHtml($html);
        $html .= self::sourceFooter($sourceLink);

        return [
            'title' => $title !== '' ? mb_substr($title, 0, 255) : $fallback['title'],
            'html' => $html,
            'scenes' => self::normalizeScenes($decoded['scenes'] ?? null, $task),
        ];
    }

    /**
     * @param  array{text?: string, kind?: string, fetched?: bool, link?: string}  $material
     * @return array{title: string, html: string, scenes: array<int, array{caption: string, prompt: string}>}
     */
    public static function fallback(object $task, array $material): array
    {
        $title = trim((string) ($task->title ?? 'SOP'));
        $pageTitle = $title !== '' ? $title.' — SOP' : 'SOP';
        $sourceLink = trim((string) ($material['link'] ?? ($task->link3 ?? '')));
        $sourceText = trim((string) ($material['text'] ?? ''));
        $who = trim((string) ($task->assign_to ?? ''));
        $when = trim(trim((string) ($task->schedule_type ?? '')).' '.trim((string) ($task->schedule_time ?? '')));
        $desc = trim((string) ($task->description ?? ''));

        $html = '<h2>Purpose</h2><p>This SOP explains how to complete <strong>'.e($title !== '' ? $title : 'this automated task').'</strong> consistently.</p>';
        if ($desc !== '') {
            $html .= '<p>'.e($desc).'</p>';
        }
        $html .= '<h2>Who</h2><p>'.e($who !== '' ? $who : 'The assigned teammate for this automated task').'</p>';
        $html .= '<h2>When</h2><p>'.e($when !== '' ? $when : 'Follow the automated task schedule.').'</p>';
        $html .= '<h2>Procedure</h2><ol>'
            .'<li>Open the original SOP link and read the current steps.</li>'
            .'<li>Complete the work described by the task: '.e($title !== '' ? $title : 'see the task title').'.</li>'
            .'<li>Record the outcome and flag blockers to the assignor.</li>'
            .'<li>Mark the task done only after the checks below pass.</li>'
            .'</ol>';
        $html .= '<h2>Checks</h2><ul><li>The work matches the SOP and the task title.</li><li>Exceptions are escalated the same day.</li></ul>';
        if ($sourceText !== '') {
            $html .= '<h2>Source notes</h2><pre style="white-space:pre-wrap;">'.e(mb_substr($sourceText, 0, 8000)).'</pre>';
        }
        $html .= self::sourceFooter($sourceLink);

        return [
            'title' => $pageTitle,
            'html' => $html,
            'scenes' => self::normalizeScenes(null, $task),
        ];
    }

    /**
     * Generate related AI images and wrap them in an animated walkthrough reel.
     *
     * @param  array<int, array{caption?: string, prompt?: string}>  $scenes
     */
    public static function attachVisuals(object $task, string $html, array $scenes = []): string
    {
        $taskId = (int) ($task->id ?? 0);
        $scenes = self::normalizeScenes($scenes, $task);
        $slides = [];
        foreach ($scenes as $i => $scene) {
            $url = self::generateSceneImage($taskId, $i, (string) $scene['prompt']);
            if (! $url) {
                continue;
            }
            $slides[] = [
                'url' => $url,
                'caption' => (string) $scene['caption'],
            ];
            if (count($slides) >= 2) {
                break;
            }
        }

        $reel = $slides !== [] ? self::reelHtml($slides) : self::motionFallbackHtml($task);
        $html = self::injectInlineImages($html, $slides);

        return $reel.$html;
    }

    /**
     * @param  mixed  $raw
     * @return array<int, array{caption: string, prompt: string}>
     */
    private static function normalizeScenes($raw, object $task): array
    {
        $title = trim((string) ($task->title ?? 'this operational task'));
        $defaults = [
            [
                'caption' => 'Overview',
                'prompt' => 'Clean modern instructional illustration, no readable text or logos, of an operations specialist reviewing this work on a widescreen computer: '.$title.'. Bright office, 3D product-ad dashboard vibe, professional.',
            ],
            [
                'caption' => 'Doing the work',
                'prompt' => 'Clean instructional illustration, no readable text or logos, close-up of hands and a monitor while performing: '.$title.'. Compare listings, ads, or inventory rows. Bright, realistic, workplace.',
            ],
            [
                'caption' => 'Quality check',
                'prompt' => 'Clean instructional illustration, no readable text or logos, of a completed quality check after: '.$title.'. Checklist marked complete, calm professional lighting.',
            ],
        ];

        if (! is_array($raw)) {
            return $defaults;
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $caption = trim((string) ($row['caption'] ?? $row['title'] ?? ''));
            $prompt = trim((string) ($row['prompt'] ?? $row['image_prompt'] ?? ''));
            if ($prompt === '') {
                continue;
            }
            if ($caption === '') {
                $caption = 'Scene '.((string) (count($out) + 1));
            }
            $out[] = [
                'caption' => mb_substr($caption, 0, 80),
                'prompt' => mb_substr($prompt, 0, 900),
            ];
            if (count($out) >= 3) {
                break;
            }
        }

        return $out !== [] ? $out : $defaults;
    }

    private static function generateSceneImage(int $taskId, int $index, string $prompt): ?string
    {
        if ($taskId < 1 || $prompt === '') {
            return null;
        }

        $headers = OpenAiRequest::authHeaders();
        if ($headers === []) {
            return null;
        }

        $dir = public_path('uploads/tasks/sop-pages/'.$taskId);
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return null;
        }

        $filename = 'scene-'.$index.'.png';
        $dest = $dir.DIRECTORY_SEPARATOR.$filename;
        $fullPrompt = 'Instructional workplace illustration for an SOP, photoreal-3D hybrid, no watermarks, no letters or UI text on screen: '.$prompt;

        try {
            $response = Http::withHeaders($headers)
                ->timeout(90)
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => 'dall-e-3',
                    'prompt' => $fullPrompt,
                    'n' => 1,
                    'size' => '1024x1024',
                    'quality' => 'standard',
                ]);
        } catch (\Throwable $e) {
            Log::warning('SOP image request failed', ['msg' => $e->getMessage(), 'task_id' => $taskId]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('SOP image API error', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
                'task_id' => $taskId,
            ]);

            return null;
        }

        $b64 = (string) ($response->json('data.0.b64_json') ?? '');
        if ($b64 !== '') {
            $bytes = base64_decode($b64, true);
            if (! is_string($bytes) || $bytes === '') {
                return null;
            }
            file_put_contents($dest, $bytes);

            return asset('uploads/tasks/sop-pages/'.$taskId.'/'.$filename);
        }

        $url = trim((string) ($response->json('data.0.url') ?? ''));
        if ($url === '') {
            return null;
        }

        try {
            $file = Http::timeout(60)->get($url);
        } catch (\Throwable $e) {
            return null;
        }
        if (! $file->successful() || strlen($file->body()) < 200) {
            return null;
        }
        file_put_contents($dest, $file->body());

        return asset('uploads/tasks/sop-pages/'.$taskId.'/'.$filename);
    }

    /**
     * @param  array<int, array{url: string, caption: string}>  $slides
     */
    private static function reelHtml(array $slides): string
    {
        $figures = '';
        foreach ($slides as $i => $slide) {
            $active = $i === 0 ? ' is-active' : '';
            $figures .= '<figure class="sop-ai-slide'.$active.'">'
                .'<img src="'.e($slide['url']).'" alt="'.e($slide['caption']).'">'
                .'<figcaption>'.e($slide['caption']).'</figcaption>'
                .'</figure>';
        }

        return '<div class="sop-ai-reel" data-sop-reel="1">'
            .'<div class="sop-ai-reel-label"><i class="mdi mdi-auto-fix"></i> AI walkthrough</div>'
            .'<div class="sop-ai-reel-stage">'.$figures.'</div>'
            .'</div>';
    }

    private static function motionFallbackHtml(object $task): string
    {
        $title = e(trim((string) ($task->title ?? 'this task')));

        return '<div class="sop-ai-reel sop-ai-reel-fallback" data-sop-reel="1">'
            .'<div class="sop-ai-reel-label"><i class="mdi mdi-auto-fix"></i> AI walkthrough</div>'
            .'<div class="sop-ai-motion">'
            .'<div class="sop-ai-orb"></div>'
            .'<div class="sop-ai-orb sop-ai-orb-2"></div>'
            .'<p class="sop-ai-motion-title">How to: '.$title.'</p>'
            .'<p class="sop-ai-motion-sub">Follow the illustrated steps below</p>'
            .'</div></div>';
    }

    /**
     * @param  array<int, array{url: string, caption: string}>  $slides
     */
    private static function injectInlineImages(string $html, array $slides): string
    {
        if ($slides === []) {
            return $html;
        }

        $blocks = preg_split('/(?=<h2\b)/i', $html) ?: [$html];
        $out = '';
        $imgIndex = count($slides) > 1 ? 1 : 99;
        foreach ($blocks as $i => $block) {
            $out .= $block;
            if ($i === 0 || $imgIndex >= count($slides)) {
                continue;
            }
            if (! preg_match('/<h2\b/i', $block)) {
                continue;
            }
            $slide = $slides[$imgIndex];
            $out .= '<figure class="sop-ai-inline">'
                .'<img src="'.e($slide['url']).'" alt="'.e($slide['caption']).'">'
                .'<figcaption>'.e($slide['caption']).'</figcaption>'
                .'</figure>';
            $imgIndex++;
        }

        return $out;
    }

    private static function sourceFooter(string $sourceLink): string
    {
        if ($sourceLink === '') {
            return '';
        }

        return '<p class="text-muted small mt-4">Original SOP: <a href="'.e($sourceLink).'" target="_blank" rel="noopener noreferrer">Open source file</a></p>';
    }

    /**
     * Apply an assignor/president/director instruction (add, edit, delete) to an existing SOP page.
     *
     * @return array{title: string, html: string}
     */
    public static function revise(object $task, string $currentHtml, string $instruction, string $currentTitle = ''): array
    {
        $instruction = trim($instruction);
        if ($instruction === '') {
            throw new \InvalidArgumentException('Enter an instruction for the AI.');
        }

        $parts = self::splitPageParts($currentHtml);
        $working = trim($parts['body']) !== '' ? $parts['body'] : $currentHtml;
        if (mb_strlen($working) > 24000) {
            $working = mb_substr($working, 0, 24000)."\n<!-- truncated -->";
        }

        $system = 'You revise an internal Standard Operating Procedure HTML page for an e-commerce operations team. '
            .'Apply the user instruction exactly: add, edit, delete, rewrite, reorder, or clarify content. '
            .'Return the FULL updated SOP body HTML after the change — not a patch and not a comment about the change. '
            .'Keep existing structure and wording that the instruction does not mention. '
            .'html may use h2, h3, p, ol, ul, li, table, thead, tbody, tr, th, td, strong, em, a, br, figure, img, figcaption. '
            .'No script, iframe, style, or forms. Do not invent passwords, account IDs, or fake URLs. '
            .'Return ONLY valid JSON: {"title":"string","html":"string"}.';

        $userMsg = "Task title: ".trim((string) ($task->title ?? 'SOP'))."\n"
            .'Current page title: '.($currentTitle !== '' ? $currentTitle : trim((string) ($task->title ?? 'SOP')))."\n"
            .'Group: '.trim((string) ($task->group ?? ''))."\n\n"
            ."Instruction (do this to the page):\n".$instruction."\n\n"
            ."Current SOP HTML:\n".$working;

        $ai = self::callAiJson($system, $userMsg, 120, 0.2, 4096);
        if ($ai['text'] === null) {
            throw new \RuntimeException($ai['error'] ?? 'AI did not return a revision.');
        }

        $decoded = json_decode($ai['text'], true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('AI returned an invalid revision.');
        }

        $title = trim((string) ($decoded['title'] ?? ''));
        $html = trim((string) ($decoded['html'] ?? $decoded['body'] ?? ''));
        if ($html === '') {
            throw new \RuntimeException('AI returned an empty page.');
        }

        $html = AutomatedTaskSopPageBuilder::sanitizeHtml($html);
        $lowerInstruction = strtolower($instruction);
        $removeVisuals = (bool) preg_match('/\b(remove|delete|drop)\b.{0,40}\b(image|reel|walkthrough|visual|picture|photo)\b/i', $lowerInstruction);
        $removeSource = (bool) preg_match('/\b(remove|delete|drop)\b.{0,40}\b(source|original file|sheet data|document data)\b/i', $lowerInstruction);

        if ($parts['reel'] !== '' && ! $removeVisuals && ! str_contains($html, 'sop-ai-reel')) {
            $html = $parts['reel'].$html;
        }
        if ($parts['source'] !== '' && ! $removeSource && ! str_contains($html, 'sop-source-data')) {
            $html .= $parts['source'];
        }

        return [
            'title' => $title !== '' ? mb_substr($title, 0, 255) : ($currentTitle !== '' ? $currentTitle : 'SOP'),
            'html' => $html,
        ];
    }

    /**
     * @return array{reel: string, source: string, body: string}
     */
    private static function splitPageParts(string $html): array
    {
        if (trim($html) === '') {
            return ['reel' => '', 'source' => '', 'body' => ''];
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div id="__sop_root">'.$html.'</div>',
            LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $dom->getElementById('__sop_root');
        if (! $root) {
            return ['reel' => '', 'source' => '', 'body' => $html];
        }

        $xpath = new \DOMXPath($dom);
        $reel = '';
        $source = '';
        $reelNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " sop-ai-reel ")]', $root)->item(0);
        if ($reelNode instanceof \DOMNode) {
            $reel = $dom->saveHTML($reelNode) ?: '';
            $reelNode->parentNode?->removeChild($reelNode);
        }
        $sourceNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " sop-source-data ")]', $root)->item(0);
        if ($sourceNode instanceof \DOMNode) {
            $source = $dom->saveHTML($sourceNode) ?: '';
            $sourceNode->parentNode?->removeChild($sourceNode);
        }

        $body = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $body .= $dom->saveHTML($child) ?: '';
        }

        return ['reel' => $reel, 'source' => $source, 'body' => $body];
    }

    /**
     * @return array{text: string|null, error: string|null, provider: string|null}
     */
    private static function callAiJson(string $system, string $userMsg, int $timeoutSeconds = 90, float $temperature = 0.35, int $maxTokens = 3500): array
    {
        $stripFences = static function (string $t): string {
            $t = trim($t);
            $t = preg_replace('/^```(?:json)?\s*/i', '', $t) ?? $t;
            $t = preg_replace('/\s*```\s*$/i', '', $t) ?? $t;

            return trim($t);
        };

        $lastError = null;
        $openAiKey = config('services.openai.key');
        if (is_string($openAiKey) && $openAiKey !== '') {
            try {
                $response = Http::withHeaders(OpenAiRequest::authHeaders())
                    ->timeout($timeoutSeconds)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => (string) config('services.openai.title_master_stack_model', 'gpt-4o-mini'),
                        'temperature' => $temperature,
                        'max_tokens' => $maxTokens,
                        'response_format' => ['type' => 'json_object'],
                        'messages' => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => $userMsg],
                        ],
                    ]);
                if ($response->successful()) {
                    $text = $stripFences((string) ($response->json('choices.0.message.content') ?? ''));
                    if ($text !== '') {
                        return ['text' => $text, 'error' => null, 'provider' => 'openai'];
                    }
                    $lastError = 'OpenAI returned an empty response.';
                } else {
                    $msg = (string) ($response->json('error.message') ?? '');
                    $lastError = 'OpenAI '.$response->status().($msg !== '' ? ': '.$msg : '');
                }
            } catch (\Throwable $e) {
                $lastError = 'OpenAI exception: '.$e->getMessage();
            }
        } else {
            $lastError = 'OpenAI key not configured.';
        }

        $claudeKey = config('services.anthropic.key');
        if (! is_string($claudeKey) || $claudeKey === '') {
            return [
                'text' => null,
                'error' => ($lastError ?: 'No AI provider configured.').' (Claude key also missing)',
                'provider' => null,
            ];
        }

        try {
            $response = Http::withHeaders([
                    'x-api-key' => $claudeKey,
                    'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
                    'content-type' => 'application/json',
                ])
                ->timeout($timeoutSeconds)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => max(1024, $maxTokens),
                    'temperature' => $temperature,
                    'system' => $system."\n\nIMPORTANT: Return ONLY a single valid JSON object. No prose, no markdown fences.",
                    'messages' => [
                        ['role' => 'user', 'content' => $userMsg],
                    ],
                ]);
            if (! $response->successful()) {
                $err = (string) ($response->json('error.message') ?? ('HTTP '.$response->status()));

                return [
                    'text' => null,
                    'error' => ($lastError ? $lastError.' · ' : '').'Claude error: '.$err,
                    'provider' => 'claude',
                ];
            }
            $text = '';
            foreach ((array) $response->json('content', []) as $block) {
                if (is_array($block) && (($block['type'] ?? '') === 'text') && isset($block['text'])) {
                    $text .= (string) $block['text'];
                }
            }
            $text = $stripFences($text);
            if ($text === '') {
                return [
                    'text' => null,
                    'error' => ($lastError ? $lastError.' · ' : '').'Claude returned an empty response.',
                    'provider' => 'claude',
                ];
            }

            return ['text' => $text, 'error' => null, 'provider' => 'claude'];
        } catch (\Throwable $e) {
            return [
                'text' => null,
                'error' => ($lastError ? $lastError.' · ' : '').'Claude network error: '.$e->getMessage(),
                'provider' => 'claude',
            ];
        }
    }
}
