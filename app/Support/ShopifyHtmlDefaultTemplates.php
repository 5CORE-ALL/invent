<?php

namespace App\Support;

/**
 * Built-in Shopify description HTML templates for Description Master (non-technical users).
 */
final class ShopifyHtmlDefaultTemplates
{
    /**
     * @return array<int, array{template_name: string, marketplace: string, html_content: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'template_name' => 'Standard Product Layout',
                'marketplace' => 'all',
                'html_content' => <<<'HTML'
<div class="product-page-rich" style="max-width:100%;font-family:system-ui,sans-serif;color:#333;line-height:1.5;">
<h2 style="color:#c00; margin-top:0;">About Item</h2>
<div class="about-item" style="margin-bottom:1.25rem;">
<p><strong>KEY POINT ONE</strong> — Replace with your first bullet-style benefit.</p>
<p><strong>KEY POINT TWO</strong> — Replace with your second point.</p>
<p><strong>KEY POINT THREE</strong> — Add as many lines as you need.</p>
</div>
<h2>Product Description</h2>
<p>Write your full product story here. Use the editor above for bold, lists, and links.</p>
<h2>Features</h2>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:1rem 0;">
<div style="border:1px solid #ddd;border-radius:8px;padding:12px;background:#f9f9f9;"><h4 style="margin:0 0 8px;">FEATURE A</h4><p style="margin:0;font-size:14px;">Short benefit text.</p></div>
<div style="border:1px solid #ddd;border-radius:8px;padding:12px;background:#f9f9f9;"><h4 style="margin:0 0 8px;">FEATURE B</h4><p style="margin:0;font-size:14px;">Short benefit text.</p></div>
<div style="border:1px solid #ddd;border-radius:8px;padding:12px;background:#f9f9f9;"><h4 style="margin:0 0 8px;">FEATURE C</h4><p style="margin:0;font-size:14px;">Short benefit text.</p></div>
<div style="border:1px solid #ddd;border-radius:8px;padding:12px;background:#f9f9f9;"><h4 style="margin:0 0 8px;">FEATURE D</h4><p style="margin:0;font-size:14px;">Short benefit text.</p></div>
</div>
<h2>Specifications</h2>
<table style="width:100%;border-collapse:collapse;font-size:14px;">
<tr style="border-bottom:1px solid #eee;"><td style="padding:8px;font-weight:600;">Power</td><td style="padding:8px;">—</td></tr>
<tr style="border-bottom:1px solid #eee;"><td style="padding:8px;font-weight:600;">Weight</td><td style="padding:8px;">—</td></tr>
<tr style="border-bottom:1px solid #eee;"><td style="padding:8px;font-weight:600;">Dimensions</td><td style="padding:8px;">—</td></tr>
</table>
<h2>Package Includes</h2>
<ul><li>Item one</li><li>Item two</li><li>Item three</li></ul>
<h2>About Brand</h2>
<p>Short brand story or warranty note.</p>
<h2>Images</h2>
<p style="font-size:13px;color:#666;">Add product photos using the image button in the editor, or paste image URLs.</p>
<p><img src="https://via.placeholder.com/400x300?text=Product+Photo+1" alt="Product" style="max-width:48%;height:auto;margin:4px;"></p>
</div>
HTML,
            ],
            [
                'template_name' => 'Simple Layout',
                'marketplace' => 'all',
                'html_content' => <<<'HTML'
<div style="max-width:100%;font-family:system-ui,sans-serif;">
<h1 style="margin-top:0;">Product Title</h1>
<p>One or two paragraphs describing your product. Keep it clear and friendly for shoppers.</p>
<h2>Gallery</h2>
<div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">
<img src="https://via.placeholder.com/320x240?text=Image+1" alt="" style="max-width:45%;height:auto;border-radius:8px;">
<img src="https://via.placeholder.com/320x240?text=Image+2" alt="" style="max-width:45%;height:auto;border-radius:8px;">
</div>
</div>
HTML,
            ],
            [
                'template_name' => 'Feature Focus',
                'marketplace' => 'all',
                'html_content' => <<<'HTML'
<div style="max-width:100%;font-family:system-ui,sans-serif;">
<ul style="font-size:15px;line-height:1.6;">
<li><strong>Benefit one</strong> — short explanation.</li>
<li><strong>Benefit two</strong> — short explanation.</li>
<li><strong>Benefit three</strong> — short explanation.</li>
</ul>
<h2 style="margin-top:1.5rem;">Why choose this product</h2>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
<div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px;background:#f8fafc;"><h3 style="margin:0 0 6px;font-size:16px;">HIGHLIGHT 1</h3><p style="margin:0;font-size:14px;">Details here.</p></div>
<div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px;background:#f8fafc;"><h3 style="margin:0 0 6px;font-size:16px;">HIGHLIGHT 2</h3><p style="margin:0;font-size:14px;">Details here.</p></div>
<div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px;background:#f8fafc;"><h3 style="margin:0 0 6px;font-size:16px;">HIGHLIGHT 3</h3><p style="margin:0;font-size:14px;">Details here.</p></div>
<div style="border:1px solid #e2e8f0;border-radius:8px;padding:14px;background:#f8fafc;"><h3 style="margin:0 0 6px;font-size:16px;">HIGHLIGHT 4</h3><p style="margin:0;font-size:14px;">Details here.</p></div>
</div>
</div>
HTML,
            ],
            [
                'template_name' => 'Image Gallery',
                'marketplace' => 'all',
                'html_content' => <<<'HTML'
<div style="max-width:100%;font-family:system-ui,sans-serif;">
<p style="text-align:center;font-size:15px;margin-bottom:12px;">Short intro line — optional.</p>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px;">
<img src="https://via.placeholder.com/200?text=1" alt="" style="width:100%;height:auto;border-radius:6px;">
<img src="https://via.placeholder.com/200?text=2" alt="" style="width:100%;height:auto;border-radius:6px;">
<img src="https://via.placeholder.com/200?text=3" alt="" style="width:100%;height:auto;border-radius:6px;">
<img src="https://via.placeholder.com/200?text=4" alt="" style="width:100%;height:auto;border-radius:6px;">
</div>
<p style="text-align:center;font-size:13px;color:#64748b;margin-top:12px;">Replace placeholders with your real image URLs.</p>
</div>
HTML,
            ],
        ];
    }
}
