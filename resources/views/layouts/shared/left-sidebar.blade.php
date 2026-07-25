<!-- ========== Left Sidebar Start ========== -->
<div class="leftside-menu">

    <!-- Brand Logo Light -->
    <a href="javascript:void(0)" class="logo logo-light sidebar-logo-static" aria-label="Application logo">
        <span class="logo">
            <img src="/images/1920 x 557_Product Manager.png" alt="logo" style="width: 200px;
    height: auto;">
        </span>
        <!--<span class="logo">-->
        <!--    <img src="/images/HR5LOGO.png" alt="small logo">-->
        <!--</span>-->
    </a>

    <div class="side-nav-title m-2">
        <input type="text" placeholder="Search Menu" class="form-control form-control-sm" id="searchMenuItem" />
    </div>


    <!-- Brand Logo Dark -->
    <a href="javascript:void(0)" class="logo logo-dark sidebar-logo-static" aria-label="Application logo">
        <span class="logo">
            <img src="/images/1920 x 557_Product Manager.png" alt="dark logo">
        </span>
        <!--<span class="logo">-->
        <!--    <img src="/images/HR5LOGO.png" alt="small logo">-->
        <!--</span>-->
    </a>

    <!-- Sidebar -left -->
    <div class="h-100" id="leftside-menu-container" data-simplebar>
        <!--- Sidemenu -->
        <ul class="side-nav">



            <li class="side-nav-title">Main</li>


            {{-- Action Manager --}}
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#actionManager" aria-expanded="false" aria-controls="actionManager"
                    class="side-nav-link">
                    <i class="ri-settings-3-line"></i>
                    <span>Action Manager</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="actionManager">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('tasks.automated') }}">
                                <i class="ri-robot-line me-2"></i>Automated Tasks
                            </a>
                        </li>
                        <li>
                            <a href="{{ url('tasks') }}">
                                <i class="ri-task-line me-2"></i>Task Manager
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('tasks.summary') }}">
                                <i class="ri-file-list-3-line me-2"></i>Task Summary
                            </a>
                        </li>
                        <li>
                            <a href="{{ url('tasks/deleted') }}">
                                <i class="ri-delete-bin-line me-2"></i>Archived Tasks
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('dar.index') }}">
                                <i class="ri-clipboard-line me-2"></i>DAR
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a href="{{ route('all.marketplace.master') }}" class="side-nav-link">
                    <i class="ri-file-line"></i>
                    <span>Active Channels Master</span>
                </a>
            </li>

            <li class="side-nav-item">
                <a href="{{ route('variations.verify.masters') }}" class="side-nav-link variations-verify-masters-nav">
                    <i class="ri-layout-grid-line"></i>
                    <span>Variations Verify Masters</span>
                    @php
                        $variationsVerifyMismatchTotal = \App\Http\Controllers\MarketPlace\VariationsVerifyMasterController::totalMismatchCountForSidebar();
                    @endphp
                    @if($variationsVerifyMismatchTotal > 0)
                        <span class="badge rounded-pill ms-auto variations-verify-mismatch-badge" title="Sum of Mismatch across all Listing Variation Verify channels">{{ number_format($variationsVerifyMismatchTotal) }}</span>
                    @endif
                </a>
            </li>

            {{-- Marketplace Manager (Shopify ↔ marketplace sync hub) --}}
            <li class="side-nav-item">
                <a href="{{ route('marketplace.manager.index') }}" class="side-nav-link">
                    <i class="ri-links-line"></i>
                    <span>Marketplace Manager</span>
                </a>
            </li>

            <li class="side-nav-item">
                <a href="{{ route('missing.listing') }}" class="side-nav-link missing-listing-nav">
                    <i class="ri-error-warning-line"></i>
                    <span>Missing Listing</span>
                    @php
                        $missingListingCount = \App\Support\Badges\AllMarketplaceMasterBadgeCalculator::missingLCountForSidebar();
                    @endphp
                    @if($missingListingCount > 0)
                        <span class="badge rounded-pill ms-auto missing-listing-badge" title="Missing L total from each channel listing page">{{ number_format($missingListingCount) }}</span>
                    @endif
                </a>
            </li>

            <li class="side-nav-item">
                <a href="{{ route('map.issues') }}" class="side-nav-link map-issues-nav">
                    <i class="ri-node-tree"></i>
                    <span>Missing Mapping</span>
                    @php
                        $mapIssuesNmapCount = \App\Support\Badges\AllMarketplaceMasterBadgeCalculator::nmapCountForSidebar();
                    @endphp
                    @if($mapIssuesNmapCount > 0)
                        <span class="badge rounded-pill ms-auto map-issues-nmap-badge" title="N Map total from each channel pricing page (Missing Mapping)">{{ number_format($mapIssuesNmapCount) }}</span>
                    @endif
                </a>
            </li>

            <li class="side-nav-item">
                <a href="{{ route('compliance-certificates.index') }}" class="side-nav-link">
                    <i class="ri-shield-check-line"></i>
                    <span>Compliance Certificates</span>
                </a>
            </li>

            {{-- Audit Master --}}
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#auditMaster" aria-expanded="false"
                    aria-controls="auditMaster" class="side-nav-link">
                    <i class="ri-file-search-line"></i>
                    <span>Audit Master</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="auditMaster">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('audit.master.cc.messages') }}">
                                <i class="ri-message-3-line me-2"></i>CC Messages Audit
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('audit.master.cc.return') }}">
                                <i class="ri-arrow-go-back-line me-2"></i>CC Return Audit
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('audit.master.cc.replacement') }}">
                                <i class="ri-refresh-line me-2"></i>CC Replacement Audit
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('audit.master.cc.shipping') }}">
                                <i class="ri-truck-line me-2"></i>CC Shipping Audit
                            </a>
                        </li>
                    </ul>
                </div>
            </li>


            {{-- KPI --}}
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#kpiSidebarPages" aria-expanded="false"
                    aria-controls="kpiSidebarPages" class="side-nav-link">
                    <i class="ri-bar-chart-line"></i>
                    <span>KPI</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="kpiSidebarPages">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('kpi.shipping.tabulator') }}">
                                <i class="ri-truck-line me-2"></i>Kpi Shipping
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#channelSidebarPages" aria-expanded="false"
                    aria-controls="sidebarPages" class="side-nav-link">
                    <i class="ri-wallet-2-line"></i>
                    <span>Channel</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="channelSidebarPages">
                    <ul class="side-nav-second-level">
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#activeChannelMasyer" aria-expanded="false"
                                aria-controls="activeChannelMasyer">
                                <span>Channel Master</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="activeChannelMasyer">
                                <ul class="side-nav-third-level">

                                   
                                    <!-- Start Nikhil Code -->
                                    {{-- <li>
                                        <a href="{{ route('channel.ads.master') }}">AD Masters</a>
                                    </li> --}}
                                    <li>
                                        <a href="{{ route('channel.adv.master') }}">ADV Masters</a>
                                    </li>

                                    <!-- End Nikhil Code  -->
                                    <li>
                                        <a href="{{ route('opportunity.index') }}">Opportunities</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('application.approvals.index') }}">Application &
                                            Approvals</a>
                                    </li>
                                    <li>
                                        <a href=" {{ route('setup.account.index') }}">Setup Account & Shop</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#movementAnalysisMenu" aria-expanded="false"
                                aria-controls="movementAnalysisMenu">
                                <span>Movement Analysis</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="movementAnalysisMenu">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ route('channel.movement.analysis') }}" target="_blank">Sales
                                            and Analysis</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li>
                            <a href="{{ route('new.marketplaces.dashboard') }}">New Marketplaces</a>
                        </li>

                        <li>
                            <a href="{{ route('promotion.master') }}">Promotion Master</a>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#account-health-master" aria-expanded="false"
                                aria-controls="account-health-master" class="side-nav-link">
                                <span>Account Health Master</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="account-health-master">
                                <ul class="side-nav-second-level">
                                    <li>
                                        <a href="{{ route('account.health.master.channel.dashboard') }}"
                                            target="_blank">Dashboard Account Health</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('account.health.master.tabulator') }}">CC Message Health</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('customer.care.health.tabulator') }}">Customer Care Health</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('shipping.health.tabulator') }}">Shipping Audit</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('shipping.health.overview.tabulator') }}">Shipping Health</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('odr.rate') }}">ODR Rate</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('fullfillment.rate') }}">Fulfillment Rate</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('valid.tracking.rate') }}">Valid Tracking Rate</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('late.shipment.rate') }}">Late Shipment</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('on.time.delivery.rate') }}">On Time Delivery</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('negative.seller.rate') }}">Negative Seller</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('a_z.claims.rate') }}">A-Z Claims</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('escalated.claims.tabulator') }}">Escalated Claims</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('voilation.rate') }}">Voilation/Compliance</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('refund.rate') }}">Refunds / Returns</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li>
                            <a href="{{ route('traffic.master.list') }}">Traffic Master</a>
                        </li>
                        <li>
                            <a href="{{ route('expenses.master') }}">Expenses Analysis</a>
                        </li>
                        <li>
                            <a href="{{ route('health.master') }}">Health Analysis</a>
                        </li>
                        <li>
                            <a href="{{ route('channel.master', ['channels', 'returns-analysis']) }}">Listing
                                analysis</a>
                        </li>
                        <li>
                            <a href="{{ route('channel.master', ['channels', 'expenses-analysis']) }}">Shipping
                                analysis</a>
                        </li>
                    </ul>
                </div>
            </li>

            {{-- CRM --}}
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#crmNav" aria-expanded="false" aria-controls="crmNav"
                    class="side-nav-link">
                    <i class="ri-customer-service-2-line"></i>
                    <span>CRM</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="crmNav">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('crm.dashboard') }}">
                                <i class="ri-dashboard-line me-2"></i>Dashboard CRM
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('crm.follow-ups.index') }}">
                                <i class="ri-calendar-check-line me-2"></i>Follow-ups
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('crm.shopify.customers.index') }}">
                                <i class="ri-store-2-line me-2"></i>Shopify customers
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('crm.shopify.orders.index') }}">
                                <i class="ri-shopping-bag-3-line me-2"></i>Shopify orders
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('google-maps-data-extractor.index') }}"
                                class="{{ request()->routeIs('google-maps-data-extractor.*') ? 'active' : '' }}">
                                <i class="ri-map-pin-line me-2"></i>Data Extractor
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarCustomerCare" aria-expanded="false"
                    aria-controls="sidebarCustomerCare" class="side-nav-link">
                    <i class="ri-customer-service-2-line"></i>
                    <span>Customer Care</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarCustomerCare">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('customer.care') }}">Overview</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.cc.messages.returns') }}">CC Message</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.report') }}">Report</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.cc.shipping') }}">CC Shipping</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.refunds') }}">Refunds</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.faq.customers.index') }}"
                               class="{{ request()->routeIs('customer.care.faq.customers.*') ? 'active' : '' }}">
                                FAQ / FFP Customers
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.orders.on.hold') }}">on hold / Mapping</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.qc.and.packing') }}">QC PKG issues</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.label.issues') }}">Label Issues</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.dispatch.issues') }}">All Issues</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.dispatch.issues.only') }}">Dispatch Issues</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.dispatch.carrier.and.claim') }}">Carrier Claims</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.dispatch.carrier.issue') }}">Carrier Scan Issues</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.dispatch.chargeback.issues') }}">Chargeback Issues</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.listing.issue') }}">Listing Issue</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.c.care.issues') }}">C-care Issues</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.other.issues') }}">Other Issues</a>
                        </li>
                        <li>
                            <a href="{{ route('customer.care.followups') }}">Follow Up CC</a>
                        </li>
                        <li>
                            <a href="{{ route('incoming.view') }}">Incoming</a>
                        </li>
                        <li>
                            <a href="{{ route('incoming.return.view') }}">Incoming Return</a>
                        </li>
                        <li>
                            <a href="{{ route('outgoing.view') }}">Outgoing</a>
                        </li>
                        <li>
                            <a href="{{ route('claim.reimbursement') }}">Claim & Reimbursement</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a href="{{ url('/cvr-master') }}" class="side-nav-link">
                    <i class="ri-file-line"></i>
                    <span>CVR Master</span>
                </a>
            </li>


            <li class="side-nav-item">
                <a href="{{ route('any', 'index') }}" class="side-nav-link">
                    <i class="ri-dashboard-3-line"></i>
                    <span> Dashboard Master </span>
                </a>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#fbaPricingMaster" aria-expanded="false"
                    aria-controls="sidebarPages22" class="side-nav-link">
                    <i class="ri-user-line"></i>
                    <span>FBA Sales </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="fbaPricingMaster">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ url('/fba-view-page') }}">FBA pricing</a>
                        </li>

                        <li>
                            <a href="{{ url('fba-dispatch-page') }}">FBA Dispatch</a>
                        </li>

                        <li>
                            <a href="{{ url('fba-ads-keywords') }}">FBA Ads Keywords</a>
                        </li>

                        <li>
                            <a href="{{ url('fba-ads-pt') }}">FBA Ads Performance</a>
                        </li>
                    </ul>
                </div>

            </li>

            {{-- Inventory Management --}}


            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#invsidebarPages" aria-expanded="false"
                    aria-controls="sidebarPages" class="side-nav-link">
                    <i class="ri-archive-drawer-line"></i>
                    <span>Inventory Management</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="invsidebarPages">
                    <ul class="side-nav-second-level">

                        <li>
                            <a href="{{ route('view-inventory-data') }}">Inventory Main</a>
                        </li>
                        {{-- <li>
                            <a href="{{ route('inventory.manage.index') }}">
                                <i class="ri-stack-line me-1"></i>Manage Inventory & Sync
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('inventory.import.index') }}">
                                <i class="ri-file-upload-line me-1"></i>Shopify CSV Import
                            </a>
                        </li> --}}
                        <li>
                            <a href="{{ route('verify-adjust') }}">Verifications & Adjustments</a>
                        </li>
                        <li>
                            <a href="{{ route('lost-gain') }}">Lost/Gain</a>
                        </li>
                        <li>
                            <a href="{{ route('incoming.view') }}">Incoming</a>
                        </li>
                        <li>
                            <a href="{{ route('incoming.return.view') }}">Incoming Return</a>
                        </li>
                        <li>
                            <a href="{{ route('view-inventory-incoming-return-trash') }}">View Inventory (Trash Godown)</a>
                        </li>
                        <li>
                            <a href="{{ route('view-inventory-incoming-return-open-box') }}">View Inventory (Open Box Godown)</a>
                        </li>
                        <li>
                            <a href="{{ route('outgoing.view') }}">Outgoing</a>
                        </li>
                        <li>
                            <a href="{{ route('inventory.spares.dashboard') }}">Spare Parts Dashboard</a>
                        </li>
                        <li>
                            <a href="{{ route('stock.adjustment.view') }}">Stock Adjustment</a>
                        </li>
                        <li>
                            <a href="{{ route('stock.transfer.view') }}">Stock Transfer (WH)</a>
                        </li>
                        {{-- <li>
                            <a href="{{ route('stock.balance.view') }}">Stock Balance / TRF</a>
                        </li> --}}

                        <li>
                            <a href="{{ url('stock-balance-tabulator') }}">Stock Balance TRF</a>
                        </li>
                        <li>
                            <a href="{{ route('stock.balance.alternate') }}">Stock Alternate</a>
                        </li>
                        <li>
                            <a href="{{ route('combo.trf') }}">Combo TRF</a>
                        </li>
                        <li>
                            <a href="{{ route('incoming.orders.view') }}">Incoming Orders</a>
                        </li>
                        {{-- <li>
                            <a href="#">Trash Entries</a>
                        </li> --}}
                        <li>
                            <a href="#">Pallete Sales</a>
                        </li>
                    </ul>
                </div>
            </li>

            {{-- Sales & History Reports --}}
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#salesHistoryMenu" aria-expanded="false"
                    aria-controls="salesHistoryMenu" class="side-nav-link">
                    <i class="ri-bar-chart-box-line"></i>
                    <span>Sales & History Reports</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="salesHistoryMenu">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('inventory-history.index') }}">
                                <i class="ri-history-line me-2"></i>Inventory History
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('shopify-orders.index') }}">
                                <i class="ri-shopping-cart-2-line me-2"></i>Sales by Source
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('shopify-raw-data.index') }}">
                                <i class="ri-database-2-line me-2"></i>All Orders
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('shopify.index') }}">
                                <i class="ri-shopify-line me-2"></i>Shopify
                            </a>
                        </li>
                    </ul>
                </div>
            </li>

            {{-- Inventory Warehouse --}}

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#waresidebarPages" aria-expanded="false"
                    aria-controls="sidebarPages" class="side-nav-link">
                    <i class="ri-building-4-line"></i>
                    <span>Inventory Warehouse</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="waresidebarPages">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('list_all_warehouses') }}">List All Warehouses</a>
                        </li>


                        <li>
                            <a href="#">Inventory Locator</a>
                        </li>
                        <li>
                            <a href="#">Transfers</a>
                        </li>
                        <li>
                            <a href="{{ route('showroom.godown') }}">Main Godown</a>
                        </li>
                        <li>
                            <a href="{{ route('main.godown') }}">Showroom Godown</a>
                        </li>
                        <li>
                            <a href="{{ route('returns.godown') }}">Returns Godown</a>
                        </li>
                        <li>
                            <a href="{{ route('openbox.godown') }}">Open Box Godown</a>
                        </li>
                        <li>
                            <a href="{{ route('useditem.godown') }}">Used Item Godown</a>
                        </li>
                        <li>
                            <a href="{{ route('trash.godown') }} ">Trash Godown</a>
                        </li>
                    </ul>
                </div>
            </li>

            {{-- Listing Master --}}
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarListingMaster" aria-expanded="false" aria-controls="sidebarListingMaster" class="side-nav-link">
                    <i class="ri-list-check-2"></i>
                    <span>Listing Master</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarListingMaster">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('listing.master.amz.data') }}">Amz Data</a>
                        </li>
                    </ul>
                </div>
            </li>

            {{-- Listing Mirror --}}
            <li class="side-nav-item">
                <a href="{{ route('listing-mirror.index') }}" class="side-nav-link">
                    <i class="ri-refresh-line"></i>
                    <span>Listing Mirror</span>
                </a>
            </li>

            {{-- LMP's Master --}}
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#lmpsMaster" aria-expanded="false" aria-controls="lmpsMaster"
                    class="side-nav-link">
                    <i class="ri-price-tag-3-line"></i>
                    <span>LMP's Master</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="lmpsMaster">
                   <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ url('/repricer/amazon-search') }}"
                                            class="{{ request()->is('repricer/amazon-search*') ? 'active' : '' }}">
                                            Amazon Competitors
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ url('/repricer/ebay-search') }}"
                                            class="{{ request()->is('repricer/ebay-search*') ? 'active' : '' }}">
                                            eBay Competitors
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ url('/repricer/reverb-search') }}"
                                            class="{{ request()->is('repricer/reverb-search*') ? 'active' : '' }}">
                                            Reverb Competitors
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ url('/repricer/macy-search') }}"
                                            class="{{ request()->is('repricer/macy-search*') ? 'active' : '' }}">
                                            Macy's Competitors
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ url('/repricer/bestbuy-search') }}"
                                            class="{{ request()->is('repricer/bestbuy-search*') ? 'active' : '' }}">
                                            Best Buy Competitors
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ url('/repricer/google-search') }}"
                                            class="{{ request()->is('repricer/google-search*') ? 'active' : '' }}">
                                            Google Competitors
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ url('/repricer/tiktok-search') }}"
                                            class="{{ request()->is('repricer/tiktok-search*') ? 'active' : '' }}">
                                            TikTok Competitors
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ url('/repricer/shein-search') }}"
                                            class="{{ request()->is('repricer/shein-search*') ? 'active' : '' }}">
                                            Shein Competitors
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ url('/repricer/amz-comp-jungle') }}"
                                            class="{{ request()->is('repricer/amz-comp-jungle*') ? 'active' : '' }}">
                                            Amz Comp Jungle
                                        </a>
                                    </li>
                                </ul>
                </div>
            </li>

            {{-- ── Facebook ─────────────────────────────────────── --}}
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarFacebookSheets" aria-expanded="false"
                    aria-controls="sidebarFacebookSheets" class="side-nav-link">
                    <i class="ri-facebook-circle-line"></i>
                    <span>Facebook</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarFacebookSheets">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('facebook.all.ads.sheet') }}">Meta Ads All</a>
                        </li>
                        <li>
                            <a href="{{ route('facebook.ads.audit') }}">Facebook Ads Audit</a>
                        </li>
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarFacebookAdsTypes" aria-expanded="false"
                                aria-controls="sidebarFacebookAdsTypes">
                                <span>Facebook</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarFacebookAdsTypes">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ route('facebook.ads.channel') }}">All</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('facebook.ads.channel.group.video') }}">G Video</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('facebook.ads.channel.group.carousal') }}">G Carousal</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('facebook.ads.channel.parent.video') }}">P Video</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('facebook.ads.channel.parent.carousal') }}">P Carousal</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarInstagramAdsTypes" aria-expanded="false"
                                aria-controls="sidebarInstagramAdsTypes">
                                <span>Instagram</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarInstagramAdsTypes">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ route('instagram.ads.channel') }}">All</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('instagram.ads.channel.group.video') }}">G Video</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('instagram.ads.channel.group.carousal') }}">G Carousal</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('instagram.ads.channel.parent.video') }}">P Video</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('instagram.ads.channel.parent.carousal') }}">P Carousal</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li>
                            <a href="{{ route('facebook.video.ads.sheet') }}">Facebook Video Ads Sheet</a>
                        </li>
                        <li>
                            <a href="{{ route('facebook.carousal.ads.sheet') }}">Facebook Carousal Ads Sheet</a>
                        </li>
                        <li>
                            <a href="{{ route('music.store.ads.sheet') }}">Music Store</a>
                        </li>
                        <li>
                            <a href="{{ route('music.school.ads.sheet') }}">Music School</a>
                        </li>
                    </ul>
                </div>
            </li>

            {{-- ── TikTok ───────────────────────────────────────── --}}
            <li class="side-nav-item">
                <a href="{{ route('tiktok.video.ads') }}" class="side-nav-link">
                    <i class="ri-music-2-line"></i>
                    <span>TikTok Video Ads</span>
                </a>
            </li>

            {{-- ── YouTube ──────────────────────────────────────── --}}
            <li class="side-nav-item">
                <a href="{{ route('youtube.video.ads') }}" class="side-nav-link">
                    <i class="ri-youtube-line"></i>
                    <span>YouTube Video Ads</span>
                </a>
            </li>

            {{-- ── Video Ads Master (Tabulator sheet — own controller) ── --}}
            <li class="side-nav-item">
                <a href="{{ route('video.ads.master') }}" class="side-nav-link">
                    <i class="ri-video-add-line"></i>
                    <span>Video Request & Check</span>
                </a>
            </li>

            {{-- ── Ads Masters (cross-channel rollup dashboards) ── --}}
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarAdsMastersList" aria-expanded="false"
                    aria-controls="sidebarAdsMastersList" class="side-nav-link">
                    <i class="ri-bar-chart-grouped-line"></i>
                    <span>Ads Masters</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarAdsMastersList">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('advertisement.master') }}">Advertisement Master</a>
                        </li>
                        <li>
                            <a href="{{ route('shopify.ads.master') }}">Shopify Ads Master</a>
                        </li>
                        <li>
                            <a href="{{ route('advertisement.variations.ads') }}">Variations Ads</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarPages2" aria-expanded="false"
                    aria-controls="sidebarPages2" class="side-nav-link">
                    <i class="ri-store-3-line"></i>
                    <span>Marketing Masters</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarPages2">
                    <ul class="side-nav-second-level">

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarvideoSales" aria-expanded="false"
                                aria-controls="sidebarvideoSales">
                                <span>Video Directory</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarvideoSales">
                                <ul class="side-nav-third-level">
                                    <li class="side-nav-item">
                                        <a data-bs-toggle="collapse" href="#videoSalesSubmenu" aria-expanded="false"
                                            aria-controls="videoSalesSubmenu">
                                            <span>Product Group</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="videoSalesSubmenu">
                                            <ul class="side-nav-third-level">
                                                <li>
                                                    <a href="{{ route('mm.video.posted') }}">Product Videos</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('mm.product.video.upload') }}">Product Video
                                                        Upload</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                    <li class="side-nav-item">
                                        <a data-bs-toggle="collapse" href="#videoSalesSubmenu2" aria-expanded="false"
                                            aria-controls="videoSalesSubmenu2">
                                            <span>Assembly Group</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="videoSalesSubmenu2">
                                            <ul class="side-nav-third-level">
                                                <li>
                                                    <a href="{{ route('mm.assembly.video.posted') }}">Assembly
                                                        Video</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('mm.assembly.video.upload') }}">Assembly
                                                        Video Upload</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                    <li class="side-nav-item">
                                        <a data-bs-toggle="collapse" href="#videoSalesSubmenu3" aria-expanded="false"
                                            aria-controls="videoSalesSubmenu3">
                                            <span>3D Video Group</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="videoSalesSubmenu3">
                                            <ul class="side-nav-third-level">
                                                <li>
                                                    <a href="{{ route('mm.3d.video.posted') }}">3D Video</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('mm.3d.video.upload') }}">3D Video
                                                        Upload</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>

                                    <li class="side-nav-item">
                                        <a data-bs-toggle="collapse" href="#videoSalesSubmenu4" aria-expanded="false"
                                            aria-controls="videoSalesSubmenu4">
                                            <span>360 Video Group</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="videoSalesSubmenu4">
                                            <ul class="side-nav-third-level">
                                                <li>
                                                    <a href="{{ route('mm.360.video.posted') }}">360 Video</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('mm.360.video.upload') }}">360 Video
                                                        Upload</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>


                                    <li class="side-nav-item">
                                        <a data-bs-toggle="collapse" href="#videoSalesSubmenu5" aria-expanded="false"
                                            aria-controls="videoSalesSubmenu5">
                                            <span>Benefits Video Group</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="videoSalesSubmenu5">
                                            <ul class="side-nav-third-level">
                                                <li>
                                                    <a href="{{ route('mm.benefits.video.posted') }}">Benefits
                                                        Video</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('mm.benefits.video.upload') }}">Benefits
                                                        Video Upload</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                    <li class="side-nav-item">
                                        <a data-bs-toggle="collapse" href="#videoSalesSubmenu6" aria-expanded="false"
                                            aria-controls="videoSalesSubmenu6">
                                            <span>DIY Video Group</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="videoSalesSubmenu6">
                                            <ul class="side-nav-third-level">
                                                <li>
                                                    <a href="{{ route('mm.diy.video.posted') }}">DIY Video</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('mm.diy.video.upload') }}">DIY Video
                                                        Upload</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                    <li class="side-nav-item">
                                        <a data-bs-toggle="collapse" href="#shoppable" aria-expanded="false"
                                            aria-controls="shoppable">
                                            <span>Shoppable Video Group</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="shoppable">
                                            <ul class="side-nav-third-level">
                                                {{-- <li>
                                                    <a href="{{ route('one.ration') }}">1:1 RATIO</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('four.ration') }}">4:5 RATIO</a>
                                                </li> --}}
                                                <li>
                                                    <a href="{{ route('nine.ration') }}">9:16 RATIO</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('sixteen.ration') }}">16:9 RATIO</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#video-ads-master" aria-expanded="false"
                                aria-controls="video-ads-master" class="side-nav-link">
                                <span>Video Request & Check</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="video-ads-master">
                                <ul class="side-nav-second-level">
                                    <li>
                                        <a href="{{ route('facebook.ads.master') }}">Facebook Ads</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('facebook.feed.ads.master') }}">Facebook In Feed</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('facebook.reel.ads.master') }}">Facebook Reel Ads</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('instagram.ads.master') }}">Instagram Ads</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('instagram.feed.ads.master') }}">Instagram In Feed</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('instagram.reel.ads.master') }}">Instagram Reel Ads</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('youtube.ads.master') }}">YouTube Ads</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('youtube.shorts.ads.master') }}">YouTube Shorts Ads</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('tiktok.ads.master') }}">Tik Tok Video Ads</a>
                                    </li>
                                </ul>
                            </div>
                        </li>


                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#meta-ads-master" aria-expanded="false"
                                aria-controls="meta-ads-master" class="side-nav-link">
                                <span>Meta Ads Manager</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="meta-ads-master">
                                <ul class="side-nav-second-level">
                                    <li>
                                        <a href="{{ route('meta.ads.manager.dashboard') }}">Dashboard Meta Ads</a>
                                    </li>
                                    @if (Route::has('meta.ads.saved.raw'))
                                    <li>
                                        <a href="{{ route('meta.ads.saved.raw') }}">All Raw Meta Ads</a>
                                    </li>
                                    @endif
                                </ul>
                            </div>
                        </li>

                        <li>
                            <a data-bs-toggle="collapse" href="#lqsSubmenu" aria-expanded="false"
                                aria-controls="lqsSubmenu">
                                <span>LQS Masters</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="lqsSubmenu">
                                <ul class="side-nav-fourth-level">
                                    <li>
                                        <a href="{{ route('ebaycvrLQS.master') }}">Ebay LQS - CVR</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('lqs.amz.view') }}">LQS Amz</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarLayouts" aria-expanded="false"
                    aria-controls="sidebarLayouts" class="side-nav-link">
                    <i class="ri-store-line"></i> <!-- Marketplace icon -->
                    <span class="menu-arrow"></span>
                    <span>Marketplace </span>
                </a>
                <div class="collapse" id="sidebarLayouts">
                    <ul class="side-nav-second-level">

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarSecondLevel" aria-expanded="false"
                                aria-controls="sidebarSecondLevel">
                                <span> Amazon FBM </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarSecondLevel">
                                <ul class="side-nav-third-level">

                                    {{-- Amazon FBA submenu --}}



                                    <li>
                                        <a href="{{ route('listing.amazon') }}">Listing Amazon</a>
                                    </li>
                                    <li>

                                        <a href="{{ url('/amazon-tabulator-view') }}">Analytics Amz
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('amz.variation.verify') }}">Amazon Ads Variation Verification</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('amz.listing.variation.verify') }}">Amz Listing Variation Verify</a>
                                    </li>
                                    <li>

                                        <a href="{{ url('/amazonpricing-cvr-tabular') }}">FBM SEO Amz
                                        </a>
                                    </li>


                                    {{-- <li>
                                        <a href="{{ route('amazon.ad-running.list') }}">Amz FBM Ad Running</a>
                                    </li> --}}
                                    {{-- <li>
                                        <a href="{{ route('amazon.campaign.reports') }}">Amazon Ads Report</a>
                                    </li> --}}
                                    {{-- <li>
                                        <a data-bs-toggle="collapse" href="#amazonAdsReport" aria-expanded="false"
                                            aria-controls="amazonAdsReport">
                                            <span>Amz FBM Ads Report</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="amazonAdsReport">
                                            <ul class="side-nav-fourth-level">
                                                <li>
                                                    <a href="{{ route('amazon.kw.ads') }}">Amz FBM KW Ads</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('amazon.pt.ads') }}">Amz FBM PT Ads</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('amazon.hl.ads') }}">Amz FBM HL Ads</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li> --}}
                                    {{-- <li>
                                        <a data-bs-toggle="collapse" href="#amazonACOS" aria-expanded="false"
                                            aria-controls="amazonACOS">
                                            <span>Amz FBM ACOS Control</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="amazonACOS">
                                            <ul class="side-nav-fourth-level">
                                                <li>
                                                    <a href="{{ route('amazon.acos.kw.control') }}">Amz FBM ACOS
                                                        KW</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('amazon.acos.pt.control') }}">Amz FBM ACOS
                                                        PT</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li> --}}
                                    {{-- <li>
                                        <a data-bs-toggle="collapse" href="#amazonPinkDilAds" aria-expanded="false"
                                            aria-controls="amazonPinkDilAds">
                                            <span>Amz FBM Pink Dil Ads</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="amazonPinkDilAds">
                                            <ul class="side-nav-fourth-level">
                                                <li>
                                                    <a href="{{ route('amazon.pink.dil.kw.ads') }}">Amz FBM Pink
                                                        KW</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('amazon.pink.dil.pt.ads') }}">Amz FBM Pink
                                                        PT</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li> --}}
                                    <li>
                                        <a data-bs-toggle="collapse" href="#amazonBudget" aria-expanded="false"
                                            aria-controls="amazonBudget">
                                            <span>FBM AD Amz</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="amazonBudget">
                                            <ul class="side-nav-forth-level amz-fbm-ad-submenu">
                                                <li>
                                                    <a href="{{ route('amazon.ads.all') }}">Ads All Amz</a>
                                                </li>
                                                @if (Route::has('amazon.ads.campaign-link.index'))
                                                <li>
                                                    <a href="{{ route('amazon.ads.campaign-link.index') }}">Campaign Link Amz</a>
                                                </li>
                                                @endif
                                                @if (Route::has('amazon.ads.negative-link.index'))
                                                <li>
                                                    <a href="{{ route('amazon.ads.negative-link.index') }}">Negative Link Amz</a>
                                                </li>
                                                @endif
                                                <li>
                                                    <a href="{{ route('amazon.ads.audit') }}">Ads Audit Amz</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('amazon.ads.missing') }}" class="amz-ads-missing-nav">
                                                        Ads Missing Amz
                                                        @php $amzAdsMissingCount = \App\Http\Controllers\AmazonAdsMissingController::missingTotalCount(); @endphp
                                                        @if($amzAdsMissingCount > 0)
                                                            <span class="badge bg-danger rounded-pill">{{ $amzAdsMissingCount }}</span>
                                                        @endif
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('amazon.ads.categories') }}">Ads Categories Amz</a>
                                                </li>
                                                {{-- <li>
                                                    <a href=" {{ route('amazon-sp.amz-utilized-bgt-kw') }} "> >
                                                        UTILIZED BGT KW</a>
                                                </li>
                                                <li>
                                                    <a href=" {{ route('amazon-sb.amz-utilized-bgt-hl') }} "> >
                                                        UTILIZED BGT HL</a>
                                                </li>
                                                <li>
                                                    <a href=" {{ route('amazon-sp.amz-utilized-bgt-pt') }} "> >
                                                        UTILIZED BGT PT</a>
                                                </li>
                                                <li>
                                                    <a href=" {{ route('amazon-sp.amz-under-utilized-bgt-kw') }} ">
                                                        UNDER-UTILIZED BGT KW</a>
                                                </li>
                                                <li>
                                                    <a href=" {{ route('amazon-sb.amz-under-utilized-bgt-hl') }} ">
                                                        UNDER-UTILIZED BGT HL</a>
                                                </li>
                                                <li>
                                                    <a href=" {{ route('amazon-sp.amz-under-utilized-bgt-pt') }} ">
                                                        UNDER-UTILIZED BGT PT</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('amazon.amz-correctly-utilized-bgt-kw') }}">CORRECTLY
                                                        UTILIZED KW</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('amazon.amz-correctly-utilized-bgt-hl') }}">CORRECTLY
                                                        UTILIZED HL</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('amazon.amz-correctly-utilized-bgt-pt') }}">CORRECTLY
                                                        UTILIZED PT</a>
                                                </li> --}}
                                            </ul>
                                        </div>
                                    </li>
                                    {{-- <li>
                                        <a data-bs-toggle="collapse" href="#amazonCpc" aria-expanded="false"
                                            aria-controls="amazonCpc">
                                            <span>Amz FBM CPC 0</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="amazonCpc">
                                            <ul class="side-nav-fourth-level">
                                                <li>
                                                    <a href="{{ route('amazon.kw.cpc.zero.list') }}">KW CPC ZERO</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('amazon.pt.cpc.zero.list') }}">PT CPC ZERO</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li> --}}
                                    {{-- <li>
                                        <a data-bs-toggle="collapse" href="#amazonFbaACOS" aria-expanded="false"
                                            aria-controls="amazonFbaACOS">
                                            <span>Amazon FBA ACOS</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="amazonFbaACOS">
                                            <ul class="side-nav-fourth-level">
                                                <li>
                                                    <a href="{{ route('amazon.fba.acos.kw.control') }}">KW
                                                        Control</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('amazon.fba.acos.pt.control') }}">PT
                                                        Control</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li> --}}
                                    {{-- Add EXtra For Amazon Pricing --}}
                                </ul>
                            </div>
                        </li>


                        {{-- <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarFBAcvr" aria-expanded="false"
                                aria-controls="sidebarFBAcvr">
                                <span>AMZ FBA</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarFBAcvr">
                                <ul class="">
                                    <li>
                                        <a href="{{ url('fba-analytics-page') }}">AMZ Sales</a>
                                    </li>
                                </ul>
                            </div>
                        </li> --}}

                        {{-- Amazon Competitors--}}
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarThirdLevel" aria-expanded="false"
                                aria-controls="sidebarThirdLevel">
                                <span> eBay </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarThirdLevel">
                                <ul class="side-nav-third-level">

                                    <li>
                                        <a href="{{ route('listing.ebay') }}">Listing EBay</a>
                                    </li>

                                    <li>
                                        <a href="{{ route('ebay.listing.variation.verify') }}">Ebay Listing Variation Verify</a>
                                    </li>

                                    <li>
                                        <a href="{{ url('ebay-tabulator-view') }}">Ebay - Analytics
                                        </a>
                                    </li>

                                    {{-- <li>
                                        <a href="{{ url('ebay-pricing-data') }}">Ebay Pricing Data</a>
                                    </li> --}}

                                    {{-- <li>
                                        <a href="{{ url('ebay-pricing-increase') }}">Ebay Pricing
                                            Increase </a>
                                    </li> --}}
                                    {{-- <li>
                                        <a href="{{ route('ebay.views.data') }}">Ebay Views</a>
                                    </li> --}}
                                    {{-- <li>
                                        <a data-bs-toggle="collapse" href="#ebayAcosSubmenu" aria-expanded="false"
                                            aria-controls="ebayAcosSubmenu">
                                            <span>Ebay ACOS Control</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="ebayAcosSubmenu">
                                            <ul class="side-nav-fourth-level">
                                                <li>
                                                    <a href="{{ route('ebay-over-uti-acos-pink') }}">EBAY > ACOS
                                                        PINK</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('ebay-over-uti-acos-green') }}">EBAY > ACOS
                                                        GREEN</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('ebay-over-uti-acos-red') }}">EBAY > ACOS
                                                        RED</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('ebay-under-uti-acos-pink') }}">EBAY < ACOS
                                                            PINK</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('ebay-under-uti-acos-green') }}">EBAY < ACOS
                                                            GREEN</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('ebay-under-uti-acos-red') }}">EBAY < ACOS
                                                            RED</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li> --}}
                                    {{-- <li>
                                        <a href="{{ route('ebay.pink.dil.ads') }}">Ebay Pink Dil Ads</a>
                                    </li> --}}
                                    <li>
                                        <a href="{{ route('ebay.campaign.ads') }}">eBay Campaign Ads (Raw)</a>
                                    </li>
                                    {{-- <li>
                                        <a href="{{ route('ebay.keywords.ads') }}">Ebay Keywords Ads</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('ebay.running.ads') }}">Ebay Running Ads</a>
                                    </li> --}}
                                    {{-- <li>
                                        <a href="{{ route('ebay-over-uti') }}">Ebay OVER UTIL.</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('ebay-under-utilize') }}">Ebay UNDER UTIL.</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('ebay-correctly-utilize') }}">Ebay CORRECTLY UTIL.</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('ebay.keywords.ads.less-than-twenty') }}">Ebay Ads <
                                                $30</a>
                                    </li> --}}
                                    {{-- <li>
                                        <a href="{{ route('ebay-make-new-campaign-kw') }}">Ebay MAKE CAMP. KW</a>
                                    </li> --}}
                                </ul>
                            </div>
                        </li>



                        {{-- Shopify --}}
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarshopifyb2c" aria-expanded="false"
                                aria-controls="sidebarshopifyb2c">
                                <span> Shopify B2C </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarshopifyb2c">
                                <ul class="side-nav-third-level">

                                    <li>
                                        <a href="{{ route('listing.shopifyb2c') }}">Listing Shopify B2C</a>
                                    </li>

                                    <li>
                                        <a href="{{ route('shopify.b2c.listing.variation.verify') }}">Shopify B2C Listing Variation Verify</a>
                                    </li>

                                    <li>
                                        <a href="{{ url('/shopify-b2c-pricing') }}">
                                            Shopify B2C - Analytics</a>
                                    </li>


                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarshopifyb2b" aria-expanded="false"
                                aria-controls="sidebarshopifyb2b">
                                <span> Shopify B2B </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarshopifyb2b">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ url('/shopify-b2b-pricing') }}">
                                            Business Analytics</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('shopify.b2b.listing.variation.verify') }}">Shopify B2B Listing Variation Verify</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarmacy" aria-expanded="false"
                                aria-controls="sidebarmacy">
                                <span> Macy's </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarmacy">
                                <ul class="side-nav-third-level">

                                    <li>
                                        <a href="{{ route('listing.macys') }}">Listing Macy's</a>
                                    </li>

                                    <li>
                                        <a href="{{ route('macys.listing.variation.verify') }}">Macys Listing Variation Verify</a>
                                    </li>

                                    <li>
                                        <a href="{{ url('/macys-pricing') }}">Macys - Analytics</a>
                                    </li>



                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarDepop" aria-expanded="false"
                                aria-controls="sidebarDepop">
                                <span> Depop </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarDepop">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ route('depop.pricing') }}">Depop - Analytics</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('depop.sheet') }}">Depop Sales Data</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarVinted" aria-expanded="false"
                                aria-controls="sidebarVinted">
                                <span> Vinted </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarVinted">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ route('vinted.pricing') }}">Vinted - Analytics</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('vinted.sheet') }}">Vinted Sales Data</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarPurchasingPower" aria-expanded="false"
                                aria-controls="sidebarPurchasingPower">
                                <span> Purchasing Power </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarPurchasingPower">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ url('/purchasing-power-pricing') }}">Purchasing Power - Analytics</a>
                                    </li>
                                    <li>
                                        <a href="{{ url('/purchasing-power-sales') }}">Purchasing Power Sales</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('purchasing.power.listing.variation.verify') }}">Purchasing Power Listing Variation Verify</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarwayfair" aria-expanded="false"
                                aria-controls="sidebarwayfair">
                                <span> Wayfair </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarwayfair">
                                <ul class="side-nav-third-level">
                                   
                                    <li>
                                        <a href="{{ route('wayfair.pricing.view') }}">Wayfair - Analytics</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('listing.wayfair') }}">Listing Wayfair</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('wayfair.listing.variation.verify') }}">Wayfair Listing Variation Verify</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarReverb" aria-expanded="false"
                                aria-controls="sidebarReverb">
                                <span> Reverb </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarReverb">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ url('reverb-pricing') }}">Reverb - Analytics</a>
                                    </li>
                                    <li>
                                        <a href="{{ url('reverb-sales') }}">Reverb Sales Data</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('listing.reverb') }}">Listing Reverb</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarTopDawg" aria-expanded="false"
                                aria-controls="sidebarTopDawg">
                                <span> TopDawg </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarTopDawg">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ route('topdawg.pricing') }}">TopDawg - Analytics</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('topdawg.sales.dashboard') }}">TopDawg Sales Data</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarTemu" aria-expanded="false"
                                aria-controls="sidebarTemu">
                                <span> Temu </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarTemu">
                                <ul class="side-nav-third-level">




                                    <li>
                                        <a href="{{ route('listing.temu') }}">Listing Temu</a>
                                    </li>
                                    <li>
                                        <a href="{{ url('temu-decrease') }}">Temu - Analytics</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('temu.ads') }}">Temu Ads (API)</a>
                                    </li>


                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarTemu2Analytics" aria-expanded="false"
                                aria-controls="sidebarTemu2Analytics">
                                <span> Temu 2 </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarTemu2Analytics">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ url('temu2-decrease') }}">Temu 2 - Analytics</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('temu2.ads') }}">Temu 2 Ads</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('temu2.variation.verify') }}">Temu 2 Ads Variation Verification</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('temu2.listing.variation.verify') }}">Temu 2 Listing Variation Verify</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarThirdLevel1" aria-expanded="false"
                                aria-controls="sidebarThirdLevel">
                                <span> Doba </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarThirdLevel1">
                                <ul class="side-nav-third-level">

                                    <li>
                                        <a href="{{ url('doba-tabulator') }}">Doba - Analytics</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('doba.withoutship') }}">Doba without ship</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('listing.doba') }}">Listing Doba</a>
                                    </li>





                                </ul>
                            </div>
                        </li>

                        <!-- Ebay 2 -->
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarEbay2" aria-expanded="false"
                                aria-controls="sidebarEbay2">
                                <span> Ebay 2 </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarEbay2">
                                <ul class="side-nav-third-level">
                                    <li> <a href="{{ url('ebay2-tabulator-view') }}">Ebay 2 - Analytics</a>
                                    </li>
                                    <li> <a href="{{ url('ebay2op-tabulator-view') }}">Ebay 2 Open Box </a>
                                    </li>

                                    <li>
                                        <a href="{{ route('listing.ebayTwo') }}">Listing Ebay 2</a>
                                    </li>

                                    <li>
                                        <a href="{{ route('ebay2.listing.variation.verify') }}">Ebay 2 Listing Variation Verify</a>
                                    </li>

                                    <li>
                                        <a href="{{ route('ebay2.campaign.ads') }}">eBay 2 Campaign Ads (Raw)</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <!-- Ebay 3 -->
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarEbay3" aria-expanded="false"
                                aria-controls="sidebarEbay3">
                                <span> Ebay 3 </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarEbay3">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ url('ebay3-tabulator-view') }}">Ebay 3 - Analytics</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('listing.ebayThree') }}">Listing Ebay 3</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('ebay3.listing.variation.verify') }}">Ebay 3 Listing Variation Verify</a>
                                    </li>


                                    {{-- <li>
                                        <a data-bs-toggle="collapse" href="#ebay3AcosSubmenu"
                                            aria-expanded="false" aria-controls="ebay3AcosSubmenu">
                                            <span>Ebay 3 ACOS Control</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="ebay3AcosSubmenu">
                                            <ul class="side-nav-fourth-level">
                                                <li>
                                                    <a href="{{ route('ebay3-over-uti-acos-pink') }}">Over ACOS
                                                        PINK</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('ebay3-over-uti-acos-green') }}">Over ACOS
                                                        GREEN</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('ebay3-over-uti-acos-red') }}">Over ACOS
                                                        RED</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('ebay3-under-uti-acos-pink') }}">Under ACOS
                                                        PINK</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('ebay3-under-uti-acos-green') }}">Under ACOS
                                                        GREEN</a>
                                                </li>
                                                <li>
                                                    <a href="{{ route('ebay3-under-uti-acos-red') }}">Under ACOS
                                                        RED</a>
                                                </li>
                                            </ul>
                                        </div>
                                    </li> --}}
                                    {{-- <li>
                                        <a href="{{ route('ebay3.pink.dil.ads') }}">Ebay 3 Pink Dil Ads</a>
                                    </li> --}}
                                    {{-- <li>
                                        <a href="{{ route('ebay3.over.utilized') }}">Ebay 3 OVER UTIL.</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('ebay3.under.utilized') }}">Ebay 3 UNDER UTIL.</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('ebay3.correctly.utilized') }}">Ebay 3 CORRECTLY UTIL.</a>
                                    </li> --}}
                                    <li>
                                        <a href="{{ route('ebay3.campaign.ads') }}">eBay 3 Campaign Ads (Raw)</a>
                                    </li>
                                    {{-- <li>
                                        <a href="{{ route('ebay3.keywords.ads') }}">Ebay 3 Keywords Ads</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('ebay3.keywords.ads.less-than-thirty') }}">Ebay 3 Ads <
                                                $30</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('ebay3.running.ads') }}">Ebay 3 Running Ads</a>
                                    </li> --}}
                                </ul>
                            </div>
                        </li>


                        <!-- Walmart -->
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarWalmart" aria-expanded="false"
                                aria-controls="sidebarWalmart">
                                <span> Walmart </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarWalmart">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ route('walmart.sheet.upload') }}">Walmart - Analytics</a>
                                    </li>

                        <li>
                            <a href="{{ route('listing.walmart') }}">Listing Walmart</a>
                        </li>
                        {{-- <li>
                                        <a href="{{ url('walmart-tabulator-view') }}">Walmart Pricing - CVR</a>
                                    </li> --}}

                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarGoogleShopping" aria-expanded="false"
                    aria-controls="sidebarGoogleShopping">
                    <span> Google Ads </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarGoogleShopping">
                    <ul class="side-nav-third-level">
                        
                        <li>
                            <a href="{{ route('google.shopping.campaigns') }}">Google Shopping</a>
                        </li>
                        <li>
                            <a href="{{ route('google.shopping.ads.missing') }}" class="gs-ads-missing-nav">
                                Missing Google Shopping Ads
                                @php $gsAdsMissingCount = \App\Http\Controllers\Campaigns\GoogleShoppingAdsMissingController::missingTotalCount(); @endphp
                                @if($gsAdsMissingCount > 0)
                                    <span class="badge bg-danger rounded-pill">{{ $gsAdsMissingCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('google.shopping.audit') }}">Google Shopping Audit</a>
                        </li>
                        <li>
                            <a href="{{ route('google.shopping.campaigns.revised') }}">Revised (Neg KW)</a>
                        </li>
                        <li>
                            <a href="{{ route('google.serp.campaigns') }}">Google SERP Campaigns</a>
                        </li>
                        <li>
                            <a href="{{ route('google.serp.ads.missing') }}" class="gs-serp-ads-missing-nav">
                                Missing Google SERP Ads
                                @php $gsSerpAdsMissingCount = \App\Http\Controllers\Campaigns\GoogleSerpAdsMissingController::missingTotalCount(); @endphp
                                @if($gsSerpAdsMissingCount > 0)
                                    <span class="badge bg-danger rounded-pill">{{ $gsSerpAdsMissingCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('google.serp.audit') }}">Google SERP Audit</a>
                        </li>
                        <li>
                            <a href="{{ Route::has('google.youtube.ads.campaigns') ? route('google.youtube.ads.campaigns') : url('/google/shopping/youtube-ads') }}">Youtube ads</a>
                        </li>
                        <li>
                            <a href="{{ route('google.youtube.ads.missing') }}">YouTube Missing Ads</a>
                        </li>
                        <li>
                            <a href="{{ route('tiktok.ads.missing') }}">TikTok Missing Ads</a>
                        </li>
                        <li>
                            <a href="{{ route('google.youtube.ads.audit') }}">Youtube Ads Audit</a>
                        </li>

                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarFacebook" aria-expanded="false"
                                aria-controls="sidebarFacebook">
                                <span> Facebook </span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarFacebook">
                                <ul class="side-nav-fourth-level">
                                    <li>
                                        <a href="{{ route('facebook.ads.master') }}">FB Video Ads</a>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Aliexpress -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarAliexpress" aria-expanded="false"
                    aria-controls="sidebarAliexpress">
                    <span>Aliexpress</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarAliexpress">
                    <ul class="side-nav-third-level">
                        <!-- <li><a href="#">Aliexpress Analytics</a></li> -->

                        <li><a href="{{ route('listing.aliexpress') }}">Listing Aliexpress</a>
                        </li>

                        <li><a href="{{ route('aliexpress.pricing.view') }}">Aliexpress - Analytics</a>
                        </li>
                        <li><a href="{{ route('aliexpress.listing.variation.verify') }}">AliExpress Listing Variation Verify</a></li>
                        <li><a href="{{ route('aliexpress.lmp') }}">Aliexpress LMP</a></li>
                    </ul>
                </div>
            </li>

            <!-- Faire -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarFaire" aria-expanded="false"
                    aria-controls="sidebarFaire">
                    <span>Faire</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarFaire">
                    <ul class="side-nav-third-level">
                        <li><a href="{{ route('faire.pricing.view') }}">Faire - Analytics</a></li>
                        <li><a href="{{ route('faire.listing.variation.verify') }}">Faire Listing Variation Verify</a></li>
                    </ul>
                </div>
            </li>
            <!-- Tiktok Shop -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarTiktokShop" aria-expanded="false"
                    aria-controls="sidebarTiktokShop">
                    <span>Tiktok Shop</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarTiktokShop">
                    <ul class="side-nav-third-level">
                        <li><a href="{{ route('listing.tiktokshop') }}">Listing Tiktok Shop</a>
                        </li>
                        <li><a href="{{ route('listing.tiktokshop2') }}">Listing TikTok 2</a>
                        </li>
                        <li><a href="{{ route('tiktok.listing.variation.verify') }}">TikTok 1 Listing Variation Verify</a>
                        </li>
                        {{-- <li><a href="{{ route('tiktokshop.ads') }}">Tiktok Shop Ads</a>
                                    </li> --}}
                        <li><a href="{{ route('tiktok.pricing') }}">TikTok 1 Shop - Analytics</a>
                        </li>
                        <li><a href="{{ route('tiktok2.listing.variation.verify') }}">TikTok 2 Listing Variation Verify</a>
                        </li>
                        <li><a href="{{ route('tiktok2.pricing') }}">TikTok 2 Shop - Analytics</a>
                        </li>
                    </ul>
                </div>
            </li>
            <!-- Mercari w Ship -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarMercariWShip" aria-expanded="false"
                    aria-controls="sidebarMercariWShip">
                    <span>Mercari w Ship</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarMercariWShip">
                    <ul class="side-nav-third-level">
                        <li><a href="{{ route('mercari.wship.tabulator.view') }}">Mercari w Ship - Analytics</a></li>
                    </ul>
                </div>
            </li>
            <!-- FB Marketplace -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarFBMarketplace" aria-expanded="false"
                    aria-controls="sidebarFBMarketplace">
                    <span>FB Marketplace</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarFBMarketplace">
                    <ul class="side-nav-third-level">
                        <li><a href="{{ route('fb.marketplace.tabulator.view') }}">Fb Marketplace - Analytics</a>
                        </li>

                        <li><a href="{{ route('listing.fbmarketplace') }}">Listing FB
                                Marketplace</a></li>
                    </ul>
                </div>
            </li>
            <!-- PLS -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarPLS" aria-expanded="false"
                    aria-controls="sidebarPLS">
                    <span>PLS</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarPLS">
                    <ul class="side-nav-third-level">
                        <li><a href="{{ route('pls.pricing') }}">PLS - Analytics</a></li>

                        <li><a href="{{ route('pls.sales') }}">PLS Sales (30 Days)</a></li>

                        <li><a href="{{ route('listing.pls') }}">Listing PLS</a></li>
                        <li><a href="{{ route('pls.listing.variation.verify') }}">PLS Listing Variation Verify</a></li>
                    </ul>
                </div>
            </li>


            <!-- Mercari w/o Ship -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarMercariWoShip" aria-expanded="false"
                    aria-controls="sidebarMercariWoShip">
                    <span>Mercari w/o Ship</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarMercariWoShip">
                    <ul class="side-nav-third-level">
                        <li><a href="{{ route('mercari.woship.tabulator.view') }}">Mercari w/o Ship - Analytics</a></li>

                        <li><a href="{{ route('listing.mercariwoship') }}">Listing Mercari w/o
                                Ship</a></li>
                    </ul>
                </div>
            </li>


            <!-- Tiendamia -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarTiendamia" aria-expanded="false"
                    aria-controls="sidebarTiendamia">
                    <span>Tiendamia</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarTiendamia">
                    <ul class="side-nav-third-level">
                        <li><a href="{{ route('listing.tiendamia') }}">Listing Tiendamia</a>
                        </li>
                        <li><a href="{{ route('tiendamia.pricing') }}">Tiendamia - Analytics</a></li>
                    </ul>
                </div>
            </li>
            <!-- Shein -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarShein" aria-expanded="false"
                    aria-controls="sidebarShein">
                    <span>Shein</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarShein">
                    <ul class="side-nav-third-level">
                        <li><a href="{{ url('shein-tabulator') }}">Shein Daily Data</a></li>
                        <li><a href="{{ route('listing.shein') }}">Listing Shein</a></li>
                        <li><a href="{{ route('shein.pricing.view') }}">Shein Pricing</a></li>
                        <li><a href="{{ route('shein.listing.variation.verify') }}">Shein Listing Variation Verify</a></li>
                    </ul>
                </div>
            </li>


            <!-- FB Shop -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarFBShop" aria-expanded="false"
                    aria-controls="sidebarFBShop">
                    <span>FB Shop</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarFBShop">
                    <ul class="side-nav-third-level">
                        <li>
                            <a href="{{ route('zero.fbshop') }}">FB Shop 0 view</a>
                        </li>

                        <li><a href="{{ route('listing.fbshop') }}">Listing FB Shop</a></li>
                    </ul>
                </div>
            </li>
            <!-- Instagram Shop -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarInstagramShop" aria-expanded="false"
                    aria-controls="sidebarInstagramShop">
                    <span>Instagram Shop</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarInstagramShop">
                    <ul class="side-nav-third-level">
                        <li>
                            <a href="{{ route('zero.instagramshop') }}">Instagram Shop 0
                                view</a>
                        </li>

                        <li><a href="{{ route('listing.instagramshop') }}">Listing Instagram
                                Shop</a></li>
                    </ul>
                </div>
            </li>

            <!-- Bestbuy USA -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarBestbuyUSA" aria-expanded="false"
                    aria-controls="sidebarBestbuyUSA">
                    <span>Bestbuy USA</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarBestbuyUSA">
                    <ul class="side-nav-third-level">
                        <li><a href="{{ route('bestbuy.pricing') }}">Best Buy Pricing</a></li>
                        <li><a href="{{ route('bestbuy.listing.variation.verify') }}">Bestbuy Listing Variation Verify</a></li>
                        <li><a href="{{ route('zero.bestbuyusa') }}">Bestbuy USA 0 view</a></li>

                        <li><a href="{{ route('listing.bestbuyusa') }}">Listing Bestbuy USA</a>
                        </li>


                    </ul>
                </div>
            </li>

            <!-- Newegg -->
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarNewegg" aria-expanded="false"
                    aria-controls="sidebarNewegg">
                    <span>Newegg</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarNewegg">
                    <ul class="side-nav-third-level">
                        <li><a href="{{ route('newegg.pricing.view') }}">Newegg Pricing</a></li>
                        <li><a href="{{ route('newegg.listing.variation.verify') }}">Newegg Listing Variation Verify</a></li>
                    </ul>
                </div>
            </li>

        </ul>
    </div>
    </li>

            <li class="side-nav-item">
                <a href="{{ url('ai-title-manager') }}" class="side-nav-link">
                    <i class="ri-magic-line"></i>
                    <span>Marketplace AI Title </span>
                </a>
            </li>

            {{-- Marketplace Sync (Reverb, Amazon, eBay, Walmart) --}}
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarMarketplaceSync" aria-expanded="false" aria-controls="sidebarMarketplaceSync" class="side-nav-link">
                    <i class="ri-store-2-line"></i>
                    <span>Marketplace Sync</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarMarketplaceSync">
                    <ul class="side-nav-second-level">
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarMarketplaceReverb" aria-expanded="false" aria-controls="sidebarMarketplaceReverb">
                                <span>Reverb</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarMarketplaceReverb">
                                <ul class="side-nav-third-level">
                                    <li><a href="{{ route('marketplace.products', 'reverb') }}">Products</a></li>
                                    <li><a href="{{ route('marketplace.orders', 'reverb') }}">Orders</a></li>
                                    <li><a href="{{ route('marketplace.settings', 'reverb') }}">Settings</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarMarketplaceAmazon" aria-expanded="false" aria-controls="sidebarMarketplaceAmazon">
                                <span>Amazon</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarMarketplaceAmazon">
                                <ul class="side-nav-third-level">
                                    <li><a href="{{ route('marketplace.products', 'amazon') }}">Products</a></li>
                                    <li><a href="{{ route('marketplace.orders', 'amazon') }}">Orders</a></li>
                                    <li><a href="{{ route('marketplace.settings', 'amazon') }}">Settings</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarMarketplaceEbay" aria-expanded="false" aria-controls="sidebarMarketplaceEbay">
                                <span>eBay</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarMarketplaceEbay">
                                <ul class="side-nav-third-level">
                                    <li><a href="{{ route('marketplace.products', 'ebay') }}">Products</a></li>
                                    <li><a href="{{ route('marketplace.orders', 'ebay') }}">Orders</a></li>
                                    <li><a href="{{ route('marketplace.settings', 'ebay') }}">Settings</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarMarketplaceWalmart" aria-expanded="false" aria-controls="sidebarMarketplaceWalmart">
                                <span>Walmart</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarMarketplaceWalmart">
                                <ul class="side-nav-third-level">
                                    <li><a href="{{ route('marketplace.products', 'walmart') }}">Products</a></li>
                                    <li><a href="{{ route('marketplace.orders', 'walmart') }}">Orders</a></li>
                                    <li><a href="{{ route('marketplace.settings', 'walmart') }}">Settings</a></li>
                                </ul>
                            </div>
                        </li>
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarMarketplaceTopDawg" aria-expanded="false" aria-controls="sidebarMarketplaceTopDawg">
                                <span>TopDawg</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="sidebarMarketplaceTopDawg">
                                <ul class="side-nav-third-level">
                                    <li><a href="{{ route('topdawg.pricing') }}">Pricing / Analytics</a></li>
                                    <li><a href="{{ route('topdawg.sales.dashboard') }}">Top Dawg Sales Data</a></li>
                                    <li><a href="{{ route('marketplace.products', 'topdawg') }}">Products</a></li>
                                    <li><a href="{{ route('marketplace.orders', 'topdawg') }}">Orders</a></li>
                                    <li><a href="{{ route('marketplace.settings', 'topdawg') }}">Settings</a></li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a href="{{ url('/pricing-master-cvr') }}" class="side-nav-link">
                    <i class="ri-file-line"></i>
                    <span>Master Analytics</span>
                </a>
            </li>

            <li class="side-nav-item">
                <a href="{{ url('/sold-master') }}" class="side-nav-link">
                    <i class="ri-shopping-cart-line"></i>
                    <span>Sales by Value</span>
                </a>
            </li>




            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarPagess" aria-expanded="false"
                    aria-controls="sidebarPagess" class="side-nav-link">
                    <i class="ri-pages-line"></i>
                    <span>Product Masters</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="sidebarPagess">
                    <ul class="side-nav-second-level">

                        <li>
                            <a href="{{ route('product.master') }}">CP Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('category.master') }}">Category Master</a>
                        </li>
                        <li>
                            <a href="{{ route('id.master') }}">ID Master</a>
                        </li>
                        <li>
                            <a href="{{ route('dim.wt.master') }}">
                                Dim Wt Items
                                @php $dimWtNotVerifiedCount = \App\Http\Controllers\PurchaseMaster\CategoryController::notVerifiedCountForSidebar(); @endphp
                                @if($dimWtNotVerifiedCount > 0)
                                    <span class="badge bg-danger rounded-pill">{{ $dimWtNotVerifiedCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('dim.wt.master.ctn') }}">Dim Wt CTN</a>
                        </li>
                        <li>
                            <a href="{{ route('qc.upgrade') }}">QC Upgrade</a>
                        </li>
                        <li>
                            <a href="{{ route('qc.masters') }}">QC Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('shipping.master') }}">Shipping Master</a>
                        </li>
                        <li>
                            <a href="{{ route('general.specific.master') }}">General Specific Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('reverb.listing.master') }}">Reverb Listing Master</a>
                        </li>
                        <li>
                            <a href="{{ route('compliance.master') }}">Compliance Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('packing.instructions.master') }}">Packing Inner Design</a>
                        </li>
                        <li>
                            <a href="{{ route('extra.features.master') }}">Extra Features Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('a.plus.images.master') }}">Listing Audit</a>
                        </li>
                        <li>
                            <a href="{{ route('hero.images.master') }}">Hero Images Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('trust.images.master') }}">Trust Images Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('ugc.images.master') }}">UGC Images Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('infographics.images.master') }}">Infographics Images Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('benefits.images.master') }}">Benefits Images Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('additional.images.master') }}">Additional Images Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('usage.images.master') }}">Usage Images Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('keywords.master') }}">Keywords Master</a>
                        </li>
                        
                        <li>
                            <a href="{{ route('package.includes.master') }}">Package Includes Master</a>
                        </li>
                        <li>
                            <a href="{{ route('qa.master') }}">Q&A Master</a>
                        </li>
                        <li>
                            <a href="{{ route('competitors.master') }}">Competitors Master</a>
                        </li>
                        <li>
                            <a href="{{ route('target.keywords.master') }}">Target Keywords Master</a>
                        </li>
                        <li>
                            <a href="{{ route('target.products.master') }}">Target Products Master</a>
                        </li>

                        <li>
                            <a href="{{ route('tag.lines.master') }}">Tag lines Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('group.master') }}">Group Masters</a>
                        </li>
                        <li>
                            <a href="{{ route('seo.keywords.master') }}">SEO Keywords master</a>
                        </li>
                        <li>
                            <a href="{{ route('title.master') }}">Title Master</a>
                        </li>
                        <li>
                            <a href="{{ route('sku-images.index') }}">SKU Image Manager</a>
                        </li>
                        <li>
                            <a href="{{ route('sku-images.push-status') }}">SKU Image Push Status</a>
                        </li>
                        <li>
                            <a href="{{ route('image.master') }}">Image Master</a>
                        </li>
                        <li>
                            <a href="{{ route('video.master') }}">Video Master</a>
                        </li>
                        <li>
                            <a href="{{ route('bullet.points') }}">Bullet Points Master</a>
                        </li>
                        <li>
                            <a href="{{ route('videos.master') }}">Videos</a>
                        </li>
                        <li>
                            <a href="{{ route('videos.for.ads') }}">Videos for Ads</a>
                        </li>
                        <li>
                            <a href="{{ route('video.for.ds') }}">FB Video Ads</a>
                        </li>
                        <li>
                            <a href="{{ route('product.description') }}">Description Master</a>
                        </li>
                        <li>
                            <a href="{{ route('product.description2') }}">Description Master 2.0</a>
                        </li>
                        <li>
                            <a href="{{ route('features') }}">Features</a>
                        </li>
                        <li>
                            <a href="{{ route('images') }}">Images</a>
                        </li>

                        <li>
                            <a href="{{ url('ads-pricing-master') }}">Advertisment Master</a>
                        </li>

                        <li>
                            <a href="{{ route('costprice.analysis') }}">Cost Price Analysis</a>
                        </li>

                        <li>
                            <a href="{{ route('movement.analysis') }}">Movement Analysis</a>
                        </li>
                        <li>
                            <a href="{{ route('tobedc.list') }}">2BDC</a>
                        </li>

                        <li>
                            <a href="/pages/transit-analysis">Transit Analysis</a>                        </li>
                        <li>
                            <a href="{{ route('pRoi.analysis') }}">Profit & ROI Analysis</a>
                        </li>
                        <li>
                            <a href="{{ route('return.analysis') }}">Returns Analysis</a>
                        </li>
                        <li>
                            <a href="{{ route('stock.analysis') }}">Stock Verification</a>
                        </li>
                        <li>
                            <a href="{{ route('shortfall.analysis') }}">Shortfall Analysis</a>
                        </li>
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#sidebarAdvMaster" aria-expanded="false"
                                aria-controls="sidebarAdvMaster" class="side-nav-link collapsed">
                                <span class="menu-arrow"></span>
                                <span>Advertisement Master</span>
                            </a>
                            <div class="collapse" id="sidebarAdvMaster">
                                <ul class="side-nav-second-level">
                                    <!-- Product Wise Section -->
                                    <li class="side-nav-item">
                                        <a data-bs-toggle="collapse" href="#productWise" aria-expanded="false"
                                            aria-controls="productWise" class="collapsed">
                                            <span>Product Wise</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="productWise">
                                            <ul class="side-nav-third-level">
                                                <!-- PPC Section -->
                                                <li class="side-nav-item">
                                                    <a data-bs-toggle="collapse" href="#ppcProduct"
                                                        aria-expanded="false" aria-controls="ppcProduct"
                                                        class="collapsed">
                                                        <span>PPC</span>
                                                        <span class="menu-arrow"></span>
                                                    </a>
                                                    <div class="collapse" id="ppcProduct">
                                                        <ul class="side-nav-fourth-level">
                                                            <li>
                                                                <a data-bs-toggle="collapse" href="#ppcProduct1"
                                                                    aria-expanded="false" aria-controls="ppcProduct1"
                                                                    class="collapsed">
                                                                    <span>KW Advt</span>
                                                                    <span class="menu-arrow"></span>
                                                                </a>
                                                                <div class="collapse" id="ppcProduct1">
                                                                    <ul class="side-nav-fifth-level">
                                                                        <li><a
                                                                                href="{{ route('advertisment.kw.amazon') }}">Amazon</a>
                                                                        </li>
                                                                        <li><a
                                                                                href="{{ route('advertisment.kw.eBay') }}">eBay</a>
                                                                        </li>
                                                                        <li><a
                                                                                href="{{ route('advertisment.kw.walmart') }}">Walmart</a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </li>
                                                            <li>
                                                                <a data-bs-toggle="collapse" href="#ppcProduct2"
                                                                    aria-expanded="false" aria-controls="ppcProduct2"
                                                                    class="collapsed">
                                                                    <span>Prod Target Advt</span>
                                                                    <span class="menu-arrow"></span>
                                                                </a>
                                                                <div class="collapse" id="ppcProduct2">
                                                                    <ul class="side-nav-fifth-level">
                                                                        <li><a
                                                                                href="{{ route('advertisment.prod.target.Amazon') }}">Amazon</a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </li>
                                                            <li>
                                                                <a data-bs-toggle="collapse" href="#ppcProduct3"
                                                                    aria-expanded="false" aria-controls="ppcProduct3"
                                                                    class="collapsed">
                                                                    <span>Headline Advt</span>
                                                                    <span class="menu-arrow"></span>
                                                                </a>
                                                                <div class="collapse" id="ppcProduct3">
                                                                    <ul class="side-nav-fifth-level">
                                                                        <li><a
                                                                                href="{{ route('advertisment.headline.Amazon') }}">Amazon</a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </li>
                                                            <li>
                                                                <a data-bs-toggle="collapse" href="#ppcProduct4"
                                                                    aria-expanded="false" aria-controls="ppcProduct4"
                                                                    class="collapsed">
                                                                    <span>Promoted Advt</span>
                                                                    <span class="menu-arrow"></span>
                                                                </a>
                                                                <div class="collapse" id="ppcProduct4">
                                                                    <ul class="side-nav-fifth-level">
                                                                        <li><a
                                                                                href="{{ route('advertisment.promoted.eBay') }}">eBay</a>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </li>

                                            </ul>
                                        </div>
                                    </li>

                                    <!-- Group Wise Section -->
                                    <li class="side-nav-item">
                                        <a data-bs-toggle="collapse" href="#groupWise" aria-expanded="false"
                                            aria-controls="groupWise" class="collapsed">
                                            <span>Group Wise</span>
                                            <span class="menu-arrow"></span>
                                        </a>
                                        <div class="collapse" id="groupWise">
                                            <ul class="side-nav-third-level">
                                                <!-- PPC Section -->
                                                <li class="side-nav-item">
                                                    <a data-bs-toggle="collapse" href="#ppcGroup"
                                                        aria-expanded="false" aria-controls="ppcGroup"
                                                        class="collapsed">
                                                        <span>PPC</span>
                                                        <span class="menu-arrow"></span>
                                                    </a>
                                                    <div class="collapse" id="ppcGroup">
                                                        <ul class="side-nav-fourth-level">
                                                            <li>
                                                                <a data-bs-toggle="collapse" href="#ppcGroup1"
                                                                    aria-expanded="false" aria-controls="ppcGroup1"
                                                                    class="collapsed">
                                                                    <span>Serp Advt</span>
                                                                    <span class="menu-arrow"></span>
                                                                </a>
                                                                <div class="collapse" id="ppcGroup1">
                                                                    <ul class="side-nav-fifth-level">
                                                                        <li><a href="{{ route('google.serp.campaigns') }}">Google SERP</a></li>
                                                                    </ul>
                                                                </div>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                </li>

                                            </ul>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>

                </div>
            </li>


            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#suppliers" aria-expanded="false" aria-controls="suppliers"
                    class="side-nav-link">
                    <i class="ri-group-line"></i>
                    <span>Purchase Master</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="suppliers">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('category.list') }}">Categories</a>
                        </li>
                        <li>
                            <a href="{{ route('supplier.list') }}">Suppliers</a>
                        </li>
                        <li>
                            <a href="{{ route('rfq-form.index') }}">RFQ Form</a>
                        </li>
                        <li>
                            <a href="{{ route('claim.reimbursement') }}">Claims & Reimbursements</a>
                        </li>
                        <li>
                            <a href="{{ route('forecast.analysis') }}">Forecast Analysis</a>
                        </li>
                        <li>
                            <a href="{{ route('to.order.analysis') }}">To Order Analysis</a>
                        </li>
                        <li>
                            <a href="{{ route('list-all-purchase-orders') }}">Purchase Contract</a>
                        </li>
                        <li>
                            <a href="{{ route('purchase.index') }}">Purchase</a>
                        </li>
                        <li class="side-nav-item">
                            <a data-bs-toggle="collapse" href="#ledger" aria-expanded="false" aria-controls="ledger">
                                <span>Ledger</span>
                                <span class="menu-arrow"></span>
                            </a>
                            <div class="collapse" id="ledger">
                                <ul class="side-nav-third-level">
                                    <li>
                                        <a href="{{ route('ledger.advance.payments') }}">Advance & Payments</a>
                                    </li>
                                    <li>
                                        <a href="{{ route('supplier.ledger') }}">Supplier Ledger</a>
                                    </li>
                                </ul>
                            </div>
                        </li>

                        <li>
                            <a href="{{ route('mfrg.in.progress') }}">MIP</a>
                        </li>
                        <li>
                            <a href="{{ route('comparison.index') }}">Purchase Comparison</a>
                        </li>
                        <li>
                            <a href="{{ url('/purchase-master/sku-link-lmp') }}">SKU Link LMP</a>
                        </li>
                        <li>
                            <a href="{{ route('ads.link.index') }}">Ads Link</a>
                        </li>
                        <li>
                            <a href="{{ route('ready.to.ship') }}">Ready To Ship</a>
                        </li>
                        <li>
                            <a href="{{ route('transit') }}">🚢 Transit</a>
                        </li>
                        <li>
                            <a href="{{ route('china.load') }}">China Load</a>
                        </li>
                        <li>
                            <a href="{{ route('upcoming.container') }}">Coming Container</a>
                        </li>
                        <li>
                            <a href="{{ route('transit.container.details') }}">Transit Container INV</a>
                        </li>
                        <li>
                            <a href="{{ route('arrived.container') }}">Arrived Container</a>
                        </li>
                        <li>
                            <a href="{{ route('container.summary') }}">Container Summary</a>
                        </li>
                        {{-- <li>
                            <a href="{{ route('transit.container.changes') }}">Transit Container Changes</a>
                        </li>
                        <li>
                            <a href="{{ route('transit.container.new') }}">Transit Container New</a>
                        </li> --}}
                        <li>
                            <a href="{{ route('container.planning') }}">Container Planning</a>
                        </li>
                        <li>
                            <a href="{{ route('on.sea.transit') }}">On Sea Transit</a>
                        </li>
                        <li>
                            <a href="{{ route('on.road.transit') }}">On Road Transit</a>
                        </li>
                        <li>
                            <a href="{{ route('quality.enhance') }}">Quality Enhance</a>
                        </li>
                        <li>
                            <a href="{{ route('inventory.index') }}">Inventory Warehouse</a>
                        </li>
                        <li>
                            <a href="{{ route('scope-of-improvement.index') }}">Scope of Improvement</a>
                        </li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a href="{{ route('resources.index') }}" class="side-nav-link {{ request()->routeIs('resources.*') ? 'active' : '' }}">
                    <i class="ri-folder-shared-line"></i>
                    <span> Resources </span>
                </a>
            </li>

            <li class="side-nav-item">
                <a href="{{ route('help-desk-faqs.index') }}" class="side-nav-link {{ request()->routeIs('help-desk-faqs.*') ? 'active' : '' }}">
                    <i class="ri-question-answer-line"></i>
                    <span> Help Desk FAQs, FFP </span>
                </a>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarResourcesMaster" aria-expanded="{{ request()->routeIs('resources-master.*') ? 'true' : 'false' }}" aria-controls="sidebarResourcesMaster"
                    class="side-nav-link {{ request()->routeIs('resources-master.*') ? 'active' : '' }}">
                    <i class="ri-folder-shield-2-line"></i>
                    <span> Resources Master </span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse {{ request()->routeIs('resources-master.*') ? 'show' : '' }}" id="sidebarResourcesMaster">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('resources-master.section', 'rr_files') }}" class="{{ request()->is('resources-master/section/rr_files') ? 'active' : '' }}">R&amp;R Files</a>
                        </li>
                        <li>
                            <a href="{{ route('resources-master.section', 'training_resources') }}" class="{{ request()->is('resources-master/section/training_resources') ? 'active' : '' }}">Training Resources</a>
                        </li>
                        <li>
                            <a href="{{ route('resources-master.section', 'checklist_forms') }}" class="{{ request()->is('resources-master/section/checklist_forms') ? 'active' : '' }}">Checklist Forms</a>
                        </li>
                        <li>
                            <a href="{{ route('resources-master.section', 'media_gallery') }}" class="{{ request()->is('resources-master/section/media_gallery') ? 'active' : '' }}">Media Gallery</a>
                        </li>
                        <li>
                            <a href="{{ route('resources-master.section', 'links_videos') }}" class="{{ request()->is('resources-master/section/links_videos') ? 'active' : '' }}">Links / Videos</a>
                        </li>
                        <li>
                            <a href="{{ route('resources-master.dashboard') }}" class="{{ request()->routeIs('resources-master.dashboard') ? 'active' : '' }}">Overview</a>
                        </li>
                    </ul>
                </div>
            </li>

            {{-- Review Intelligence --}}
            <li class="side-nav-item">
                <a href="{{ route('reviews.index') }}" class="side-nav-link {{ request()->routeIs('reviews.*') ? 'active' : '' }}">
                    <i class="ri-star-smile-line"></i>
                    <span>Review Intelligence</span>
                    @php $openAlertCount = \App\Models\ReviewAlert::where('status','open')->count(); @endphp
                    @if($openAlertCount > 0)
                        <span class="badge bg-danger rounded-pill ms-auto">{{ $openAlertCount }}</span>
                    @endif
                </a>
            </li>

            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#salesDashboard" aria-expanded="false"
                    aria-controls="salesDashboard" class="side-nav-link">
                    <i class="ri-bar-chart-line"></i>
                    <span>Sales Data</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse" id="salesDashboard">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('ebay.daily.sales') }}">eBay Sales Data</a>
                        </li>
                        <li>
                            <a href="{{ route('ebay2.daily.sales') }}">eBay 2 Sales Data</a>
                        </li>

                        <li>
                            <a href="{{ url('/ebay3/daily-sales') }}">eBay 3 Sales Data</a>
                        </li>

                        <li>
                            <a href="{{ route('topdawg.sales.dashboard') }}">TopDawg Sales Data</a>
                        </li>

                        <li>
                            <a href="{{ url('amazon/daily-sales') }}">Amazon Sales Data</a>
                        </li>
                        <li>
                            <a href="{{ url('doba/daily-sales') }}">Doba Sales Data</a>
                        </li>

                        <li><a href="{{ url('temu-tabulator') }}">Temu Sales Data</a></li>

                        <li><a href="{{ url('temu2-tabulator') }}">Temu 2 Sales Data</a></li>

                        <li><a href="{{ url('shein-tabulator') }}">Shein Sales Data</a></li>

                        <li><a href="{{ url('mercari-with-ship') }}">Mercari With Ship Sales</a></li>

                        <li><a href="{{ url('mercari-without-ship') }}">Mercari Without Ship Sales</a></li>

                        <li><a href="{{ url('aliexpress-tabulator') }}">Aliexpress Sales Data</a></li>


                        <li><a href="{{ url('shopify-b2c/daily-sales') }}">Shopify B2C Sales</a></li>

                        <li><a href="{{ url('shopify-b2b/daily-sales') }}">Shopify B2B Sales</a></li>

                        <li><a href="{{ route('bestbuy.daily.sales') }}">Best Buy Sales Data</a></li>

                        <li><a href="{{ route('newegg.daily.sales') }}">Newegg Sales Data</a></li>

                        <li><a href="{{ route('newegg.pricing.view') }}">Newegg Pricing</a></li>

                        <li><a href="{{ route('newegg.listing.variation.verify') }}">Newegg Listing Variation Verify</a></li>

                        <li><a href="{{ route('macys.daily.sales') }}">Macy's Sales Data</a></li>

                        <li><a href="{{ route('tiendamia.daily.sales') }}">Tiendamia Sales Data</a></li>

                        <li><a href="{{ route('purchasing.power.sales') }}">Purchasing Power Sales Data</a></li>

                        <li><a href="{{ route('tiktok.daily.sales') }}">TikTok Sales Data</a></li>

                        <li><a href="{{ route('faire.tabulator.view') }}">Faire Sales Data</a></li>

                        <li><a href="{{ route('tiktok.two.daily.sales') }}">TikTok 2 Sales Data</a></li>

                        <li><a href="{{ route('depop.sheet') }}">Depop Sheet Data</a></li>

                        <li><a href="{{ route('vinted.sheet') }}">Vinted Sheet Data</a></li>

                        <li><a href="{{ route('walmart.daily.sales') }}">Walmart Sales Data</a></li>

                        <li><a href="{{ route('wayfair.daily.sales') }}">Wayfair Sales Data</a></li>

                        <li><a href="{{ route('facebook.marketplace') }}">Facebook Marketplace</a></li>
                    </ul>
                </div>
            </li>

            <li class="side-nav-item">
                <a href="{{ route('customer.care.shipping') }}" class="side-nav-link">
                    <i class="ri-truck-line"></i>
                    <span>Shipping</span>
                </a>
            </li>

            {{-- Team Management --}}
            @can('team.management.view')
            @php
                $teamMgmtActive = request()->routeIs('users.add')
                    || (request()->routeIs('payroll.*') && Gate::allows('payroll.manage'));
            @endphp
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#userManagement" aria-expanded="{{ $teamMgmtActive ? 'true' : 'false' }}" aria-controls="userManagement"
                    class="side-nav-link {{ $teamMgmtActive ? 'active' : '' }}">
                    <i class="ri-user-settings-line"></i>
                    <span>Team Management</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse {{ $teamMgmtActive ? 'show' : '' }}" id="userManagement">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('users.add') }}" class="{{ request()->routeIs('users.add') ? 'active' : '' }}">
                                <i class="ri-user-add-line me-2"></i>Users
                            </a>
                        </li>
                        @can('payroll.manage')
                        <li>
                            <a href="{{ route('payroll.index') }}" class="{{ request()->routeIs('payroll.*') ? 'active' : '' }}">
                                <i class="ri-wallet-3-line me-2"></i>Salary
                            </a>
                        </li>
                        @endcan
                    </ul>
                </div>
            </li>
            @endcan

            {{-- User --}}

            @php
                $userMenuActive = request()->routeIs('roles')
                    || request()->routeIs('users.add')
                    || request()->routeIs('permissions')
                    || request()->routeIs('permissions.view')
                    || request()->routeIs('attendance.monitor*')
                    || request()->routeIs('attendance.employee')
                    || request()->routeIs('attendance.summary*')
                    || request()->routeIs('attendance.payroll*')
                    || request()->routeIs('attendance.agent');
            @endphp
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarPages1" aria-expanded="{{ $userMenuActive ? 'true' : 'false' }}" aria-controls="sidebarPages1"
                    class="side-nav-link {{ $userMenuActive ? 'active' : '' }}">
                    <i class="ri-user-line"></i>
                    <span>User</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse {{ $userMenuActive ? 'show' : '' }}" id="sidebarPages1">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('roles') }}" class="{{ request()->routeIs('roles') ? 'active' : '' }}">Roles</a>
                        </li>
                        <li>
                            <a href="{{ route('users.add') }}" class="{{ request()->routeIs('users.add') ? 'active' : '' }}">Add User</a>
                        </li>
                        <li>
                            <a href="{{ route('permissions') }}" class="text-danger bg-light {{ request()->routeIs('permissions') ? 'active' : '' }}"><i
                                    class="ri-error-warning-line text-danger"></i> Reset Permission</a>
                        </li>
                        <li>
                            <a href="{{ route('permissions.view') }}" class="{{ request()->routeIs('permissions.view') ? 'active' : '' }}">View Permissions</a>
                        </li>
                        <li>
                            <a href="{{ route('attendance.summary') }}" class="{{ request()->routeIs('attendance.summary*') || request()->routeIs('attendance.monitor*') || request()->routeIs('attendance.employee') ? 'active' : '' }}">
                                <i class="ri-group-line me-2"></i>Team Monitoring
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('attendance.payroll.index') }}" class="{{ request()->routeIs('attendance.payroll*') ? 'active' : '' }}">
                                <i class="ri-wallet-3-line me-2"></i>Payroll
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('attendance.agent') }}" class="{{ request()->routeIs('attendance.agent') ? 'active' : '' }}">
                                <i class="ri-computer-line me-2"></i>Desktop Agent
                            </a>
                        </li>
                    </ul>
                </div>

            </li>

            {{-- Warehouse Management System (WMS) --}}
            <li class="side-nav-item">
                <a data-bs-toggle="collapse" href="#sidebarWms" aria-expanded="{{ request()->routeIs('wms.*') ? 'true' : 'false' }}" aria-controls="sidebarWms"
                    class="side-nav-link {{ request()->routeIs('wms.*') ? 'active' : '' }}">
                    <i class="ri-building-4-line"></i>
                    <span>Warehouse (WMS)</span>
                    <span class="menu-arrow"></span>
                </a>
                <div class="collapse {{ request()->routeIs('wms.*') ? 'show' : '' }}" id="sidebarWms">
                    <ul class="side-nav-second-level">
                        <li>
                            <a href="{{ route('wms.dashboard') }}" class="{{ request()->routeIs('wms.dashboard') ? 'active' : '' }}">Dashboard WareHouse</a>
                        </li>
                        <li>
                            <a href="{{ route('wms.structure') }}" class="{{ request()->routeIs('wms.structure') ? 'active' : '' }}">Structure</a>
                        </li>
                        <li>
                            <a href="{{ route('wms.inventory') }}" class="{{ request()->routeIs('wms.inventory') ? 'active' : '' }}">By location</a>
                        </li>
                        <li>
                            <a href="{{ route('wms.scan') }}" class="{{ request()->routeIs('wms.scan') ? 'active' : '' }}">Scan</a>
                        </li>
                        <li>
                            <a href="{{ route('wms.pick') }}" class="{{ request()->routeIs('wms.pick') ? 'active' : '' }}">Pick</a>
                        </li>
                        <li>
                            <a href="{{ route('wms.putaway') }}" class="{{ request()->routeIs('wms.putaway') ? 'active' : '' }}">Putaway</a>
                        </li>
                        <li>
                            <a href="{{ route('wms.locate') }}" class="{{ request()->routeIs('wms.locate') ? 'active' : '' }}">Locate</a>
                        </li>
                        <li>
                            <a href="{{ route('wms.movements') }}" class="{{ request()->routeIs('wms.movements') ? 'active' : '' }}">History</a>
                        </li>
                    </ul>
                </div>
            </li>
    </ul>
    <!--- End Sidemenu -->

    <div class="clearfix"></div>
</div>
</div>
<!-- ========== Left Sidebar End ========== -->

<style>
    /* Keep Missing badge beside the label (theme defaults pin .badge to absolute right). */
    .side-nav a.amz-ads-missing-nav {
        padding-right: calc(var(--tz-menu-item-padding-x, 0.75rem) * 1.5) !important;
    }
    .side-nav a.amz-ads-missing-nav > .badge {
        position: static !important;
        display: inline-block;
        vertical-align: middle;
        margin: 0 0 0 0.35rem !important;
        top: auto !important;
        right: auto !important;
        transform: none !important;
    }

    /* Keep Missing Google Shopping / SERP Ads badges beside the label. */
    .side-nav a.gs-ads-missing-nav,
    .side-nav a.gs-serp-ads-missing-nav {
        padding-right: calc(var(--tz-menu-item-padding-x, 0.75rem) * 1.5) !important;
    }
    .side-nav a.gs-ads-missing-nav > .badge,
    .side-nav a.gs-serp-ads-missing-nav > .badge {
        position: static !important;
        display: inline-block;
        vertical-align: middle;
        margin: 0 0 0 0.35rem !important;
        top: auto !important;
        right: auto !important;
        transform: none !important;
    }

    /* Map Issues — N Map count (same total as /all-marketplace-master). */
    .side-nav a.map-issues-nav > .map-issues-nmap-badge {
        background-color: #a71d2a !important;
        color: #fff !important;
        font-weight: 700;
    }

    /* Missing Listing — Missing L count (same total as /all-marketplace-master). */
    .side-nav a.missing-listing-nav > .missing-listing-badge {
        background-color: #a71d2a !important;
        color: #fff !important;
        font-weight: 700;
    }

    /* Variations Verify Masters — sum of all channel mismatch counts. */
    .side-nav a.variations-verify-masters-nav > .variations-verify-mismatch-badge {
        background-color: #a71d2a !important;
        color: #fff !important;
        font-weight: 700;
    }

    /* Accordion affordance: arrow points down when collapsed, up when open (not right). */
    .side-nav .side-nav-item > a > .menu-arrow {
        transform: translate(-50%, -50%) rotate(90deg) !important;
    }
    .side-nav .side-nav-item > a[aria-expanded="true"] > .menu-arrow,
    .side-nav .side-nav-item.menuitem-active > a:not(.collapsed) > .menu-arrow {
        transform: translate(-50%, -50%) rotate(-90deg) !important;
    }

    /*
     * All sidebar dropdowns: expand directly under the parent (accordion below),
     * not indented/flyout to the right. Covers second/third/forth/fourth levels.
     */
    .side-nav .side-nav-second-level,
    .side-nav .side-nav-third-level,
    .side-nav .side-nav-forth-level,
    .side-nav .side-nav-fourth-level,
    .side-nav ul.amz-fbm-ad-submenu {
        padding-left: 0 !important;
        margin-left: 0 !important;
    }
    .side-nav .side-nav-second-level > li > a,
    .side-nav .side-nav-third-level > li > a,
    .side-nav .side-nav-forth-level > li > a,
    .side-nav .side-nav-fourth-level > li > a,
    .side-nav ul.amz-fbm-ad-submenu > li > a {
        padding-left: calc(var(--tz-menu-item-padding-x, 0.75rem) * 1.5) !important;
    }

    /* Condensed/hover theme flyouts open to the right — force accordion below instead. */
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item {
        position: relative;
    }
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item:hover > .collapse,
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item:hover > .collapsing,
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item > .collapse.show,
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item > .collapsing {
        display: block !important;
        position: static !important;
        height: auto !important;
        transition: none !important;
    }
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item:hover > .collapse > ul,
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item:hover > .collapsing > ul,
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item > .collapse.show > ul,
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item > .collapsing > ul,
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item li:hover > .collapse > ul,
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item li:hover > .collapsing > ul {
        display: block !important;
        position: static !important;
        left: auto !important;
        top: auto !important;
        width: 100% !important;
        box-shadow: none !important;
        padding-left: 0 !important;
        margin-left: 0 !important;
    }
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item:hover > .collapse > ul a,
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item > .collapse.show > ul a {
        width: 100% !important;
    }
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item > .side-nav-link span,
    html[data-sidenav-size="condensed"]:not([data-layout="topnav"]) .wrapper .leftside-menu .side-nav .side-nav-item > a span {
        visibility: visible !important;
    }

    @media (min-width: 768px) {
        body.desktop-sidebar-collapsible .leftside-menu {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            height: 100vh !important;
            display: flex !important;
            flex-direction: column !important;
            margin-left: 0 !important;
            transform: translateX(calc(-100% - 12px)) !important;
            transition: transform 0.25s ease, box-shadow 0.25s ease !important;
            z-index: 1045 !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        body.desktop-sidebar-collapsible.desktop-menu-open .leftside-menu {
            transform: translateX(0) !important;
            box-shadow: 6px 0 20px rgba(0, 0, 0, 0.25) !important;
            visibility: visible !important;
            opacity: 1 !important;
            pointer-events: auto !important;
        }

        body.desktop-sidebar-collapsible .content-page {
            margin-left: 0 !important;
        }

        body.desktop-sidebar-collapsible .navbar-custom {
            margin-left: 0 !important;
            width: 100% !important;
        }

        body.desktop-sidebar-collapsible .leftside-menu #leftside-menu-container {
            flex: 1 1 auto !important;
            min-height: 0 !important;
            height: auto !important;
        }

        body.desktop-sidebar-collapsible::before {
            content: "";
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
            z-index: 1040;
        }

        body.desktop-sidebar-collapsible.desktop-menu-open::before {
            opacity: 1;
            pointer-events: auto;
        }
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Prevent accidental navigation/reload from sidebar logo taps.
        document.querySelectorAll('.sidebar-logo-static').forEach(function(logoLink) {
            logoLink.addEventListener('click', function(event) {
                event.preventDefault();
            });
        });
    });
</script>


<script>
    // Flyout menu functionality disabled - submenus will use standard collapse/expand behavior
    // document.addEventListener('DOMContentLoaded', function() {
    //     const SIDEBAR_SELECTOR = '.side-nav'; // root sidebar container
    //     const ITEM_SELECTOR = '.side-nav-item'; // each menu li
    //     const SUBMENU_SELECTOR = 'ul'; // submenu inside li
    //
    //     // Create single floating container reused for all submenus
    //     const floatWrap = document.createElement('div');
    //     floatWrap.setAttribute('aria-hidden', 'true');
    //     // inline styles only — no external CSS
    //     Object.assign(floatWrap.style, {
    //         position: 'absolute',
    //         display: 'none',
    //         zIndex: 99999,
    //         minWidth: '180px',
    //         background: '#1f1f1f',
    //         padding: '6px 0',
    //         borderRadius: '6px',
    //         boxShadow: '0 6px 18px rgba(0,0,0,0.25)',
    //         overflow: 'hidden',
    //         maxHeight: '80vh',
    //         overflowY: 'auto',
    //         transition: 'opacity 160ms ease',
    //         opacity: '0'
    //     });
    //     document.body.appendChild(floatWrap);
    //
    //     let hideTimer = null;
    //     let currentOwner = null; // li that owns current flyout
    //
    //     // Helper: copy submenu (<ul>) into floatWrap
    //     function showFlyoutFor(ownerLi) {
    //         const submenu = ownerLi.querySelector(SUBMENU_SELECTOR);
    //         if (!submenu) return;
    //
    //         // clone so original remains untouched
    //         const clone = submenu.cloneNode(true);
    //
    //         // normalize links in clone to display as blocks with padding via inline styles
    //         Array.from(clone.querySelectorAll('a')).forEach(a => {
    //             Object.assign(a.style, {
    //                 display: 'block',
    //                 padding: '8px 14px',
    //                 color: '#fff',
    //                 textDecoration: 'none',
    //                 whiteSpace: 'nowrap'
    //             });
    //             // small hover effect using mouse events (inline styles)
    //             a.addEventListener('mouseenter', () => a.style.background = '#333');
    //             a.addEventListener('mouseleave', () => a.style.background = 'transparent');
    //         });
    //
    //         // clear and append
    //         floatWrap.innerHTML = '';
    //         floatWrap.appendChild(clone);
    //
    //         // compute position: to the right of the owner li (or left if insufficient space)
    //         const ownerRect = ownerLi.getBoundingClientRect();
    //         const bodyRect = document.body.getBoundingClientRect();
    //         const scrollY = window.scrollY || window.pageYOffset;
    //         const floatWidth = Math.max(180, Math.min(320, ownerRect.width * 1.25)); // reasonable width
    //         floatWrap.style.minWidth = floatWidth + 'px';
    //
    //         const desiredLeft = Math.round(ownerRect.right + 6 + window.scrollX); // 6px gap
    //         const viewportRight = window.innerWidth;
    //         let left = desiredLeft;
    //         // if not enough space on right, show to left of owner
    //         if (desiredLeft + floatWidth > viewportRight - 8) {
    //             left = Math.round(ownerRect.left - floatWidth - 6 + window.scrollX);
    //             if (left < 8) left = 8;
    //         }
    //
    //         // top: align top of owner; but keep within viewport (with some margin)
    //         let top = Math.round(ownerRect.top + scrollY);
    //         const maxTop = (window.innerHeight + scrollY) - (floatWrap.offsetHeight || 300) - 10;
    //         if (top > maxTop) top = Math.max(8 + scrollY, maxTop);
    //
    //         Object.assign(floatWrap.style, {
    //             left: left + 'px',
    //             top: top + 'px',
    //             display: 'block'
    //         });
    //
    //         // force reflow then fade in
    //         requestAnimationFrame(() => {
    //             floatWrap.style.opacity = '1';
    //         });
    //
    //         currentOwner = ownerLi;
    //     }
    //
    //     function hideFlyoutImmediate() {
    //         floatWrap.style.opacity = '0';
    //         // hide after transition time
    //         setTimeout(() => {
    //             floatWrap.style.display = 'none';
    //             floatWrap.innerHTML = '';
    //         }, 180);
    //         currentOwner = null;
    //     }
    //
    //     // attach listeners to all side-nav-item elements that include a submenu UL
    //     const sidebar = document.querySelector(SIDEBAR_SELECTOR) || document.body;
    //     const items = sidebar.querySelectorAll(ITEM_SELECTOR);
    //
    //     items.forEach(li => {
    //         const submenu = li.querySelector(SUBMENU_SELECTOR);
    //         if (!submenu) return; // skip items without submenu
    //
    //         // show on mouseenter of LI or its link
    //         li.addEventListener('mouseenter', (ev) => {
    //             if (hideTimer) {
    //                 clearTimeout(hideTimer);
    //                 hideTimer = null;
    //             }
    //             showFlyoutFor(li);
    //         });
    //
    //         // start hide timer on mouseleave (so user can move into flyout without it vanishing)
    //         li.addEventListener('mouseleave', () => {
    //             if (hideTimer) clearTimeout(hideTimer);
    //             hideTimer = setTimeout(() => {
    //                 // only hide if mouse not inside floatWrap
    //                 if (!floatWrap.matches(':hover')) hideFlyoutImmediate();
    //             }, 160);
    //         });
    //     });
    //
    //     // keep flyout visible while hovering over it, hide when leaving
    //     floatWrap.addEventListener('mouseenter', () => {
    //         if (hideTimer) {
    //             clearTimeout(hideTimer);
    //             hideTimer = null;
    //         }
    //     });
    //     floatWrap.addEventListener('mouseleave', () => {
    //         if (hideTimer) clearTimeout(hideTimer);
    //         hideTimer = setTimeout(() => hideFlyoutImmediate(), 160);
    //     });
    //
    //     // Close on ESC key
    //     document.addEventListener('keydown', (e) => {
    //         if (e.key === 'Escape') hideFlyoutImmediate();
    //     });
    //
    //     // Recompute position on resize/scroll if visible
    //     window.addEventListener('resize', () => {
    //         if (currentOwner) showFlyoutFor(currentOwner);
    //     });
    //     window.addEventListener('scroll', () => {
    //         if (currentOwner) showFlyoutFor(currentOwner);
    //     });
    // });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        function applySidebarTargetBlank(root) {
            if (!root) {
                return;
            }
            root.querySelectorAll('a[href]').forEach(function(a) {
                if (a.getAttribute('data-bs-toggle') === 'collapse') {
                    return;
                }
                var h = (a.getAttribute('href') || '').trim();
                if (!h || h === '#' || /^javascript:/i.test(h)) {
                    return;
                }
                if (h.charAt(0) === '#') {
                    return;
                }
                a.setAttribute('target', '_blank');
                a.setAttribute('rel', 'noopener noreferrer');
            });
        }
        applySidebarTargetBlank(document.getElementById('leftside-menu-container'));
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var body = document.body;
        var html = document.documentElement;
        var sidebar = document.querySelector('.leftside-menu');
        var toggleBtn = document.querySelector('.button-toggle-menu');

        if (!body || !html || !sidebar || !toggleBtn) {
            return;
        }

        if (!sidebar.id) {
            sidebar.id = 'main-leftside-menu';
        }

        toggleBtn.setAttribute('aria-controls', sidebar.id);
        toggleBtn.setAttribute('aria-expanded', 'false');

        var isDesktop = function() {
            return window.innerWidth >= 768;
        };

        var closeDesktopSidebar = function() {
            body.classList.remove('desktop-menu-open');
            html.classList.remove('sidebar-enable');
            toggleBtn.setAttribute('aria-expanded', 'false');
        };

        var openDesktopSidebar = function() {
            // Keep sidebar in expanded theme mode so text labels are visible.
            html.setAttribute('data-sidenav-size', 'full');
            body.classList.add('desktop-menu-open');
            html.classList.add('sidebar-enable');
            toggleBtn.setAttribute('aria-expanded', 'true');
        };

        body.classList.add('desktop-sidebar-collapsible');
        html.setAttribute('data-sidenav-size', 'full');
        closeDesktopSidebar();

        toggleBtn.addEventListener('click', function(event) {
            if (!isDesktop()) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            if (body.classList.contains('desktop-menu-open')) {
                closeDesktopSidebar();
            } else {
                openDesktopSidebar();
            }
        }, true);

        document.addEventListener('click', function(event) {
            if (!isDesktop() || !body.classList.contains('desktop-menu-open')) {
                return;
            }

            if (sidebar.contains(event.target) || toggleBtn.contains(event.target)) {
                return;
            }

            closeDesktopSidebar();
        }, true);

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && body.classList.contains('desktop-menu-open')) {
                closeDesktopSidebar();
            }
        });

        window.addEventListener('resize', function() {
            if (!isDesktop()) {
                closeDesktopSidebar();
            }
        });
    });
</script>
