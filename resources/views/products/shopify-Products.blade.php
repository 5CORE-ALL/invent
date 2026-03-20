@extends('layouts.vertical', ['title' => '5core', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    @vite(['node_modules/datatables.net-fixedheader-bs5/css/fixedHeader.bootstrap5.min.css'])
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css">
@endsection

@section('content')
    @include('layouts.shared.page-title', ['sub_title' => 'Products', 'page_title' => 'Shopify Products'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="header-title">{{-- Fixed Header --}}</h4>
                        <p class="text-muted mb-0">
                            {{-- The FixedHeader will freeze in place the header and/or footer in a DataTable,
                            ensuring that title information will remain always visible. --}}
                        </p>
                    </div>
                    <div class="d-flex">
                        <div class="me-2">
                            <input type="text" id="search-input" class="form-control" placeholder="Search...">
                        </div>
                        <button id="download-excel" class="btn btn-success">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor"
                                class="bi bi-file-earmark-arrow-down-fill" viewBox="0 0 16 16">
                                <path
                                    d="M9.293 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V4.707A1 1 0 0 0 13.707 4L10 .293A1 1 0 0 0 9.293 0M9.5 3.5v-2l3 3h-2a1 1 0 0 1-1-1m-1 4v3.793l1.146-1.147a.5.5 0 0 1 .708.708l-2 2a.5.5 0 0 1-.708 0l-2-2a.5.5 0 0 1 .708-.708L7.5 11.293V7.5a.5.5 0 0 1 1 0">
                                </path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <table id="fixed-header-datatable" class="table table-striped dt-responsive nowrap w-100">
                        <thead>
                            <tr>
                                <th>Id</th>
                                <th>Img</th>
                                <th>Product</th>
                                <th>Status</th>
                                <th>Inventory</th>
                                <th>SKU</th>
                                <th>Sale Channels</th>
                                <th>Market</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Vendor</th>
                            </tr>
                        </thead>
                        <tbody id="product-table-body">
                            <!-- ✅ Dynamic Product Data Will Be Inserted Here via JavaScript -->
                        </tbody>
                    </table>
                    <nav>
                        <ul class="pagination justify-content-center" id="pagination"></ul>
                    </nav>
                </div> <!-- end card body-->
            </div> <!-- end card -->
        </div><!-- end col-->
    </div> <!-- end row-->

    <!-- ✅ Load Required JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script>
document.addEventListener("DOMContentLoaded", function() {
    const products = @json($products);
    const tableBody = document.getElementById("product-table-body");
    const downloadBtn = document.getElementById("download-excel");
    const searchInput = document.getElementById("search-input");
    const pagination = document.getElementById("pagination");
    const pageSize = 100;
    let currentPage = 1;
    let filteredProducts = products;

    function renderTable(page = 1) {
        tableBody.innerHTML = "";
        const start = (page - 1) * pageSize;
        const end = start + pageSize;
        const pageProducts = filteredProducts.slice(start, end);

        if (pageProducts.length > 0) {
            pageProducts.forEach((product, index) => {
            const rowNumber = start + index + 1; // Calculate row number based on current page
            let imageUrl = product.image ? product.image : "/images/users/avatar-2.jpg";
            let row = `
                <tr>
                    <td>${rowNumber}</td>
                    <td class="account-user-avatar">
                        <img src="${imageUrl}" alt="Product" width="60" class="rounded-circle" />
                    </td>
                    <td>${product.product}</td>
                    <td><span class="badge bg-primary-subtle text-primary rounded-pill fs-5">${product.status}</span></td>
                    <td>${product.inventory ?? 'N/A'}</td>
                    <td>${product.sku ?? 'N/A'}</td>
                    <td>**</td>
                    <td>**</td>
                    <td>Electronics</td>
                    <td>${product.type}</td>
                    <td>${product.vendor}</td>
                </tr>
            `;
            tableBody.innerHTML += row;
            });
        } else {
            tableBody.innerHTML = `<tr><td colspan="11" class="text-center">No Products Available</td></tr>`;
        }
    }

    function renderPagination() {
        pagination.innerHTML = "";
        const totalPages = Math.ceil(filteredProducts.length / pageSize);
        if (totalPages <= 1) return;

        // Previous button
        pagination.innerHTML += `<li class="page-item${currentPage === 1 ? ' disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage - 1}">Previous</a>
        </li>`;

        // Page numbers (show max 5 pages at a time)
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, currentPage + 2);
        for (let i = startPage; i <= endPage; i++) {
            pagination.innerHTML += `<li class="page-item${i === currentPage ? ' active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>`;
        }

        // Next button
        pagination.innerHTML += `<li class="page-item${currentPage === totalPages ? ' disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage + 1}">Next</a>
        </li>`;
    }

    // Pagination click event
    pagination.addEventListener("click", function(e) {
        if (e.target.tagName === "A") {
            e.preventDefault();
            const page = parseInt(e.target.getAttribute("data-page"));
            if (!isNaN(page) && page >= 1 && page <= Math.ceil(filteredProducts.length / pageSize)) {
                currentPage = page;
                renderTable(currentPage);
                renderPagination();
            }
        }
    });

    // Search filter
    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const searchTerm = this.value.toLowerCase();
            filteredProducts = products.filter(product => {
                return Object.values(product).some(val =>
                    (val ? val.toString().toLowerCase() : "").includes(searchTerm)
                );
            });
            currentPage = 1;
            renderTable(currentPage);
            renderPagination();
        });
    }

    // Initial render
    renderTable(currentPage);
    renderPagination();

    // Excel Export Function
    if (downloadBtn) {
        downloadBtn.addEventListener("click", function() {
            let tableElement = document.getElementById("fixed-header-datatable");
            let sheet = XLSX.utils.table_to_sheet(tableElement);
            let workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, sheet, "Shopify Products");
            XLSX.writeFile(workbook, "Shopify Products.xlsx");
        });
    }
});
</script>
    <!-- Separate Search Functionality Script -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.getElementById("search-input");
            
            if (searchInput) {
                searchInput.addEventListener("keyup", function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll("#product-table-body tr");
                    
                    rows.forEach(row => {
                        const rowText = row.textContent.toLowerCase();
                        if (rowText.includes(searchTerm)) {
                            row.style.display = "";
                        } else {
                            row.style.display = "none";
                        }
                    });
                });
            }
        });
    </script>
@endsection
