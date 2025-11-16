@extends('layouts.vertical', ['title' => 'Shopify All Channels', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0">Shopify - All Channels Overview</h4>
                        <a href="{{ url('/shopify-all-channels-export') }}" class="btn btn-outline-success btn-sm">
                            <i class="fa fa-file-excel"></i>
                            Export Excel
                        </a>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <div id="shopify-all-channels-wrapper" style="height: calc(100vh - 200px); display: flex; flex-direction: column;">
                            <div id="shopify-all-channels-table" style="flex: 1;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        $(document).ready(function () {
            const table = new Tabulator("#shopify-all-channels-table", {
                ajaxURL: "{{ url('/shopify-all-channels-json') }}",
                layout: "fitData",
                pagination: true,
                paginationSize: 50,
                columns: [
                    { title: "Parent", field: "Parent", headerFilter: "input", frozen: true },
                    { title: "SKU", field: "SKU", headerFilter: "input", frozen: true },
                    { title: "Shopify INV", field: "Shopify_INV", hozAlign: "center" },
                    { title: "OV L30", field: "Shopify_Qty", hozAlign: "center" },

                    { title: "Amz API L30", field: "Amazon_L30", hozAlign: "center" },
                    // { title: "Amz Orders L30", field: "Amazon_O_L30", hozAlign: "center" },
                    { title: "Amz Shopify L30", field: "Amazon_Q_L30", hozAlign: "center" },

                    { title: "eBay1 API L30", field: "Ebay1_L30", hozAlign: "center" },
                    // { title: "eBay Orders L30", field: "Ebay_O_L30", hozAlign: "center" },
                    { title: "eBay Shopify L30", field: "Ebay_Q_L30", hozAlign: "center" },

                    { title: "eBay2  API L30", field: "Ebay2_L30", hozAlign: "center" },
                    // { title: "eBay2 Orders L30", field: "Ebay2_O_L30", hozAlign: "center" },
                    { title: "eBay2 Shopify L30", field: "Ebay2_Q_L30", hozAlign: "center" },

                    { title: "eBay3 API L30", field: "Ebay3_L30", hozAlign: "center" },
                    // { title: "eBay3 Orders L30", field: "Ebay3_O_L30", hozAlign: "center" },
                    { title: "eBay3 Shopify L30", field: "Ebay3_Q_L30", hozAlign: "center" },

                    { title: "Reverb API L30", field: "Reverb_L30", hozAlign: "center" },
                    // { title: "Reverb Orders L30", field: "Reverb_O_L30", hozAlign: "center" },
                    { title: "Reverb Shopify L30", field: "Reverb_Q_L30", hozAlign: "center" },

                    { title: "Walmart API L30", field: "Walmart_L30", hozAlign: "center" },
                    // { title: "Walmart Orders L30", field: "Walmart_O_L30", hozAlign: "center" },
                    { title: "Walmart Shopify L30", field: "Walmart_Q_L30", hozAlign: "center" },

                    { title: "Temu API L30", field: "Temu_L30", hozAlign: "center" },
                    // { title: "Temu Orders L30", field: "Temu_O_L30", hozAlign: "center" },
                    { title: "Temu Shopify L30", field: "Temu_Q_L30", hozAlign: "center" },

                    { title: "Wayfair API L30", field: "Wayfair_L30", hozAlign: "center" },
                    // { title: "Wayfair Orders L30", field: "Wayfair_O_L30", hozAlign: "center" },
                    { title: "Wayfair Shopify L30", field: "Wayfair_Q_L30", hozAlign: "center" },

                    { title: "TikTok API L30", field: "TikTok_L30", hozAlign: "center" },

                    { title: "Mercari W Sheet API L30", field: "MercariW_L30", hozAlign: "center" },
                    // { title: "Mercari W Orders L30", field: "MercariW_O_L30", hozAlign: "center" },
                    { title: "Mercari W Shopify L30", field: "MercariW_Q_L30", hozAlign: "center" },

                    { title: "Mercari WO Sheet API L30", field: "MercariWO_L30", hozAlign: "center" },

                    { title: "PLS Sheet API L30", field: "PLS_L30", hozAlign: "center" },

                    { title: "BestBuy API L30", field: "BestBuy_L30", hozAlign: "center" },
                    // { title: "BestBuy Orders L30", field: "BestBuyUSA_O_L30", hozAlign: "center" },
                    { title: "BestBuy Shopify L30", field: "BestBuyUSA_Q_L30", hozAlign: "center" },

                    { title: "Macy API L30", field: "Macy_L30", hozAlign: "center" },
                    // { title: "Macy Orders L30", field: "Macys_O_L30", hozAlign: "center" },
                    { title: "Macy Shopify L30", field: "Macys_Q_L30", hozAlign: "center" },

                    { title: "AliExpress API L30", field: "AliExpress_L30", hozAlign: "center" },
                    // { title: "Ali Orders L30", field: "AliExpress_O_L30", hozAlign: "center" },
                    { title: "Ali Shopify L30", field: "AliExpress_Q_L30", hozAlign: "center" },

                    { title: "Shein API L30", field: "Shein_L30", hozAlign: "center" },
                    // { title: "Shein Orders L30", field: "Shein_O_L30", hozAlign: "center" },
                    { title: "Shein Shopify L30", field: "Shein_Q_L30", hozAlign: "center" },
                ],
            });
        });
    </script>
@endsection


