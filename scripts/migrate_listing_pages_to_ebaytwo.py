#!/usr/bin/env python3
"""Migrate listing pages to EbayTwo Tabulator UI + automated view-data wiring."""

from __future__ import annotations

import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CTRL_DIR = ROOT / "app/Http/Controllers/MarketPlace/ListingMarketPlace"
BLADE_DIR = ROOT / "resources/views/market-places/listing-market-places"
EBAYTWO_BLADE = BLADE_DIR / "listingEbayTwo.blade.php"

# channel_key, ControllerClass, viewMethodName, pageMethodName, bladeStem, route_slug (listing_X), view_var_pct, title
CHANNELS = [
    ("ebay", "ListingEbayController", "getViewListingEbayData", "listingEbay", "listingEbay", "ebay", "ebayPercentage", "eBay"),
    ("ebaythree", "ListingEbayThreeController", "getViewListingEbayThreeData", "listingEbayThree", "listingEbayThree", "ebaythree", "ebayThreePercentage", "Ebay Three"),
    ("ebayvariation", "ListingEbayVariationController", "getViewListingEbayVariationData", "listingEbayVariation", "listingEbayVariation", "ebayvariation", "ebayVariationPercentage", "Ebay Variation"),
    ("doba", "ListingDobaController", "getViewListingDobaData", "listingDoba", "listingDoba", "doba", "dobaPercentage", "Doba"),
    ("walmart", "ListingWalmartController", "getViewListingWalmartData", "listingWalmart", "listingWalmart", "walmart", "walmartPercentage", "Walmart"),
    ("neweggb2c", "ListingNeweggB2CController", "getViewListingNeweggB2CData", "listingNeweggB2C", "listingNeweggB2C", "neweggb2c", "neweggB2CPercentage", "Newegg B2C"),
    ("neweggb2b", "ListingNeweggB2BController", "getViewListingNeweggB2BData", "listingNeweggB2B", "listingNeweggB2B", "neweggb2b", "neweggB2BPercentage", "Newegg B2B"),
    ("tiktokshop", "ListingTiktokShopController", "getViewListingTiktokShopData", "listingTiktokShop", "listingTiktokShop", "tiktokshop", "tiktokShopPercentage", "TikTok Shop"),
    ("reverb", "ListingReverbController", "getViewListingReverbData", "listingReverb", "listingReverb", "reverb", "reverbPercentage", "Reverb"),
    ("shein", "ListingSheinController", "getViewListingSheinData", "listingShein", "listingShein", "shein", "sheinPercentage", "Shein"),
    ("temu", "ListingTemuController", "getViewListingTemuData", "listingTemu", "listingTemu", "temu", "temuPercentage", "Temu"),
    ("macys", "ListingMacysController", "getViewListingMacysData", "listingMacys", "listingMacys", "macys", "macysPercentage", "Macys"),
    ("wayfair", "ListingWayfairController", "getViewListingWayfairData", "listingWayfair", "listingWayfair", "wayfair", "wayfairPercentage", "Wayfair"),
    ("pls", "ListingPlsController", "getViewListingPlsData", "listingPls", "listingPls", "pls", "plsPercentage", "PLS"),
    ("bestbuyusa", "ListingBestbuyUSAController", "getViewListingBestbuyUSAData", "listingBestbuyUSA", "listingBestbuyUSA", "bestbuyusa", "bestbuyUSAPercentage", "BestBuy USA"),
    ("fbmarketplace", "ListingFBMarketplaceController", "getViewListingFBMarketplaceData", "listingFBMarketplace", "listingFBMarketplace", "fbmarketplace", "fbMarketplacePercentage", "FB Marketplace"),
    ("fbshop", "ListingFBShopController", "getViewListingFBShopData", "listingFBShop", "listingFBShop", "fbshop", "fbShopPercentage", "FB Shop"),
    ("instagramshop", "ListingInstagramShopController", "getViewListingInstagramShopData", "listingInstagramShop", "listingInstagramShop", "instagramshop", "instagramShopPercentage", "Instagram Shop"),
    ("shopifyb2c", "ListingShopifyB2CController", "getViewListingShopifyB2CData", "listingShopifyB2C", "listingShopifyB2C", "shopifyb2c", "shopifyB2CPercentage", "Shopify B2C"),
    ("mercariwoship", "ListingMercariWoShipController", "getViewListingMercariWoShipData", "listingMercariWoShip", "listingMercariWoShip", "mercariwoship", "mercariWoShipPercentage", "Mercari WoShip"),
    ("autods", "ListingAutoDSController", "getViewListingAutoDSData", "listingAutoDS", "listingAutoDS", "autods", "autoDSPercentage", "AutoDS"),
    ("poshmark", "ListingPoshmarkController", "getViewListingPoshmarkData", "listingPoshmark", "listingPoshmark", "poshmark", "poshmarkPercentage", "Poshmark"),
    ("spocket", "ListingSpocketController", "getViewListingSpocketData", "listingSpocket", "listingSpocket", "spocket", "spocketPercentage", "Spocket"),
    ("zendrop", "ListingZendropController", "getViewListingZendropData", "listingZendrop", "listingZendrop", "zendrop", "zendropPercentage", "Zendrop"),
    ("syncee", "ListingSynceeController", "getViewListingSynceeData", "listingSyncee", "listingSyncee", "syncee", "synceePercentage", "Syncee"),
    ("offerup", "ListingOfferupController", "getViewListingOfferupData", "listingOfferup", "listingOfferup", "offerup", "offerupPercentage", "OfferUp"),
    ("appscenic", "ListingAppscenicController", "getViewListingAppscenicData", "listingAppscenic", "listingAppscenic", "appscenic", "appscenicPercentage", "Appscenic"),
    ("yamibuy", "ListingYamibuyController", "getViewListingYamibuyData", "listingYamibuy", "listingYamibuy", "yamibuy", "yamibuyPercentage", "Yamibuy"),
    ("swgearexchange", "ListingSWGearExchangeController", "getViewListingSWGearExchangeData", "listingSWGearExchange", "listingSWGearExchange", "swgearexchange", "swGearExchangePercentage", "SW Gear Exchange"),
]

# Counts-only controllers (no blade)
COUNTS_ONLY = [
    ("faire", "ListingFaireController"),
    ("mercariwship", "ListingMercariWShipController"),
    ("shopifywholesale", "ListingShopifyWholesaleController"),
    ("business5core", "ListingBusiness5CoreController"),
]

VIEW_DATA_METHOD = '''
    public function {method}(Request $request)
    {{
        return response()->json([
            'status' => 200,
            'data' => AutomatedListingPage::rows('{key}'),
        ]);
    }}
'''

NR_REQ_METHOD = '''
    public function getNrReqCount()
    {{
        return ChannelListingRegistry::nrReqCountArray('{key}');
    }}
'''


def ensure_uses(src: str) -> str:
    needed = [
        "use App\\Support\\Marketplace\\AutomatedListingPage;",
        "use App\\Support\\Marketplace\\ChannelListingRegistry;",
    ]
    for u in needed:
        if u not in src:
            # insert after namespace block's first use or after namespace
            m = re.search(r"(namespace [^;]+;\s*)", src)
            if m:
                insert_at = m.end()
                src = src[:insert_at] + "\n" + u + src[insert_at:]
    return src


def replace_method(src: str, method_name: str, new_body: str) -> str:
    """Replace a public function ... { ... } by brace matching."""
    pattern = re.compile(rf"public function {re.escape(method_name)}\s*\(")
    m = pattern.search(src)
    if not m:
        # append before final closing brace of class
        src = src.rstrip()
        if src.endswith("}"):
            src = src[:-1] + new_body + "\n}\n"
        return src

    # find opening brace of method
    i = src.find("{", m.start())
    if i < 0:
        return src
    depth = 0
    j = i
    while j < len(src):
        if src[j] == "{":
            depth += 1
        elif src[j] == "}":
            depth -= 1
            if depth == 0:
                j += 1
                break
        j += 1
    return src[: m.start()] + new_body.strip() + "\n\n" + src[j:].lstrip()


def patch_controller(key: str, class_name: str, view_method: str | None, counts_only: bool = False) -> None:
    path = CTRL_DIR / f"{class_name}.php"
    if not path.exists():
        print(f"SKIP missing controller {path}")
        return
    src = path.read_text()
    src = ensure_uses(src)
    if view_method:
        src = replace_method(src, view_method, VIEW_DATA_METHOD.format(method=view_method, key=key))
    src = replace_method(src, "getNrReqCount", NR_REQ_METHOD.format(key=key))
    path.write_text(src)
    print(f"OK controller {class_name}")


def adapt_blade(key: str, blade_stem: str, route_slug: str, pct_var: str, title: str) -> None:
    src = EBAYTWO_BLADE.read_text()
    # Titles / wrap ids
    src = src.replace("Ebay Two Listing", f"{title} Listing")
    src = src.replace("EbayTwo Listing", f"{title} Listing")
    src = src.replace("ebaytwo-listing-wrap", f"{route_slug}-listing-wrap")
    src = src.replace("ebaytwo-listing-table", f"{route_slug}-listing-table")
    src = src.replace("ebayTwoListingTable", f"{route_slug}ListingTable")
    src = src.replace("ebaytwoListingTable", f"{route_slug}ListingTable")
    src = src.replace("/listing_ebaytwo/", f"/listing_{route_slug}/")
    src = src.replace("listing_ebaytwo.", f"listing_{route_slug}.")
    src = src.replace("listing.ebayTwo", f"listing.{route_slug}")
    src = src.replace("route('listing_ebaytwo.import')", f"route('listing_{route_slug}.import')")
    src = src.replace("route('listing_ebaytwo.export')", f"route('listing_{route_slug}.export')")
    src = src.replace("eBay_item_id", "listing_id" if key not in ("ebay", "ebaythree") else "eBay_item_id")
    # Link formatters for generic channels
    if key not in ("ebay", "ebaythree"):
        src = src.replace(
            "https://www.ebay.com/itm/",
            "GENERIC_BUYER_PREFIX",
        )
        # Replace formatEbayItemLink with generic listing_id links from status when possible
        src = re.sub(
            r"function formatEbayItemLink\(cell, type\) \{.*?\n        \}",
            """function formatEbayItemLink(cell, type) {
            const data = cell.getRow().getData();
            const isBuyer = type === 'buyer';
            const stored = isBuyer ? (data.buyer_link || '') : (data.seller_link || '');
            if (stored) {
                const label = isBuyer ? 'Buyer' : 'Seller';
                return `<a href="${escapeHtml(stored)}" target="_blank" rel="noopener noreferrer" class="listing-item-link"
                title="${escapeHtml(label + ' link')}" onclick="event.stopPropagation();">
                <i class="fas fa-external-link-alt"></i> ${label}
            </a>`;
            }
            return '<span class="listing-link-empty">—</span>';
        }""",
            src,
            count=1,
            flags=re.S,
        )
        src = src.replace("GENERIC_BUYER_PREFIX", "https://www.ebay.com/itm/")
        src = src.replace(
            "headerTooltip: 'Dynamic buyer link: https://www.ebay.com/itm/{item_id}'",
            "headerTooltip: 'Buyer link from listing status (or auto when available)'",
        )
        src = src.replace(
            "headerTooltip: 'Dynamic seller link: https://www.ebay.com/sh/lst/active?keyword={item_id}&action=search'",
            "headerTooltip: 'Seller link from listing status (or auto when available)'",
        )
    else:
        # keep ebay links; ensure wrap class unique
        pass

    # Cache key / percentage var leftovers
    src = src.replace("ebaytwo_marketplace_percentage", f"{route_slug}_marketplace_percentage")
    src = src.replace("$ebayTwoPercentage", f"${pct_var}")

    out = BLADE_DIR / f"{blade_stem}.blade.php"
    out.write_text(src)
    print(f"OK blade {out.name}")


def ensure_reverb_view_method():
    """Reverb controller may lack getViewListingReverbData / listingReverb page method."""
    path = CTRL_DIR / "ListingReverbController.php"
    if not path.exists():
        return
    src = path.read_text()
    if "function listingReverb" not in src:
        stub = '''
    public function listingReverb(Request $request)
    {
        return view('market-places.listing-market-places.listingReverb', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
        ]);
    }
'''
        src = replace_method(src, "listingReverb", stub) if "function listingReverb" in src else src
        if "function listingReverb" not in src:
            src = ensure_uses(src)
            src = src.rstrip()
            if src.endswith("}"):
                src = src[:-1] + stub + "\n}\n"
        path.write_text(src)


def main():
    ensure_reverb_view_method()
    for key, cls, view_m, page_m, blade, slug, pct, title in CHANNELS:
        patch_controller(key, cls, view_m)
        adapt_blade(key, blade, slug, pct, title)
    for key, cls in COUNTS_ONLY:
        patch_controller(key, cls, None, counts_only=True)
    print("Done.")


if __name__ == "__main__":
    main()
