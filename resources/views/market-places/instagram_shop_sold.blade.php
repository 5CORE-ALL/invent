@extends('layouts.vertical', ['title' => 'Instagram Shop Sold'])

@section('css')
<link href="https://unpkg.com/tabulator-tables@6.3.1/dist/css/tabulator.min.css" rel="stylesheet">
@endsection

@section('content')
@include('layouts.shared.page-title', ['page_title' => 'Instagram Shop Sold', 'sub_title' => 'Facebook & Instagram'])

<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <div id="instagram-shop-sold"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script src="https://unpkg.com/tabulator-tables@6.3.1/dist/js/tabulator.min.js"></script>
<script>
    new Tabulator("#instagram-shop-sold", {
        ajaxURL: "{{ route('instagram.shop.sold.data') }}",
        layout: "fitDataStretch",
        pagination: true,
        paginationSize: 50,
        placeholder: "No sold rows",
        columns: [
            { title: "Date", field: "sale_date", headerFilter: "input" },
            { title: "Order", field: "order_name", headerFilter: "input" },
            { title: "SKU", field: "sku", headerFilter: "input" },
            {
                title: "Page",
                field: "url",
                formatter: function (cell) {
                    const url = cell.getValue();
                    if (!url) {
                        return "";
                    }
                    const link = document.createElement("a");
                    link.href = url;
                    link.target = "_blank";
                    link.rel = "noopener";
                    link.textContent = "Open";
                    return link;
                },
            },
            { title: "Title", field: "product_title", headerFilter: "input" },
            { title: "Qty", field: "quantity", hozAlign: "right" },
            { title: "Price", field: "sold_price", hozAlign: "right" },
            { title: "Gross", field: "gross_sales", hozAlign: "right" },
            { title: "Net", field: "net_sales", hozAlign: "right" },
            { title: "Discounts", field: "discounts", hozAlign: "right" },
            { title: "Returns", field: "returns", hozAlign: "right" },
        ],
    });
</script>
@endsection
