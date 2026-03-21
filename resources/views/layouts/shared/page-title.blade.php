<!-- start page title -->
<div class="row">
    <div class="col-12">
        <div class="page-title-box">
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    <li class="breadcrumb-item"><a href="javascript: void(0);">5Core</a></li>
                    <li class="breadcrumb-item"><a href="javascript: void(0);">{{ $sub_title }}</a></li>
                    <li class="breadcrumb-item active">{{ $page_title }}</li>
                </ol>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <h4 class="page-title mb-0">{{ $page_title }}</h4>
                @stack('page-title-after')
            </div>
        </div>
    </div>
</div>
<!-- end page title -->