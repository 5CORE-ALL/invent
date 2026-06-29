$ErrorActionPreference = 'Stop'

function Clone-WithReplacements($src, $dest, [string[]]$pairs) {
    $c = [IO.File]::ReadAllText($src)
    foreach ($p in $pairs) {
        $parts = $p -split '\|', 2
        $c = $c.Replace($parts[0], $parts[1])
    }
    $dir = Split-Path $dest -Parent
    if ($dir -and !(Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    [IO.File]::WriteAllText($dest, $c)
}

$common = @(
    'ImageMasterController|VideoMasterController'
    'ImageMasterPushJobStore|VideoMasterPushJobStore'
    'ImageMasterPushRunner|VideoMasterPushRunner'
    'RunImageMasterPushJob|RunVideoMasterPushJob'
    'RunImageMasterPush|RunVideoMasterPush'
    'ShopifyImagePullJobStore|ShopifyVideoPullJobStore'
    'ShopifyImagePullRunner|ShopifyVideoPullRunner'
    'RunShopifyImagePullJob|RunShopifyVideoPullJob'
    'RunShopifyImagePull|RunShopifyVideoPull'
    'ImageMaster|VideoMaster'
    'image-master-push|video-master-push'
    'image-master|video-master'
    'image_master|video_master'
    'Image Master|Video Master'
    'ProductImage|ProductVideo'
    'product_images|product_videos'
    'image_path|video_path'
    'PM_MAX_IMAGES|PM_MAX_VIDEOS'
    'saveProductMasterImages|saveProductMasterVideos'
    'uploadImages|uploadVideos'
    'getSkuImages|getSkuVideos'
    'deleteSkuImage|deleteSkuVideo'
    'getAmazonImages|getAmazonVideos'
    'getEbayImages|getEbayVideos'
    'pullShopifyImagesToMaster|pullShopifyVideosToMaster'
    'pushImagesToRemote|pushVideosToRemote'
    'saveImageMetricsToTable|saveVideoMetricsToTable'
    'loadImageMetricsBySku|loadVideoMetricsBySku'
    'loadImageMainByMarketplaceForSkus|loadVideoMainByMarketplaceForSkus'
    'loadImageMainByMarketplace|loadVideoMainByMarketplace'
    'decodeImageMainByMarketplaceJson|decodeVideoMainByMarketplaceJson'
    'marketplaceImageLimit|marketplaceVideoLimit'
    'mainImageIndexFromMap|mainVideoIndexFromMap'
    'mainImageIndexForMarketplace|mainVideoIndexForMarketplace'
    'reorderImagesWithMainFirst|reorderVideosWithMainFirst'
    'loadExistingMarketplaceImages|loadExistingMarketplaceVideos'
    'normalizeStorageUrlsForImageMasterMetrics|normalizeStorageUrlsForVideoMasterMetrics'
    'purgeRemovedSkuImages|purgeRemovedSkuVideos'
    'productMasterImageArray|productMasterVideoArray'
    'normalizedImageArray|normalizedVideoArray'
    'fetchShopifyImagesForSku|fetchShopifyVideosForSku'
    'extractShopifyImageUrls|extractShopifyVideoUrls'
    'fetchPublicShopifyProductImagesForSku|fetchPublicShopifyProductVideosForSku'
    'fetchCachedShopifyImagesForSku|fetchCachedShopifyVideosForSku'
    'dedupeImageUrls|dedupeVideoUrls'
    'saveShopifyCatalogImages|saveShopifyCatalogVideos'
    'dispatchImageMasterPushJob|dispatchVideoMasterPushJob'
    'dispatchShopifyImagePullJob|dispatchShopifyVideoPullJob'
    'shopifyImagePullLogger|shopifyVideoPullLogger'
    'dryRunUpdateImages|dryRunUpdateVideos'
    'updateListingImages|updateListingVideos'
    'image_main_by_marketplace_json|video_main_by_marketplace_json'
    'image_main_by_marketplace|video_main_by_marketplace'
    'main_image|main_video'
    'im-master|vm-master'
    'im-card|vm-card'
    'im-grid|vm-grid'
    'im-thumb|vm-thumb'
    'im-select|vm-select'
    'im-pending|vm-pending'
    'im-main-mp|vm-main-mp'
)

$ctrlPairs = $common + @(
    'updateImages|updateVideos'
    'image10|video10'
    'image9|video9'
    'image8|video8'
    'image7|video7'
    'image6|video6'
    'image5|video5'
    'image4|video4'
    'image3|video3'
    'image2|video2'
    'image1|video1'
)

$bladePairs = $common + @(
    'Product images by marketplace|Product videos by marketplace'
    'image push|video push'
    'image pull|video pull'
    'image URL|video URL'
    'No images|No videos'
    'all images|all videos'
    'Shopify images|Shopify videos'
    'Main image|Main video'
    'main image|main video'
    'image10|video10'
    'image9|video9'
    'image8|video8'
    'image7|video7'
    'image6|video6'
    'image5|video5'
    'image4|video4'
    'image3|video3'
    'image2|video2'
    'image1|video1'
    'accept="image/jpeg,image/png,image/webp"|accept="video/mp4,video/webm,video/quicktime,video/x-m4v"'
)

$root = 'd:\5core'
Set-Location $root

Clone-WithReplacements 'app\Http\Controllers\ProductMaster\ImageMasterController.php' 'app\Http\Controllers\ProductMaster\VideoMasterController.php' $ctrlPairs
Clone-WithReplacements 'resources\views\image-master.blade.php' 'resources\views\video-master.blade.php' $bladePairs
Clone-WithReplacements 'app\Services\Support\ImageMasterPushRunner.php' 'app\Services\Support\VideoMasterPushRunner.php' $common
Clone-WithReplacements 'app\Services\Support\ImageMasterPushJobStore.php' 'app\Services\Support\VideoMasterPushJobStore.php' $common
Clone-WithReplacements 'app\Jobs\RunImageMasterPushJob.php' 'app\Jobs\RunVideoMasterPushJob.php' $common
Clone-WithReplacements 'app\Console\Commands\RunImageMasterPush.php' 'app\Console\Commands\RunVideoMasterPush.php' $common
Clone-WithReplacements 'app\Services\Support\ShopifyImagePullRunner.php' 'app\Services\Support\ShopifyVideoPullRunner.php' $common
Clone-WithReplacements 'app\Services\Support\ShopifyImagePullJobStore.php' 'app\Services\Support\ShopifyVideoPullJobStore.php' $common
Clone-WithReplacements 'app\Jobs\RunShopifyImagePullJob.php' 'app\Jobs\RunShopifyVideoPullJob.php' $common
Clone-WithReplacements 'app\Console\Commands\RunShopifyImagePull.php' 'app\Console\Commands\RunShopifyVideoPull.php' $common

Write-Host 'Clone complete'
