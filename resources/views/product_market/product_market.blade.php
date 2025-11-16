@extends('layouts.vertical', ['title' => 'Product Marketing', 'sidenav' => 'condensed'])

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>Product Data</h4>

                        <!-- 🔍 Filter Controls -->
                        <div class="d-flex gap-2">
                            <select id="filter-parent" class="form-select" style="width:180px;">
                                <option value="">Filter by Parent</option>
                            </select>

                            <select id="filter-sku" class="form-select" style="width:180px;">
                                <option value="">Filter by SKU</option>
                            </select>

                            <button id="clear-filters" class="btn btn-sm btn-secondary">
                                <i class="fa fa-times"></i> Clear Filters
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        <div id="fba-table"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@section('script-bottom')
    <script>
        $(document).ready(function() {
            // ✅ Initialize Tabulator
            const table = new Tabulator("#fba-table", {
                ajaxURL: "product-market/details",
                layout: "fitData",
                pagination: true,
                paginationSize: 50,
                columns: [{
                        title: "Parent",
                        field: "Parent",
                        hozAlign: "center"
                    },
                    {
                        title: "SKU",
                        field: "SKU",
                        hozAlign: "center"
                    },
                    {
                        title: "Shopify INV",
                        field: "Shopify_INV",
                        hozAlign: "center"
                    },
                    {
                        title: "OVL3",
                        field: "OVL3",
                        hozAlign: "center"
                    },
                    {
                        title: "Dil",
                        field: "Dil",
                        hozAlign: "center",
                        formatter: function(cell) {
                            return cell.getValue() + "%";
                        }
                    }
                ],
                ajaxResponse: function(url, params, response) {
                    // ✅ Populate dropdown filters dynamically
                    let parentSet = new Set();
                    let skuSet = new Set();

                    response.forEach(row => {
                        if (row.Parent && row.Parent !== '-') parentSet.add(row.Parent);
                        if (row.SKU && row.SKU !== '-') skuSet.add(row.SKU);
                    });

                    const parentSelect = $("#filter-parent");
                    const skuSelect = $("#filter-sku");

                    // Empty previous options (except first)
                    parentSelect.find("option:not(:first)").remove();
                    skuSelect.find("option:not(:first)").remove();

                    [...parentSet].sort().forEach(p => parentSelect.append(
                        `<option value="${p}">${p}</option>`));
                    [...skuSet].sort().forEach(s => skuSelect.append(
                        `<option value="${s}">${s}</option>`));

                    return response; // return for rendering
                }
            });

            // ✅ Parent filter
            $("#filter-parent").on("change", function() {
                const val = $(this).val();
                table.setFilter("Parent", "like", val);
            });

            // ✅ SKU filter
            $("#filter-sku").on("change", function() {
                const val = $(this).val();
                table.setFilter("SKU", "like", val);
            });

            // ✅ Clear filters
            $("#clear-filters").on("click", function() {
                $("#filter-parent").val("");
                $("#filter-sku").val("");
                table.clearFilter();
            });
        });
    </script>
@endsection
