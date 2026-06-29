<?php

$files = [
    __DIR__ . '/../app/Http/Controllers/ProductMaster/VideoMasterController.php',
    __DIR__ . '/../resources/views/video-master.blade.php',
    __DIR__ . '/../app/Services/Support/VideoMasterPushJobStore.php',
    __DIR__ . '/../app/Services/Support/VideoMasterPushRunner.php',
];

$replacements = [
    'private const PM_MAX_VIDEOS = 20;' => 'private const PM_MAX_VIDEOS = 10;',
    "'image'.(\$i + 1)" => "'video'.(\$i + 1)", // won't work in str replace
    "\$col = 'image'.(\$i + 1);" => "\$col = 'video'.(\$i + 1);",
    'updates.*.images' => 'updates.*.videos',
    'updates.*.images.*' => 'updates.*.videos.*',
    "'images' => 'present|array|max:" => "'videos' => 'present|array|max:",
    "'images.*' => 'nullable" => "'videos.*' => 'nullable",
    "\$u['images']" => "\$u['videos']",
    "'images' => array_values" => "'videos' => array_values",
    "task['images']" => "task['videos']",
    'images: list<string>' => 'videos: list<string>',
    'marketplace: string, images:' => 'marketplace: string, videos:',
    "'images' => \$images" => "'videos' => \$videos",
    '$maxImageCount' => '$maxVideoCount',
    '@param  list<string>  $images' => '@param  list<string>  $videos',
    'array $images,' => 'array $videos,',
    '$images = array_values' => '$videos = array_values',
    'count($images)' => 'count($videos)',
    '$images === []' => '$videos === []',
    '$images !== []' => '$videos !== []',
    'array $imageUrls' => 'array $videoUrls',
    '$imageUrls' => '$videoUrls',
    'imageUrls as $url' => 'videoUrls as $url',
    'count($imageUrls)' => 'count($videoUrls)',
    '$imagesForPush' => '$videosForPush',
    'images_count' => 'videos_count',
    'image_count' => 'video_count',
    'first_image' => 'first_video',
    '$images = $this->normalizeStorageUrlsForVideoMasterMetrics(
            array_values(array_slice($validated[\'images\']' => '$videos = $this->normalizeStorageUrlsForVideoMasterMetrics(
            array_values(array_slice($validated[\'videos\']',
    'count($images)' => 'count($videos)',
    '$images[$i]' => '$videos[$i]',
    '$images[0]' => '$videos[0]',
    'sanitizeMainByMarketplace(
            $validated[\'main_by_marketplace\'] ?? [],
            count($images)' => 'sanitizeMainByMarketplace(
            $validated[\'main_by_marketplace\'] ?? [],
            count($videos)',
    'purgeRemovedSkuVideos($sku, $validated[\'removed_urls\'] ?? [], $images)' => 'purgeRemovedSkuVideos($sku, $validated[\'removed_urls\'] ?? [], $videos)',
    'image master data' => 'video master data',
    'Image push' => 'Video push',
    'image push' => 'video push',
    'An image push' => 'A video push',
    'Could not queue image push' => 'Could not queue video push',
    'Image push queued' => 'Video push queued',
    'No images to add' => 'No videos to add',
    'Clear-all images' => 'Clear-all videos',
    'image(s)' => 'video(s)',
    'Main image: Image' => 'Main video: Video',
    'invalid image URL' => 'invalid video URL',
    'would clear all Shopify images' => 'would clear all Shopify videos',
    'No images to push' => 'No videos to push',
    'Image push is not implemented' => 'Video push is not implemented',
    'Product Master images saved' => 'Product Master videos saved',
    'Removed {$purged} image(s)' => 'Removed {$purged} video(s)',
    'getAmazonImages' => 'getAmazonVideos',
    'getEbayImages' => 'getEbayVideos',
    'amazon-images' => 'amazon-videos',
    'ebay-images' => 'ebay-videos',
    'sku-images' => 'sku-videos',
    'sku-image/' => 'sku-video/',
    'deleteSkuImage' => 'deleteSkuVideo',
    'image_urls' => 'video_urls',
    'video_master_json' => 'video_master_json', // noop
    'for ($i = 1; $i <= self::PM_MAX_VIDEOS; $i++) {
            $value = trim((string) ($product->{\'image\'.$i}' => 'for ($i = 1; $i <= self::PM_MAX_VIDEOS; $i++) {
            $value = trim((string) ($product->{\'video\'.$i}',
    'image11' => 'video10',
    'image12' => 'video10',
    'image13' => 'video10',
    'image14' => 'video10',
    'image15' => 'video10',
    'image16' => 'video10',
    'image17' => 'video10',
    'image18' => 'video10',
    'image19' => 'video10',
    'image20' => 'video10',
];

foreach ($files as $file) {
    if (! is_file($file)) {
        echo "Missing: $file\n";
        continue;
    }
    $content = file_get_contents($file);
    foreach ($replacements as $from => $to) {
        $content = str_replace($from, $to, $content);
    }
    file_put_contents($file, $content);
    echo "Fixed: $file\n";
}

// Blade-specific
$blade = file_get_contents(__DIR__ . '/../resources/views/video-master.blade.php');
$bladeReplacements = [
    'fa-images' => 'fa-video',
    'Edit product images' => 'Edit product videos',
    'fa-image' => 'fa-file-video',
    'Max 20 images' => 'Max 10 videos',
    'Fetch Amazon images' => 'Fetch Amazon videos',
    'main/hero image' => 'main/hero video',
    'image #1' => 'video #1',
    'Save images here' => 'Save videos here',
    'push images' => 'push videos',
    'product images from Shopify' => 'product videos from Shopify',
    'pull images from Shopify' => 'pull videos from Shopify',
    'image fields' => 'video fields',
    'push images?' => 'push videos?',
    'image(s) to' => 'video(s) to',
    'marketplace images' => 'marketplace videos',
    'verify images before' => 'verify videos before',
    'stored-image' => 'stored-video',
    'row[\'image\'+i]' => 'row[\'video\'+i]',
    'j.images' => 'j.videos',
    'knownImageUrls' => 'knownVideoUrls',
    'modalUrls' => 'modalUrls',
    'imagesToPush' => 'videosToPush',
    'canPushImages' => 'canPushVideos',
    'syncPushModeModal(checks, imageCount)' => 'syncPushModeModal(checks, videoCount)',
    'pmImageCount' => 'pmVideoCount',
    'PM_MAX_VIDEOS = 20' => 'PM_MAX_VIDEOS = 10',
    'images:' => 'videos:',
    'images,' => 'videos,',
    'images ' => 'videos ',
    'images.' => 'videos.',
    'images?' => 'videos?',
    'images)' => 'videos)',
    'images\'' => 'videos\'',
    'images"' => 'videos"',
    'images}' => 'videos}',
    'images]' => 'videos]',
    'Delete image' => 'Delete video',
    'im-main-' => 'vm-main-',
    'im-up' => 'vm-up',
    'im-down' => 'vm-down',
    'im-set-main' => 'vm-set-main',
    'im-mp-chk' => 'vm-mp-chk',
    'amazon-images' => 'amazon-videos',
    'ebay-images' => 'ebay-videos',
    'sku-images' => 'sku-videos',
    'Product images by marketplace' => 'Product videos by marketplace',
    'image uploaded' => 'video uploaded',
    'Amazon images loaded' => 'Amazon videos loaded',
    'No Amazon images' => 'No Amazon videos',
    'eBay images loaded' => 'eBay videos loaded',
    'No eBay images' => 'No eBay videos',
    'no images?' => 'no videos?',
    'Images saved' => 'Videos saved',
    'images cleared' => 'videos cleared',
    'Save images first' => 'Save videos first',
    '<img class="vm-card-img-wrap img"' => '<video class="vm-card-video"',
    '<img ' => '<video controls preload="metadata" ',
    '</img>' => '</video>',
    'object-fit:cover' => 'object-fit:contain',
];
foreach ($bladeReplacements as $from => $to) {
    $blade = str_replace($from, $to, $blade);
}
file_put_contents(__DIR__ . '/../resources/views/video-master.blade.php', $blade);
echo "Fixed blade\n";
