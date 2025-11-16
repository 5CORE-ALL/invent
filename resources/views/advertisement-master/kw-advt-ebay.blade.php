@extends('layouts.vertical', ['title' => $title, 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('css')
    @include('advertisement-master.include.style');
@endsection

@section('content')
    @include('layouts.shared/page-title', ['page_title' => $title, 'sub_title' => $subtitle])
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    @include('advertisement-master.include.pagination');
                    <div class="table-container">
                        <table class="custom-resizable-table" id="ebay-table">
                            <thead>
                                <tr>
                                    <th data-field="sl_no">SL No. <span class="sort-arrow">↓</span></th>
                                    <th data-field="parent" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center">
                                            <div class="d-flex align-items-center sortable-header">
                                                Parent <span class="sort-arrow">↓</span>
                                            </div>
                                            <div class="mt-1 dropdown-search-container">
                                                <input type="text" class="form-control form-control-sm parent-search"
                                                    placeholder="Search parent..." id="parentSearch">
                                                <div class="dropdown-search-results" id="parentSearchResults"></div>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="sku" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center sortable">
                                            <div class="d-flex align-items-center">
                                                Sku <span class="sort-arrow">↓</span>
                                            </div>
                                            <div class="mt-1 dropdown-search-container">
                                                <input type="text" class="form-control form-control-sm sku-search"
                                                    placeholder="Search SKU..." id="skuSearch">
                                                <div class="dropdown-search-results" id="skuSearchResults"></div>
                                            </div>
                                        </div>
                                    </th>
                                    <th data-field="inv" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                INV <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="inv-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="ov_l30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                OV L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ovl30-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="ov_dil" style="vertical-align: middle; white-space: nowrap;">

                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                OV DIL <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ovdil-total">0%</div>
                                        </div>
                                    </th>







                                    <th data-field="imp-l30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                IMP L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="imp-l30-total">0</div>
                                        </div>
                                    </th>

                                    <th data-field="clicks-l30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                CLICKS L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="clicks-l30-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="ctr-l30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                CTR L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ctr-l30-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="spend-l30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                SPEND L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="spend-l30-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="ad-sales-l30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                AD SALES L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ad-sales-l30-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="cpc-l1" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                CPC L1 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="cpc-l1-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="ad-sold-l30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                AD SOLD L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ad-sold-l30-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="acos-l30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                ACOS L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ad-sold-l30-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="cvr-l30" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                CVR L30 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="cvr-l30-total">0%</div>
                                        </div>
                                    </th>
                                    <th data-field="bgt-crnt" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                BGT CRNT <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="bgt-crnt-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="ubl7" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                UBL7 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ubl7-total">0</div>
                                        </div>
                                    </th>
                                    <th data-field="ubl1" style="vertical-align: middle; white-space: nowrap;">
                                        <div class="d-flex flex-column align-items-center" style="gap: 4px">
                                            <div class="d-flex align-items-center">
                                                UBL1 <span class="sort-arrow">↓</span>
                                            </div>
                                            <div style="width: 100%; height: 5px; background-color: #9ec7f4;"></div>
                                            <div class="metric-total" id="ubl1-total">0</div>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>

                    <div id="data-loader" class="card-loader-overlay" style="display: none;">
                        <div class="loader-content">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="loader-text">Loading data...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!--for popup modal script-->
    <script>
        flatpickr("#duration", {
            enableTime: true,
            mode: "range",
            dateFormat: "M d, Y h:i K"
        });

        document.getElementById("createBtn").addEventListener("click", function () {
            const form = document.getElementById("taskForm");
            const title = form.querySelector('input[placeholder="Enter Title"]').value.trim();
            const assignor = form.querySelectorAll('select')[0].value;
            const assignee = form.querySelectorAll('select')[2].value;
            const duration = form.querySelector('#duration').value;

            if (!title || assignor === "Select Assignor" || assignee === "Please Select" || !duration) {
                alert("Please fill in all required fields marked with *");
                return;
            }

            alert("🎉 Task Created Successfully!");

            form.reset();
            const modal = bootstrap.Modal.getInstance(document.getElementById('createTaskModal'));
            modal.hide();
        });
    </script>
    <!--for popup modal script-->
    <script>
        document.body.style.zoom = "80%";
        $(document).ready(function () {
            $('#editPercentBtn').on('click', function () {
                var $input = $('#updateAllSkusPercent');
                var $icon = $(this).find('i');
                var originalValue = $input.val(); // Store original value

                if ($icon.hasClass('fa-pen')) {
                    // Enable editing
                    $input.prop('disabled', false).focus();
                    $icon.removeClass('fa-pen').addClass('fa-check');
                } else {
                    // Submit and disable editing
                    var percent = parseFloat($input.val());

                    // Validate input
                    if (isNaN(percent) || percent < 0 || percent > 100) {
                        showNotification('danger', 'Invalid percentage value. Must be between 0 and 100.');
                        $input.val(originalValue); // Restore original value
                        return;
                    }

                    $.ajax({
                        url: '/update-all-amazon-skus',
                        type: 'POST',
                        data: {
                            percent: percent,
                            _token: $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function (response) {
                            showNotification('success', 'Percentage updated successfully!');
                            $input.prop('disabled', true);
                            $icon.removeClass('fa-check').addClass('fa-pen');
                        },
                        error: function (xhr) {
                            showNotification('danger', 'Error updating percentage.');
                            $input.val(originalValue); // Restore original value
                            $input.prop('disabled', true);
                            $icon.removeClass('fa-check').addClass('fa-pen');
                        }
                    });
                }
            });

            // Cache system
            const amazonDataCache = {
                cache: {},

                set: function (id, data) {
                    this.cache[id] = JSON.parse(JSON.stringify(data));
                },

                get: function (id) {
                    return this.cache[id] ? JSON.parse(JSON.stringify(this.cache[id])) : null;
                },

                updateField: function (id, field, value) {
                    if (this.cache[id]) {
                        this.cache[id][field] = value;
                    }
                },

                clear: function () {
                    this.cache = {};
                }
            };

            // Clear cache on page load
            window.addEventListener('load', function () {
                amazonDataCache.clear();
            });

            // Current state
            let currentPage = 1;
            let rowsPerPage = Infinity;
            let currentSort = {
                field: null,
                direction: 1
            };
            let tableData = [];
            let filteredData = [];
            let isResizing = false;
            let isLoading = false;
            let isEditMode = false;
            let currentEditingElement = null;
            let isNavigationActive = false; // Add this line

            // Parent Navigation System
            let currentParentIndex = -1; // -1 means showing all products
            let uniqueParents = [];
            let isPlaying = false;

            // Define status indicator fields for different modal types
            const statusIndicatorFields = {
                'price view': ['PFT_percentage', 'TPFT', 'ROI_percentage', 'Spft%'],
                'advertisement view': ['KwAcos60', 'KwAcos30', 'KwCvr60', 'KwCvr30',
                    'PtAcos60', 'PtAcos30', 'PtCvr60', 'PtCvr30',
                    'DspAcos60', 'DspAcos30', 'DspCvr60', 'DspCvr30',
                    'HdAcos60', 'HdAcos30', 'HdCvr60', 'HdCvr30',
                    'TAcos60', 'TAcos30', 'TCvr60', 'TCvr30'
                ],
                'conversion view': ['SCVR', 'KwCvr60', 'KwCvr30', 'PtCvr60', 'PtCvr30',
                    'DspCvr60', 'DspCvr30', 'HdCvr60', 'HdCvr30',
                    'TCvr60', 'TCvr30'
                ]
            };

            // Filter state
            const state = {
                filters: {
                    'Dil%': 'all',
                    'A Dil%': 'all',
                    'PFT_percentage': 'all',
                    'ROI_percentage': 'all',
                    'Tacos30': 'all',
                    'SCVR': 'all',
                    'entryType': 'all'
                }
            };

            // Modal System
            const ModalSystem = {
                modals: [],
                zIndex: 1050,

                createModal: function (id, title, content) {
                    // Remove existing modal if it exists
                    let existingModal = document.getElementById(id);
                    if (existingModal) {
                        existingModal.remove();
                        this.modals = this.modals.filter(m => m.id !== id);
                    }

                    // Create modal element
                    const modalElement = document.createElement('div');
                    modalElement.id = id;
                    modalElement.className = 'custom-modal fade';
                    modalElement.style.zIndex = this.zIndex++;

                    // Set modal HTML
                    modalElement.innerHTML = `
                                                                    <div class="custom-modal-dialog">
                                                                        <div class="custom-modal-content">
                                                                            <div class="custom-modal-header">
                                                                                <h5 class="custom-modal-title">${title}</h5>
                                                                                <button type="button" class="custom-modal-close" data-modal-id="${id}">&times;</button>
                                                                            </div>
                                                                            <div class="custom-modal-body">${content}</div>
                                                                        </div>
                                                                    </div>
                                                                `;

                    document.body.appendChild(modalElement);

                    // Store modal reference
                    const modal = {
                        id: id,
                        element: modalElement,
                        zIndex: modalElement.style.zIndex
                    };
                    this.modals.push(modal);

                    // Setup events after a brief delay to ensure DOM is ready
                    setTimeout(() => {
                        this.setupModalEvents(modal);
                    }, 50);

                    return modal;
                },

                setupModalEvents: function (modal) {
                    const modalElement = modal.element;

                    // Find close button
                    const closeBtn = modalElement.querySelector('.custom-modal-close');
                    if (!closeBtn) {
                        console.error('Close button not found in modal', modal.id);
                        return;
                    }

                    // Setup close button click
                    closeBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.closeModal(modal.id);
                    });

                    // Make draggable
                    this.makeDraggable(modalElement);
                },

                makeDraggable(modalElement) {
                    if (!modalElement) {
                        console.error('Modal element not found');
                        return;
                    }

                    const header = modalElement.querySelector('.custom-modal-header');
                    const dialog = modalElement.querySelector('.custom-modal-dialog');

                    if (!header || !dialog) {
                        console.error('Could not find modal elements', {
                            header,
                            dialog
                        });
                        return;
                    }

                    let isDragging = false;
                    let startX, startY, initialLeft, initialTop;

                    const downHandler = (e) => {
                        if (e.button !== 0 || $(e.target).is('input, select, textarea, button, a')) return;

                        isDragging = true;
                        startX = e.clientX;
                        startY = e.clientY;

                        // Get current position
                        const rect = dialog.getBoundingClientRect();
                        initialLeft = rect.left;
                        initialTop = rect.top;

                        // Prevent text selection during drag
                        document.body.style.userSelect = 'none';
                        document.body.style.cursor = 'grabbing';

                        e.preventDefault();
                    };

                    const moveHandler = (e) => {
                        if (!isDragging) return;

                        // Calculate new position
                        const dx = e.clientX - startX;
                        const dy = e.clientY - startY;

                        // Apply new position
                        dialog.style.left = `${initialLeft + dx}px`;
                        dialog.style.top = `${initialTop + dy}px`;
                    };

                    const upHandler = () => {
                        if (!isDragging) return;

                        isDragging = false;
                        document.body.style.userSelect = '';
                        document.body.style.cursor = '';
                    };

                    // Add event listeners
                    header.addEventListener('mousedown', downHandler);
                    document.addEventListener('mousemove', moveHandler);
                    document.addEventListener('mouseup', upHandler);

                    // Store references for cleanup
                    modalElement._dragHandlers = {
                        downHandler,
                        moveHandler,
                        upHandler
                    };
                },

                cleanupDragHandlers(modalElement) {
                    if (!modalElement || !modalElement._dragHandlers) return;

                    const {
                        downHandler,
                        moveHandler,
                        upHandler
                    } = modalElement._dragHandlers;
                    const header = modalElement.querySelector('.custom-modal-header');

                    if (header) {
                        header.removeEventListener('mousedown', downHandler);
                    }

                    document.removeEventListener('mousemove', moveHandler);
                    document.removeEventListener('mouseup', upHandler);

                    // Reset cursor and selection
                    document.body.style.userSelect = '';
                    document.body.style.cursor = '';

                    delete modalElement._dragHandlers;
                },

                bringToFront: function (modal) {
                    modal.element.style.zIndex = this.zIndex++;
                    modal.zIndex = modal.element.style.zIndex;
                },

                showModal: function (id) {
                    const modal = this.modals.find(m => m.id === id);
                    if (!modal) return;

                    this.bringToFront(modal);

                    // Show modal
                    modal.element.classList.add('show');
                    modal.element.style.display = 'block';

                    // Show close all button if we have modals
                    if (this.modals.length > 0) {
                        $('#close-all-modals').show();
                    }
                },

                closeModal: function (id) {
                    const modalIndex = this.modals.findIndex(m => m.id === id);
                    if (modalIndex === -1) return;

                    const modal = this.modals[modalIndex];
                    this.cleanupDragHandlers(modal.element);
                    modal.element.classList.remove('show');

                    setTimeout(() => {
                        modal.element.style.display = 'none';

                        // Remove from array
                        this.modals.splice(modalIndex, 1);

                        // Hide close all button if no modals left
                        if (this.modals.length === 0) {
                            $('#close-all-modals').hide();
                        }
                    }, 300);
                },
                closeAllModals: function () {
                    // Close all modals from last to first to prevent z-index issues
                    while (this.modals.length > 0) {
                        const modal = this.modals.pop();
                        this.cleanupDragHandlers(modal.element);
                        modal.element.classList.remove('show');
                        setTimeout(() => {
                            modal.element.style.display = 'none';
                        }, 50);
                    }
                    $('#close-all-modals').hide();
                }
            };
            // Close all modals button handler
            $('#close-all-modals').on('click', function () {
                ModalSystem.closeAllModals();
            });

            function initPlaybackControls() {
                // Get all unique parent ASINs
                uniqueParents = [...new Set(tableData.map(item => item.Parent))];

                // Set up event handlers
                $('#play-forward').click(nextParent);
                $('#play-backward').click(previousParent);
                $('#play-pause').click(stopNavigation);
                $('#play-auto').click(startNavigation);

                // Initialize button states
                updateButtonStates();
            }

            function startNavigation() {
                if (uniqueParents.length === 0) return;

                isNavigationActive = true;
                currentParentIndex = 0;

                // Show R&A column
                $('th[data-field="r&a"], td:nth-child(4)').removeClass('hide-column');

                showCurrentParent();

                // Update button visibility
                $('#play-auto').hide();
                $('#play-pause').show()
                    .removeClass('btn-light'); // Ensure default color is removed

                // Set initial color
                checkParentRAStatus();
            }

            function stopNavigation() {
                isNavigationActive = false;
                currentParentIndex = -1;

                // Hide R&A column
                $('th[data-field="r&a"], td:nth-child(4)').addClass('hide-column');

                // Update button visibility and reset color
                $('#play-pause').hide();
                $('#play-auto').show()
                    .removeClass('btn-success btn-warning btn-danger')
                    .addClass('btn-light');

                // Show all products
                filteredData = [...tableData];
                currentPage = 1;
                renderTable();
                calculateTotals();
            }

            function nextParent() {
                if (!isNavigationActive) return;
                if (currentParentIndex >= uniqueParents.length - 1) return;

                currentParentIndex++;
                showCurrentParent();
            }

            function previousParent() {
                if (!isNavigationActive) return;
                if (currentParentIndex <= 0) return;

                currentParentIndex--;
                showCurrentParent();
            }

            function showCurrentParent() {
                if (!isNavigationActive || currentParentIndex === -1) return;

                // Filter data to show only current parent's products
                filteredData = tableData.filter(item => item.Parent === uniqueParents[currentParentIndex]);

                // Update UI
                currentPage = 1;
                renderTable();
                calculateTotals();
                updateButtonStates();
                checkParentRAStatus(); // Add this line
            }

            function updateButtonStates() {
                // Enable/disable navigation buttons based on position
                $('#play-backward').prop('disabled', !isNavigationActive || currentParentIndex <= 0);
                $('#play-forward').prop('disabled', !isNavigationActive || currentParentIndex >= uniqueParents
                    .length - 1);

                // Update button tooltips
                $('#play-auto').attr('title', isNavigationActive ? 'Show all products' : 'Start parent navigation');
                $('#play-pause').attr('title', 'Stop navigation and show all');
                $('#play-forward').attr('title', isNavigationActive ? 'Next parent' : 'Start navigation first');
                $('#play-backward').attr('title', isNavigationActive ? 'Previous parent' :
                    'Start navigation first');

                // Update button colors based on state
                if (isNavigationActive) {
                    $('#play-forward, #play-backward').removeClass('btn-light').addClass('btn-primary');
                } else {
                    $('#play-forward, #play-backward').removeClass('btn-primary').addClass('btn-light');
                }
            }

            function checkParentRAStatus() {
                if (!isNavigationActive || currentParentIndex === -1) return;

                const currentParent = uniqueParents[currentParentIndex];
                const parentRows = tableData.filter(item => item.Parent === currentParent);

                if (parentRows.length === 0) return;

                let checkedCount = 0;
                let rowsWithRAData = 0;

                parentRows.forEach(row => {
                    // Only count rows that have R&A data (not undefined/null/empty)
                    if (row['R&A'] !== undefined && row['R&A'] !== null && row['R&A'] !== '') {
                        rowsWithRAData++;
                        if (row['R&A'] === true || row['R&A'] === 'true' || row['R&A'] === '1') {
                            checkedCount++;
                        }
                    }
                });

                // Determine which button is currently visible
                const $activeButton = $('#play-pause').is(':visible') ? $('#play-pause') : $('#play-auto');

                // Remove all state classes first
                $activeButton.removeClass('btn-success btn-warning btn-danger btn-light');

                if (rowsWithRAData === 0) {
                    // No rows with R&A data at all (all empty)
                    $activeButton.addClass('btn-light');
                } else if (checkedCount === rowsWithRAData) {
                    // All rows with R&A data are checked (green)
                    $activeButton.addClass('btn-success');
                } else if (checkedCount > 0) {
                    // Some rows with R&A data are checked (yellow)
                    $activeButton.addClass('btn-warning');
                } else {
                    // No rows with R&A data are checked (red)
                    $activeButton.addClass('btn-danger');
                }
            }

            // Initialize everything
            function initTable() {
                loadData().then(() => {
                    // Hide R&A column initially
                    $('th[data-field="r&a"], td:nth-child(4)').addClass('hide-column');
                    renderTable();
                    initResizableColumns();
                    initSorting();
                    initPagination();
                    initSearch();
                    initColumnToggle();
                    initFilters();
                    calculateTotals();
                    initEnhancedDropdowns();
                    initManualDropdowns();
                    initModalTriggers();
                    initPlaybackControls();
                    initRAEditHandlers(); // Add this line
                    initNREditHandlers();
                });
            }

            // Initialize modal triggers
            function initModalTriggers() {
                $(document).on('click', '.wmpnm-view-trigger', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'WMPNM view');
                    } else {
                        console.error("No data found for WMPNM view");
                    }
                });
                $(document).on('click', '.scouth-products-view-trigger', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'scouth products view');
                    } else {
                        console.error("No data found for Scouth Products View");
                    }
                });

                $(document).on('click', '.ad-view-trigger', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'visibility view');
                    } else {
                        console.error("No data found for Visibility view");
                    }
                });

                $(document).on('click', '.price-view-trigger', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'price view');
                    } else {
                        console.error("No data found for Price view");
                    }
                });

                $(document).on('click', '.advertisement-view-trigger', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'advertisement view');
                    } else {
                        console.error("No data found for Advertisement view");
                    }
                });

                $(document).on('click', '.conversion-view-trigger', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const rawData = $(this).data('item');
                    if (rawData) {
                        openModal(rawData, 'conversion view');
                    } else {
                        console.error("No data found for Conversion view");
                    }
                });
            }

            // Load data from server
            function loadData() {
                showLoader();
                return $.ajax({
                    url: '/kw-ebay-get-data',
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        console.log('Data loaded:', response.data);

                        if (response && response.data) {
                            tableData = response.data.map((item, index) => {
                                // Calculate A Dil% as (A L30 / INV), handle division by zero
                                const inv = Number(item.INV) || 0;
                                const aL30 = Number(item['A_L30']) || 0;
                                const aDil = inv > 0 ? aL30 / inv : 0;

                                return {
                                    sl_no: index + 1,
                                    'SL No.': item['SL No.'] || index + 1,
                                    Parent: item.Parent || item.parent || item.parent_asin ||
                                        item.Parent_ASIN || '(No Parent)',
                                    '(Child) sku': item['(Child) sku'] || '',
                                    'R&A': item['R&A'] !== undefined ? item['R&A'] : '',
                                    INV: inv,
                                    L30: item.L30 || 0,
                                    'Dil%': item['Dil%'] || 0,
                                    'A L30': aL30,
                                    units_ordered_l60: item.units_ordered_l60 || 0,
                                    'A Dil%': aDil, // <-- Use calculated value here
                                    Sess30: item.Sess30 || 0,
                                    price: Number(item.price) || 0,
                                    COMP: item.COMP || 0,
                                    min_price: item.scout_data ? item.scout_data.min_price : 0,
                                    all_products: item.scout_data ? item.scout_data.all_data :
                                        0,
                                    'PFT_percentage': item['PFT_percentage'] || 0,
                                    TPFT: item.TPFT || 0,
                                    ROI_percentage: item.ROI_percentage || 0,
                                    Tacos30: item.Tacos30 || 0,
                                    SCVR: item.SCVR || 0,
                                    LP: item.LP_productmaster || 0,
                                    SHIP: item.Ship_productmaster || 0,
                                    'ad cost/ pc': item['ad cost/ pc'] || '',
                                    is_parent: item['(Child) sku'] ? item['(Child) sku']
                                        .toUpperCase().includes("PARENT") : false,
                                    raw_data: item || {} // Ensure raw_data always exists
                                };
                            });

                            console.log('Data loaded successfully:', tableData);
                            filteredData = [...tableData];

                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Error loading data:', error);
                        showNotification('danger', 'Failed to load data. Please try again.');
                    },
                    complete: function () {
                        hideLoader();
                    }
                });
            }

            // Render table with current data
            function renderTable() {
                const $tbody = $('#ebay-table tbody');
                $tbody.empty();

                if (isLoading) {
                    $tbody.append('<tr><td colspan="18" class="text-center">Loading data...</td></tr>');
                    return;
                }

                if (filteredData.length === 0) {
                    $tbody.append('<tr><td colspan="18" class="text-center">No matching records found</td></tr>');
                    return;
                }

                // Color coding functions
                const getDilColor = (value) => {
                    const percent = parseFloat(value) || 0;
                    if (percent < 16.66) return 'red';
                    if (percent >= 16.66 && percent < 25) return 'yellow';
                    if (percent >= 25 && percent < 50) return 'green';
                    return 'pink';
                };

                const getCTRColor = (value) => {
                    if (value < 0.3) return 'red';
                    if (value < 0.5) return 'yellow';
                    if (value < 1.0) return 'green';
                    return 'pink';
                };

                const getACOSColor = (value) => {
                    if (value > 50) return 'red';
                    if (value > 30) return 'yellow';
                    if (value > 15) return 'green';
                    return 'pink';
                };

                const getCVRColor = (value) => {
                    if (value < 5) return 'red';
                    if (value < 10) return 'yellow';
                    if (value < 20) return 'green';
                    return 'pink';
                };

                filteredData.forEach(item => {
                    const $row = $('<tr>');
                    if (item.is_parent) {
                        $row.addClass('parent-row');
                    }

                    // Basic info columns
                    $row.append($('<td>').text(item['SL No.']));
                    $row.append($('<td>').text(item.Parent));

                    // SKU with hover content for links
                    const $skuCell = $('<td>').addClass('skuColumn').css('position', 'static');
                    if (item.is_parent) {
                        $skuCell.html(`<strong>${item['(Child) sku']}</strong>`);
                    } else {
                        const buyerLink = item.raw_data['AMZ LINK BL'] || '';
                        const sellerLink = item.raw_data['AMZ LINK SL'] || '';

                        if (buyerLink || sellerLink) {
                            $skuCell.html(`
                                                <div class="sku-tooltip-container">
                                                    <span class="sku-text">${item['(Child) sku']}</span>
                                                    <div class="sku-tooltip">
                                                        ${buyerLink ? `<div class="sku-link"><a href="${buyerLink}" target="_blank" rel="noopener noreferrer">Buyer link</a></div>` : ''}
                                                        ${sellerLink ? `<div class="sku-link"><a href="${sellerLink}" target="_blank" rel="noopener noreferrer">Seller link</a></div>` : ''}
                                                    </div>
                                                </div>
                                            `);
                        } else {
                            $skuCell.text(item['(Child) sku']);
                        }
                    }
                    $row.append($skuCell);

                    // R&A checkbox if navigation is active
                    if (isNavigationActive) {
                        const $raCell = $('<td>').addClass('ra-cell');
                        if (item['R&A'] !== undefined && item['R&A'] !== null && item['R&A'] !== '') {
                            const $container = $('<div>').addClass('ra-edit-container d-flex align-items-center');
                            const $checkbox = $('<input>', {
                                type: 'checkbox',
                                checked: item['R&A'] === true || item['R&A'] === 'true' || item['R&A'] === '1',
                                class: 'ra-checkbox',
                                disabled: true
                            }).data('original-value', item['R&A']);
                            const $editIcon = $('<i>').addClass('fas fa-pen edit-icon ml-2 text-primary')
                                .css('cursor', 'pointer')
                                .attr('title', 'Edit R&A');
                            $container.append($checkbox, $editIcon);
                            $raCell.append($container);
                        } else {
                            $raCell.html('&nbsp;');
                        }
                        $row.append($raCell);
                    }

                    // Inventory metrics
                    $row.append($('<td>').text(item.INV));
                    $row.append($('<td>').text(item.L30));
                    const ovDil = item.raw_data.OVDil ?? 0;

                    $row.append($('<td>').html(`
        <span class="dil-percent-value ${getDilColor(ovDil)}" title="Dil% = L30 / INV">
            ${ovDil.toFixed(2)}%
        </span> 
        <span class="text-info tooltip-icon wmpnm-view-trigger" 
              data-bs-toggle="tooltip" 
              data-bs-placement="left" 
              title="WMPNM View"
              data-item='${JSON.stringify(item.raw_data)}'>W</span>
    `));


                    // Advertising metrics calculations
                    const impressions = parseFloat(item.raw_data.Impressions) || 0;
                    const clicksL30 = parseFloat(item.raw_data.Clicks) || 0;
                    const adSpendL30 = parseFloat(item.raw_data['Ad fees'] || item.raw_data['TSpnd30'] || 0);
                    const adSalesL30 = parseFloat(item.raw_data['Sales'] || 0);
                    const adSoldL30 = parseFloat(item.raw_data['Sold quantity'] || 0);
                    const dailyBudget = parseFloat(item.raw_data['Daily budget'] || 0);
                    const Average_cost_per_click = parseFloat(item.raw_data['Average cost per click'] || 0);

                    // CTR L30 = (Clicks/Impressions)%
                    const ctr = impressions > 0 ? (clicksL30 / impressions) * 100 : 0;

                    // ACOS L30 calculation
                    let acosValue;
                    if (adSpendL30 > 0 && adSalesL30 === 0) {
                        acosValue = 100; // 100% when spend >0 and sales=0
                    } else if (adSpendL30 > 0 && adSalesL30 > 0) {
                        acosValue = (adSpendL30 / adSalesL30) * 100; // (spend/sales)%
                    } else {
                        acosValue = null; // Blank when spend=0
                    }

                    // CVR L30 = (Ad sold L30/Clicks L30)%
                    const cvr = clicksL30 > 0 ? (adSoldL30 / clicksL30) * 100 : 0;

                    // Append advertising metrics columns
                    $row.append($('<td>').text(impressions.toLocaleString())); // IMP L30
                    $row.append($('<td>').text(clicksL30.toLocaleString())); // CLICKS L30
                    $row.append($('<td>').html(
                        `<span class="percent-value ${getCTRColor(ctr)}">${ctr.toFixed(2)}%</span>`
                    )); // CTR L30
                    $row.append($('<td>').text(adSpendL30.toFixed(2))); // SPEND L30
                    $row.append($('<td>').text(adSalesL30.toFixed(2))); // AD SALES L30
                    $row.append($('<td>').text(Average_cost_per_click.toFixed(2))); // CPC L1
                    $row.append($('<td>').text(adSoldL30)); // AD SOLD L30
                    $row.append($('<td>').html(
                        acosValue !== null
                            ? `<span class="percent-value ${getACOSColor(acosValue)}">${acosValue.toFixed(2)}%</span>`
                            : '&nbsp;'
                    )); // ACOS L30
                    $row.append($('<td>').html(
                        `<span class="percent-value ${getCVRColor(cvr)}">${cvr.toFixed(2)}%</span>`
                    )); // CVR L30
                    $row.append($('<td>').text(dailyBudget.toFixed(2))); // BGT CRNT

                    $tbody.append($row);
                });

                updatePaginationInfo();
                $('#visible-rows').text(`Showing all ${filteredData.length} rows`);
                initTooltips();
            }


            function initRAEditHandlers() {
                $(document).on('click', '.edit-icon', function (e) {
                    e.stopPropagation();
                    const $icon = $(this);
                    const $checkbox = $icon.siblings('.ra-checkbox');
                    const $row = $checkbox.closest('tr');
                    const rowData = filteredData.find(item => item['SL No.'] == $row.find('td:eq(0)')
                        .text());

                    if ($icon.hasClass('fa-pen')) {
                        // Enter edit mode
                        $checkbox.prop('disabled', false)
                            .data('original-value', $checkbox.is(':checked'));
                        $icon.removeClass('fa-pen text-primary')
                            .addClass('fa-save text-success')
                            .attr('title', 'Save Changes');
                    } else {
                        // Prepare data for saveChanges
                        const $cell = $checkbox.closest('.ra-cell');
                        const slNo = $row.find('td:eq(0)').text();
                        const title = "R&A";
                        const updatedValue = $checkbox.is(':checked') ? "true" : "false";

                        // Show saving indicator
                        $icon.html('<i class="fas fa-spinner fa-spin"></i>');

                        saveChanges(
                            $cell,
                            title,
                            slNo,
                            false, // isHyperlink
                            updatedValue,
                            true // isCheckbox
                        );

                        // Immediately disable checkbox after save
                        $checkbox.prop('disabled', true);
                        $icon.removeClass('fa-save text-success')
                            .addClass('fa-pen text-primary');
                    }
                });

                // Handle direct checkbox changes (for keyboard accessibility)
                $(document).on('change', '.ra-checkbox:not(:disabled)', function (e) {
                    e.stopPropagation();
                    $(this).siblings('.edit-icon').trigger('click');
                });
            }

            function initNREditHandlers() {
                $(document).off('click', '.nr-edit-container .nr-edit-icon, .nr-edit-container .fa-save');
                $(document).on('click', '.nr-edit-container .nr-edit-icon, .nr-edit-container .fa-save', function (
                    e) {
                    e.stopPropagation();
                    const $icon = $(this);
                    const $checkbox = $icon.siblings('.nr-checkbox');
                    const $row = $checkbox.closest('tr');
                    const rowData = filteredData.find(item => item['SL No.'] == $row.find('td:eq(0)')
                        .text());

                    if ($icon.hasClass('fa-pen')) {
                        // Enter edit mode
                        $checkbox.prop('disabled', false)
                            .data('original-value', $checkbox.is(':checked'));
                        $icon.removeClass('fa-pen text-primary')
                            .addClass('fa-save text-success')
                            .attr('title', 'Save Changes');
                    } else if ($icon.hasClass('fa-save')) {
                        // Save
                        const $cell = $checkbox.closest('td');
                        const slNo = $row.find('td:eq(0)').text();
                        const title = "NR";
                        const updatedValue = $checkbox.is(':checked') ? "true" : "false";

                        // Show saving indicator
                        $icon.removeClass('fa-save text-success')
                            .addClass('fa-spinner fa-spin text-primary')
                            .attr('title', 'Saving...');

                        saveChanges(
                            $cell,
                            title,
                            slNo,
                            false, // isHyperlink
                            updatedValue,
                            true // isCheckbox
                        );

                        // Immediately disable checkbox after save
                        $checkbox.prop('disabled', true);
                        $icon.removeClass('fa-spinner fa-spin text-primary')
                            .addClass('fa-pen text-primary')
                            .attr('title', 'Edit NR');
                    }
                });

                // Allow clicking the checkbox directly to enter edit mode (like R&A)
                $(document).off('click', '.nr-checkbox:disabled');
                $(document).on('click', '.nr-checkbox:disabled', function (e) {
                    e.stopPropagation();
                    $(this).siblings('.nr-edit-icon').trigger('click');
                });

            }

            window.openModal = function (selectedItem, type) {
                try {
                    // Handle both string and object inputs
                    let itemData;
                    if (typeof selectedItem === 'string') {
                        try {
                            itemData = JSON.parse(selectedItem);
                        } catch (e) {
                            try {
                                itemData = JSON.parse(decodeURIComponent(selectedItem));
                            } catch (e2) {
                                console.error("Error parsing item data:", e2);
                                showNotification('danger', 'Failed to open details view. Data format error.');
                                return;
                            }
                        }
                    } else {
                        itemData = selectedItem;
                    }


                    if (!itemData || typeof itemData !== 'object') {
                        console.error("Invalid item data:", itemData);
                        showNotification('danger', 'Failed to open details view. Invalid data.');
                        return;
                    }

                    const itemId = itemData['SL No.'] || 'unknown';
                    const modalId = `modal-${itemId}-${type.replace(/\s+/g, '-').toLowerCase()}`;

                    // Check cache first - use the cached data if available
                    const cachedData = amazonDataCache.get(itemId);
                    const dataToUse = cachedData || itemData;

                    // Store the data in cache if it wasn't already
                    if (!cachedData) {
                        amazonDataCache.set(itemId, itemData);
                    }

                    // Check if this modal already exists
                    const existingModal = ModalSystem.modals.find(m => m.id === modalId);
                    if (existingModal) {
                        // Just bring it to front if it exists
                        ModalSystem.bringToFront(existingModal);
                        return;
                    }

                    // Special handling for Scouth products view
                    if (type.toLowerCase() === 'scouth products view') {
                        return openScouthProductsView(selectedItem, modalId);
                    }

                    // Create modal content based on type
                    const mainContainer = document.createElement('div');
                    mainContainer.className = 'd-flex flex-nowrap align-items-start gap-3 p-3 overflow-auto';

                    // Common fields for all modal types
                    const commonFields = [{
                        title: 'Parent',
                        content: dataToUse['Parent']
                    },
                    {
                        title: 'SKU',
                        content: dataToUse['(Child) sku']
                    }
                    ];

                    // Fields specific to each modal type
                    let fieldsToDisplay = [];
                    switch (type.toLowerCase()) {
                        case 'conversion view':
                            fieldsToDisplay = [{
                                title: 'Sess30',
                                content: selectedItem['Sess30']
                            },
                            {
                                title: 'SCVR',
                                content: selectedItem['SCVR']
                            },
                            {
                                title: 'KwClks60',
                                content: selectedItem['KwClks60']
                            },
                            {
                                title: 'KwClks30',
                                content: selectedItem['KwClks30']
                            },
                            {
                                title: 'KwSld60',
                                content: selectedItem['KwSld60']
                            },
                            {
                                title: 'KwSld30',
                                content: selectedItem['KwSld30']
                            },
                            {
                                title: 'KwCvr60',
                                content: selectedItem['KwCvr60']
                            },
                            {
                                title: 'KwCvr30',
                                content: selectedItem['KwCvr30']
                            },
                            {
                                title: 'PtClks60',
                                content: selectedItem['PtClks60']
                            },
                            {
                                title: 'PtClks30',
                                content: selectedItem['PtClks30']
                            },
                            {
                                title: 'PtSld60',
                                content: selectedItem['PtSld60']
                            },
                            {
                                title: 'PtSld30',
                                content: selectedItem['PtSld30']
                            },
                            {
                                title: 'PtCvr60',
                                content: selectedItem['PtCvr60']
                            },
                            {
                                title: 'PtCvr30',
                                content: selectedItem['PtCvr30']
                            },
                            {
                                title: 'DspClks60',
                                content: selectedItem['DspClks60']
                            },
                            {
                                title: 'DspClks30',
                                content: selectedItem['DspClks30']
                            },
                            {
                                title: 'DspSld60',
                                content: selectedItem['DspSld60']
                            },
                            {
                                title: 'DspSld30',
                                content: selectedItem['DspSld30']
                            },
                            {
                                title: 'DspCvr60',
                                content: selectedItem['DspCvr60']
                            },
                            {
                                title: 'DspCvr30',
                                content: selectedItem['DspCvr30']
                            },
                            {
                                title: 'HdClks60',
                                content: selectedItem['HdClks60']
                            },
                            {
                                title: 'HdClks30',
                                content: selectedItem['HdClks30']
                            },
                            {
                                title: 'HdSld60',
                                content: selectedItem['HdSld60']
                            },
                            {
                                title: 'HdSld30',
                                content: selectedItem['HdSld30']
                            },
                            {
                                title: 'HdCvr60',
                                content: selectedItem['HdCvr60']
                            },
                            {
                                title: 'HdCvr30',
                                content: selectedItem['HdCvr30']
                            },
                            {
                                title: 'TClks60',
                                content: selectedItem['TClks60']
                            },
                            {
                                title: 'TClks30',
                                content: selectedItem['TClks30']
                            },
                            {
                                title: 'TSld60',
                                content: selectedItem['TSld60']
                            },
                            {
                                title: 'TSld30',
                                content: selectedItem['TSld30']
                            },
                            {
                                title: 'TCvr60',
                                content: selectedItem['TCvr60']
                            },
                            {
                                title: 'TCvr30',
                                content: selectedItem['TCvr30']
                            }
                            ];
                            break;
                        case 'visibility view':
                            fieldsToDisplay = [{
                                title: 'Sess30',
                                content: selectedItem['Sess30']
                            },
                            {
                                title: 'Sessl60',
                                content: selectedItem['sessions_l60']
                            },
                            {
                                title: 'KwImp60',
                                content: selectedItem['KwImp60']
                            },
                            {
                                title: 'KwImp30',
                                content: selectedItem['KwImp30']
                            },
                            {
                                title: 'KwClks60',
                                content: selectedItem['KwClks60']
                            },
                            {
                                title: 'KwClks30',
                                content: selectedItem['KwClks30']
                            },
                            {
                                title: 'KwCtr60',
                                content: selectedItem['KwCtr60']
                            },
                            {
                                title: 'KwCtr30',
                                content: selectedItem['KwCtr30']
                            },
                            {
                                title: 'PtImp60',
                                content: selectedItem['PtImp60']
                            },
                            {
                                title: 'PtImp30',
                                content: selectedItem['PtImp30']
                            },
                            {
                                title: 'PtClks60',
                                content: selectedItem['PtClks60']
                            },
                            {
                                title: 'PtClks30',
                                content: selectedItem['PtClks30']
                            },
                            {
                                title: 'PtCtr60',
                                content: selectedItem['PtCtr60']
                            },
                            {
                                title: 'PtCtr30',
                                content: selectedItem['PtCtr30']
                            },
                            {
                                title: 'DspImp60',
                                content: selectedItem['DspImp60']
                            },
                            {
                                title: 'DspImp30',
                                content: selectedItem['DspImp30']
                            },
                            {
                                title: 'DspClks60',
                                content: selectedItem['DspClks60']
                            },
                            {
                                title: 'DspClks30',
                                content: selectedItem['DspClks30']
                            },
                            {
                                title: 'DspCtr60',
                                content: selectedItem['DspCtr60']
                            },
                            {
                                title: 'DspCtr30',
                                content: selectedItem['DspCtr30']
                            },
                            {
                                title: 'HdImp60',
                                content: selectedItem['HdImp60']
                            },
                            {
                                title: 'HdImp30',
                                content: selectedItem['HdImp30']
                            },
                            {
                                title: 'HdClks60',
                                content: selectedItem['HdClks60']
                            },
                            {
                                title: 'HdClks30',
                                content: selectedItem['HdClks30']
                            },
                            {
                                title: 'HdCtr60',
                                content: selectedItem['HdCtr60']
                            },
                            {
                                title: 'HdCtr30',
                                content: selectedItem['HdCtr30']
                            },
                            {
                                title: 'TImp60',
                                content: selectedItem['TImp60']
                            },
                            {
                                title: 'TImp30',
                                content: selectedItem['TImp30']
                            },
                            {
                                title: 'TClks60',
                                content: selectedItem['TClks60']
                            },
                            {
                                title: 'TClks30',
                                content: selectedItem['TClks30']
                            },
                            {
                                title: 'TCtr60',
                                content: selectedItem['TCtr60']
                            },
                            {
                                title: 'TCtr30',
                                content: selectedItem['TCtr30']
                            }
                            ];
                            break;
                        case 'price view':
                            fieldsToDisplay = [{
                                title: 'MSRP',
                                content: selectedItem['MSRP']
                            },
                            {
                                title: 'price',
                                content: selectedItem['price']
                            },
                            {
                                title: 'PFT_percentage',
                                content: selectedItem['PFT_percentage']
                            },
                            {
                                title: 'TPFT',
                                content: selectedItem['TPFT']
                            },
                            {
                                title: 'ROI_percentage',
                                content: selectedItem['ROI_percentage']
                            },
                            {
                                title: 'SPRICE',
                                content: dataToUse['SPRICE']
                            },
                            {
                                title: 'Spft%',
                                content: (function () {
                                    const spftValue = dataToUse['Spft%'];

                                    // Check if value exists and is a valid number
                                    if (typeof spftValue !== 'number' || isNaN(spftValue)) {
                                        return '0 %'; // Fallback for invalid data
                                    }

                                    // Handle zero case
                                    if (spftValue === 0) {
                                        return '0 %';
                                    }

                                    // Format the number (ensure it's a number before using .toFixed)
                                    const absValue = Math.abs(spftValue);
                                    const formattedValue = (absValue < 100) ?
                                        (spftValue * 100).toFixed(2) :
                                        spftValue.toFixed(2);

                                    return formattedValue + ' %';
                                })()
                            },
                            {
                                title: 'Tannishtha done',
                                content: dataToUse['Tannishtha done']
                            },
                            {
                                title: 'LMP 1',
                                content: dataToUse['LMP 1']
                            },
                            {
                                title: 'LINK 1',
                                content: dataToUse['LINK 1']
                            },
                            {
                                title: 'LMP 2',
                                content: dataToUse['LMP 2']
                            },
                            {
                                title: 'LINK 2',
                                content: dataToUse['LINK 2']
                            },
                            {
                                title: 'LMP3',
                                content: dataToUse['LMP3']
                            },
                            {
                                title: 'LINK 3',
                                content: dataToUse['LINK 3']
                            },
                            {
                                title: 'LMP 4',
                                content: dataToUse['LMP 4']
                            },
                            {
                                title: 'LINK 4',
                                content: dataToUse['LINK 4']
                            },
                            {
                                title: 'LMP 5',
                                content: dataToUse['LMP 5']
                            },
                            {
                                title: 'LINK 5',
                                content: dataToUse['LINK 5']
                            }
                            ];
                            break;
                        case 'advertisement view':
                            fieldsToDisplay = [{
                                title: 'KwImp60',
                                content: selectedItem['KwImp60']
                            },
                            {
                                title: 'KwImp30',
                                content: selectedItem['KwImp30']
                            },
                            {
                                title: 'KwClks60',
                                content: selectedItem['KwClks60']
                            },
                            {
                                title: 'KwClks30',
                                content: selectedItem['KwClks30']
                            },
                            {
                                title: 'KwCtr60',
                                content: selectedItem['KwCtr60']
                            },
                            {
                                title: 'KwCtr30',
                                content: selectedItem['KwCtr30']
                            },
                            {
                                title: 'KwSpnd60',
                                content: selectedItem['KwSpnd60']
                            },
                            {
                                title: 'KwSpnd30',
                                content: selectedItem['KwSpnd30']
                            },
                            {
                                title: 'KwSls60',
                                content: selectedItem['KwSls60']
                            },
                            {
                                title: 'KwSls30',
                                content: selectedItem['KwSls30']
                            },
                            {
                                title: 'KwSld60',
                                content: selectedItem['KwSld60']
                            },
                            {
                                title: 'KwSld30',
                                content: selectedItem['KwSld30']
                            },
                            {
                                title: 'KwAcos60',
                                content: selectedItem['KwAcos60']
                            },
                            {
                                title: 'KwAcos30',
                                content: selectedItem['KwAcos30']
                            },
                            {
                                title: 'KwCvr60',
                                content: selectedItem['KwCvr60']
                            },
                            {
                                title: 'KwCvr30',
                                content: selectedItem['KwCvr30']
                            },

                            {
                                title: 'PtImp60',
                                content: selectedItem['PtImp60']
                            },
                            {
                                title: 'PtImp30',
                                content: selectedItem['PtImp30']
                            },
                            {
                                title: 'PtClks60',
                                content: selectedItem['PtClks60']
                            },
                            {
                                title: 'PtClks30',
                                content: selectedItem['PtClks30']
                            },
                            {
                                title: 'PtCtr60',
                                content: selectedItem['PtCtr60']
                            },
                            {
                                title: 'PtCtr30',
                                content: selectedItem['PtCtr30']
                            },
                            {
                                title: 'PtSpnd60',
                                content: selectedItem['PtSpnd60']
                            },
                            {
                                title: 'PtSpnd30',
                                content: selectedItem['PtSpnd30']
                            },
                            {
                                title: 'PtSls60',
                                content: selectedItem['PtSls60']
                            },
                            {
                                title: 'PtSls30',
                                content: selectedItem['PtSls30']
                            },
                            {
                                title: 'PtSld60',
                                content: selectedItem['PtSld60']
                            },
                            {
                                title: 'PtSld30',
                                content: selectedItem['PtSld30']
                            },
                            {
                                title: 'PtAcos60',
                                content: selectedItem['PtAcos60']
                            },
                            {
                                title: 'PtAcos30',
                                content: selectedItem['PtAcos30']
                            },
                            {
                                title: 'PtCvr60',
                                content: selectedItem['PtCvr60']
                            },
                            {
                                title: 'PtCvr30',
                                content: selectedItem['PtCvr30']
                            },

                            {
                                title: 'DspImp60',
                                content: selectedItem['DspImp60']
                            },
                            {
                                title: 'DspImp30',
                                content: selectedItem['DspImp30']
                            },
                            {
                                title: 'DspClks60',
                                content: selectedItem['DspClks60']
                            },
                            {
                                title: 'DspClks30',
                                content: selectedItem['DspClks30']
                            },
                            {
                                title: 'DspCtr60',
                                content: selectedItem['DspCtr60']
                            },
                            {
                                title: 'DspCtr30',
                                content: selectedItem['DspCtr30']
                            },
                            {
                                title: 'DspSpnd60',
                                content: selectedItem['DspSpnd60']
                            },
                            {
                                title: 'DspSpnd30',
                                content: selectedItem['DspSpnd30']
                            },
                            {
                                title: 'DspSls60',
                                content: selectedItem['DspSls60']
                            },
                            {
                                title: 'DspSls30',
                                content: selectedItem['DspSls30']
                            },
                            {
                                title: 'DspSld60',
                                content: selectedItem['DspSld60']
                            },
                            {
                                title: 'DspSld30',
                                content: selectedItem['DspSld30']
                            },
                            {
                                title: 'DspAcos60',
                                content: selectedItem['DspAcos60']
                            },
                            {
                                title: 'DspAcos30',
                                content: selectedItem['DspAcos30']
                            },
                            {
                                title: 'DspCvr60',
                                content: selectedItem['DspCvr60']
                            },
                            {
                                title: 'DspCvr30',
                                content: selectedItem['DspCvr30']
                            },

                            {
                                title: 'HdImp60',
                                content: selectedItem['HdImp60']
                            },
                            {
                                title: 'HdImp30',
                                content: selectedItem['HdImp30']
                            },
                            {
                                title: 'HdClks60',
                                content: selectedItem['HdClks60']
                            },
                            {
                                title: 'HdClks30',
                                content: selectedItem['HdClks30']
                            },
                            {
                                title: 'HdCtr60',
                                content: selectedItem['HdCtr60']
                            },
                            {
                                title: 'HdCtr30',
                                content: selectedItem['HdCtr30']
                            },
                            {
                                title: 'HdSpnd60',
                                content: selectedItem['HdSpnd60']
                            },
                            {
                                title: 'HdSpnd30',
                                content: selectedItem['HdSpnd30']
                            },
                            {
                                title: 'HdSls60',
                                content: selectedItem['HdSls60']
                            },
                            {
                                title: 'HdSls30',
                                content: selectedItem['HdSls30']
                            },
                            {
                                title: 'HdSld60',
                                content: selectedItem['HdSld60']
                            },
                            {
                                title: 'HdSld30',
                                content: selectedItem['HdSld30']
                            },
                            {
                                title: 'HdAcos60',
                                content: selectedItem['HdAcos60']
                            },
                            {
                                title: 'HdAcos30',
                                content: selectedItem['HdAcos30']
                            },
                            {
                                title: 'HdCvr60',
                                content: selectedItem['HdCvr60']
                            },
                            {
                                title: 'HdCvr30',
                                content: selectedItem['HdCvr30']
                            },

                            {
                                title: 'TImp60',
                                content: selectedItem['TImp60']
                            },
                            {
                                title: 'TImp30',
                                content: selectedItem['TImp30']
                            },
                            {
                                title: 'TClks60',
                                content: selectedItem['TClks60']
                            },
                            {
                                title: 'TClks30',
                                content: selectedItem['TClks30']
                            },
                            {
                                title: 'TCtr60',
                                content: selectedItem['TCtr60']
                            },
                            {
                                title: 'TCtr30',
                                content: selectedItem['TCtr30']
                            },
                            {
                                title: 'TSpnd60',
                                content: selectedItem['TSpnd60']
                            },
                            {
                                title: 'TSpnd30',
                                content: selectedItem['TSpnd30']
                            },
                            {
                                title: 'TSls60',
                                content: selectedItem['TSls60']
                            },
                            {
                                title: 'TSls30',
                                content: selectedItem['TSls30']
                            },
                            {
                                title: 'TSld60',
                                content: selectedItem['TSld60']
                            },
                            {
                                title: 'TSld30',
                                content: selectedItem['TSld30']
                            },
                            {
                                title: 'TAcos60',
                                content: selectedItem['TAcos60']
                            },
                            {
                                title: 'TAcos30',
                                content: selectedItem['TAcos30']
                            },
                            {
                                title: 'TCvr60',
                                content: selectedItem['TCvr60']
                            },
                            {
                                title: 'TCvr30',
                                content: selectedItem['TCvr30']
                            }
                            ];
                            break;
                        case 'wmpnm view':
                            fieldsToDisplay = [{
                                title: 'HIDE',
                                content: dataToUse['HIDE'],
                                isCheckbox: true
                            },
                            {
                                title: 'LISTING STATUS',
                                isSectionHeader: true,
                                children: [{
                                    title: 'LISTED',
                                    content: dataToUse['LISTED'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'LIVE / ACTIVE',
                                    content: dataToUse['LIVE / ACTIVE'],
                                    isCheckbox: true
                                }
                                ]
                            },
                            {
                                title: '0 VISIBILITY ISSUE',
                                isSectionHeader: true,
                                children: [{
                                    title: 'VISIBILITY ISSUE',
                                    content: dataToUse['VISIBILITY ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'INV SYNCED',
                                    content: dataToUse['INV SYNCED'],
                                    isCheckbox: true
                                }, {
                                    title: 'RIGHT CATEGORY',
                                    content: dataToUse['RIGHT CATEGORY'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'INCOMPLETE LISTING',
                                    content: dataToUse['INCOMPLETE LISTING'],
                                    isCheckbox: true
                                }, {
                                    title: 'BUYBOX ISSUE',
                                    content: dataToUse['BUYBOX ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'SEO  (KW RICH) ISSUE',
                                    content: dataToUse['SEO  (KW RICH) ISSUE'],
                                    isCheckbox: true
                                }, {
                                    title: 'TITLE ISSUEAD ISSUE',
                                    content: dataToUse['TITLE ISSUEAD ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'AD ISSUE',
                                    content: dataToUse['AD ISSUE'],
                                    isCheckbox: true
                                },
                                ]
                            },
                            {
                                title: 'LOW VISIBILITY (1-300 clicks)',
                                isSectionHeader: true,
                                children: [{
                                    title: 'SEO  (KW RICH) ISSUE',
                                    content: dataToUse['SEO  (KW RICH) ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'TITLE ISSUE',
                                    content: dataToUse['TITLE ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'BP ISSUE',
                                    content: dataToUse['TBP ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'DESCR ISSUE',
                                    content: dataToUse['DESCR ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'SPECS ISSUE',
                                    content: dataToUse['SPECS ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'IMG ISSUE',
                                    content: dataToUse['IMG ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'AD ISSUE',
                                    content: dataToUse['AD ISSUE'],
                                    isCheckbox: true
                                }
                                ]
                            },
                            {
                                title: 'CTR ISSUE (impressions but no clicks)',
                                isSectionHeader: true,
                                children: [{
                                    title: 'CATEGORY ISSUE',
                                    content: dataToUse['CATEGORY ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'TITILE ISSUE',
                                    content: dataToUse['TITILE ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'MAIN IMAGE ISSUE',
                                    content: dataToUse['MAIN IMAGE ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'PRICE ISSUE',
                                    content: dataToUse['PRICE ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'REVIEW ISSUE',
                                    content: dataToUse['REVIEW ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'WRONG KW IN LISTING',
                                    content: dataToUse['WRONG KW IN LISTING'],
                                    isCheckbox: true
                                }
                                ]
                            },
                            {
                                title: 'CVR ISSUE',
                                isSectionHeader: true,
                                children: [{
                                    title: 'CVR ISSUE',
                                    content: dataToUse['CVR ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'PRICE ISSUE',
                                    content: dataToUse['PRICE ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'REV ISSUE',
                                    content: dataToUse['REV ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'IMAGE ISSUE',
                                    content: dataToUse['IMAGE ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'VID ISSUE',
                                    content: dataToUse['VID ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'BP ISSUE',
                                    content: dataToUse['BP ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'DESCR ISSUE',
                                    content: dataToUse['DESCR ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'USP HIGHLIGHT ISSUE',
                                    content: dataToUse['USP HIGHLIGHT ISSUE'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'SPECS ISSUES',
                                    content: dataToUse['SPECS ISSUES'],
                                    isCheckbox: true
                                },
                                {
                                    title: 'MISMATCH ISSUE',
                                    content: dataToUse['MISMATCH ISSUE'],
                                    isCheckbox: true
                                }
                                ]
                            },
                            {
                                title: 'NOTES',
                                content: dataToUse['NOTES']
                            },
                            {
                                title: 'ACTION',
                                content: dataToUse['ACTION']
                            },
                            {
                                title: 'ACTION',
                                content: dataToUse['ACTION']
                            },
                            ];
                            break;
                        default:
                            fieldsToDisplay = commonFields;
                    }

                    // Combine common fields with type-specific fields
                    fieldsToDisplay = [...commonFields, ...fieldsToDisplay];

                    // Create cards for each field
                    fieldsToDisplay.forEach(field => {
                        if (field.isSectionHeader) {
                            // Create section container
                            const sectionContainer = document.createElement('div');
                            sectionContainer.className = 'd-flex flex-column';

                            // Section header
                            const header = document.createElement('div');
                            header.className = 'fw-bold text-nowrap';
                            header.style.cssText = `
                                                                            padding-left: 8px;
                                                                            margin-bottom: 3px;
                                                                            color: rgb(73, 80, 87);
                                                                            position: relative;
                                                                            z-index: 1;
                                                                        `;
                            header.textContent = field.title;
                            sectionContainer.appendChild(header);

                            // Cards row
                            const cardsRow = document.createElement('div');
                            cardsRow.className = 'd-flex flex-nowrap gap-2';
                            cardsRow.style.marginTop = '2px';

                            // Create cards for each child field
                            field.children.forEach(childField => {
                                const card = createFieldCard(childField, dataToUse, type,
                                    itemId);
                                cardsRow.appendChild(card);
                            });

                            sectionContainer.appendChild(cardsRow);
                            mainContainer.appendChild(sectionContainer);
                        } else {
                            // Create standalone card
                            const card = createFieldCard(field, dataToUse, type, itemId);
                            mainContainer.appendChild(card);
                        }
                    });

                    // Create modal with the content
                    const modal = ModalSystem.createModal(
                        modalId,
                        `${type.charAt(0).toUpperCase() + type.slice(1)} Details`,
                        mainContainer.outerHTML
                    );

                    // Show the modal
                    ModalSystem.showModal(modalId);

                    // Setup edit handlers after modal is shown
                    setTimeout(() => {
                        setupEditHandlers(modalId);
                    }, 100);
                } catch (error) {
                    console.error("Error in openModal:", error);
                    showNotification('danger', 'Failed to open details view. Please try again.');
                }
            };

            // New function to handle Scouth products view specifically
            function openScouthProductsView(data, modalId) {
                if (!data.scout_data || !data.scout_data.all_data) {
                    const modal = ModalSystem.createModal(
                        modalId,
                        'Scouth Products View Details',
                        '<div class="alert alert-warning">No scout data available</div>'
                    );
                    ModalSystem.showModal(modalId);
                    return;
                }

                // Sort products by price (lowest first)
                const sortedProducts = [...data.scout_data.all_data].sort((a, b) => {
                    const priceA = parseFloat(a.price) || Infinity;
                    const priceB = parseFloat(b.price) || Infinity;
                    return priceA - priceB;
                });

                // Create header with Parent and SKU
                const header = document.createElement('div');
                header.className = 'scouth-header';
                header.innerHTML = `
                                                                <div class="scouth-header-item">
                                                                    <div class="scouth-product-label">Parent</div>
                                                                    <div class="scouth-product-value">${data.Parent || 'N/A'}</div>
                                                                </div>
                                                                <div class="scouth-header-item">
                                                                    <div class="scouth-product-label">SKU</div>
                                                                    <div class="scouth-product-value">${data['(Child) sku'] || 'N/A'}</div>
                                                                </div>
                                                            `;

                // Create table wrapper
                const tableWrapper = document.createElement('div');
                tableWrapper.className = 'scouth-table-wrapper';
                tableWrapper.style.height = '425px';
                tableWrapper.style.overflowY = 'auto';

                // Create table header
                const tableHeader = document.createElement('div');
                tableHeader.className = 'scouth-table-header';
                tableHeader.style.position = 'sticky';
                tableHeader.style.top = '0';
                tableHeader.style.backgroundColor = '#fff';
                tableHeader.style.zIndex = '10';
                tableHeader.innerHTML = `
                                                                <div class="scouth-table-cell">ID</div>
                                                                <div class="scouth-table-cell">Price</div>
                                                                <div class="scouth-table-cell">Category</div>
                                                                <div class="scouth-table-cell">Dimensions</div>
                                                                <div class="scouth-table-cell">Image</div>
                                                                <div class="scouth-table-cell">Quality Score</div>
                                                                <div class="scouth-table-cell">Parent ASIN</div>
                                                                <div class="scouth-table-cell">Product Rank</div>
                                                                <div class="scouth-table-cell">Rating</div>
                                                                <div class="scouth-table-cell">Reviews</div>
                                                                <div class="scouth-table-cell">Weight</div>
                                                            `;

                // Create table body
                const tableBody = document.createElement('div');
                tableBody.className = 'scouth-table-body';

                // Add CSS for image thumbnails
                const style = document.createElement('style');
                style.textContent = `
                                                                .scouth-image-link {
                                                                    display: inline-block;
                                                                }
                                                                .scouth-image-thumbnail {
                                                                    width: 60px;
                                                                    height: 60px;
                                                                    border-radius: 50%;
                                                                    object-fit: cover;
                                                                    cursor: pointer;
                                                                    border: 2px solid #ddd;
                                                                    transition: transform 0.2s;
                                                                }
                                                                .scouth-image-thumbnail:hover {
                                                                    transform: scale(1.1);
                                                                    border-color: #aaa;
                                                                }
                                                            `;
                document.head.appendChild(style);

                // Add product rows
                sortedProducts.forEach(product => {
                    const row = document.createElement('div');
                    row.className = 'scouth-table-row';

                    let imageCellContent = 'N/A';
                    if (product.image_url) {
                        const link = document.createElement('a');
                        link.className = 'scouth-image-link';
                        link.href = product.image_url;
                        link.target = '_blank';
                        link.rel = 'noopener noreferrer';

                        const thumbnail = document.createElement('img');
                        thumbnail.className = 'scouth-image-thumbnail';
                        thumbnail.src = product.image_url;
                        thumbnail.alt = 'Product image';

                        link.appendChild(thumbnail);
                        imageCellContent = link.outerHTML;
                    }

                    row.innerHTML = `
                                                                    <div class="scouth-table-cell">${product.id || 'N/A'}</div>
                                                                    <div class="scouth-table-cell">${product.price ? '$' + parseFloat(product.price).toFixed(2) : 'N/A'}</div>
                                                                    <div class="scouth-table-cell">${product.category || 'N/A'}</div>
                                                                    <div class="scouth-table-cell">${product.dimensions || 'N/A'}</div>
                                                                    <div class="scouth-table-cell">${imageCellContent}</div>
                                                                    <div class="scouth-table-cell">${product.listing_quality_score || 'N/A'}</div>
                                                                    <div class="scouth-table-cell">${product.parent_asin || 'N/A'}</div>
                                                                    <div class="scouth-table-cell">${product.product_rank || 'N/A'}</div>
                                                                    <div class="scouth-table-cell">${product.rating || 'N/A'}</div>
                                                                    <div class="scouth-table-cell">${product.reviews || 'N/A'}</div>
                                                                    <div class="scouth-table-cell">${product.weight || 'N/A'}</div>
                                                                `;
                    tableBody.appendChild(row);
                });

                // Assemble table
                tableWrapper.appendChild(tableHeader);
                tableWrapper.appendChild(tableBody);

                // Create main container
                const mainContainer = document.createElement('div');
                mainContainer.appendChild(header);
                mainContainer.appendChild(tableWrapper);

                // Create modal
                const modal = ModalSystem.createModal(
                    modalId,
                    'Scouth Products View Details (Sorted by Lowest Price)',
                    mainContainer.outerHTML
                );

                // Show the modal
                ModalSystem.showModal(modalId);
            }

            // Helper function to create a field card
            function createFieldCard(field, data, type, itemId) {
                const hyperlinkFields = ['LINK 1', 'LINK 2', 'LINK 3', 'LINK 4', 'LINK 5'];

                const editableFields = [
                    'SPRICE', 'Tannishtha done', 'LMP 1', 'LINK 1', 'LMP 2', 'LINK 2',
                    'LMP3', 'LINK 3', 'LMP 4', 'LINK 4', 'LMP 5', 'LINK 5',
                    'HIDE', 'LISTED', 'LIVE / ACTIVE', 'VISIBILITY ISSUE', 'INV SYNCED',
                    'RIGHT CATEGORY', 'INCOMPLETE LISTING', 'BUYBOX ISSUE', 'SEO  (KW RICH) ISSUE',
                    'TITLE ISSUEAD ISSUE', 'AD ISSUE', 'BP ISSUE', 'DESCR ISSUE', 'SPECS ISSUE',
                    'IMG ISSUE', 'CATEGORY ISSUE', 'MAIN IMAGE ISSUE', 'PRICE ISSUE',
                    'REVIEW ISSUE', 'WRONG KW IN LISTING', 'CVR ISSUE', 'REV ISSUE',
                    'IMAGE ISSUE', 'VID ISSUE', 'USP HIGHLIGHT ISSUE', 'SPECS ISSUES',
                    'MISMATCH ISSUE', 'NOTES', 'ACTION', 'TITLE ISSUE'
                ];

                const percentageFields = ['KwCtr60', 'KwCtr30', 'PtCtr60', 'PtCtr30', 'DspCtr60',
                    'DspCtr30',
                    'HdCtr60', 'HdCtr30', 'SCVR', 'KwCvr60', 'KwCvr30', 'PtCvr60', 'PtCvr30',
                    'DspCvr60', 'DspCvr30', 'HdCvr60', 'HdCvr30', 'TCvr60', 'TCvr30',
                    'KwAcos60', 'KwAcos30', 'PtAcos60', 'PtAcos30', 'DspAcos60', 'DspAcos30',
                    'HdAcos60', 'HdAcos30', 'TCtr60', 'TCtr30', 'TAcos60', 'TAcos30',
                    'Tacos60', 'Tacos30', 'PFT_percentage', 'TPFT', 'ROI_percentage'
                ];

                const getIndicatorColor = (fieldTitle, fieldValue) => {
                    // Handle both string percentages (like "15%") and raw numbers
                    let value;
                    if (typeof fieldValue === 'string' && fieldValue.includes('%')) {
                        value = parseFloat(fieldValue.replace('%', ''));
                    } else {
                        value = parseFloat(fieldValue);
                    }

                    // Handle NaN cases (invalid numbers)
                    if (isNaN(value)) {
                        return 'gray';
                    }

                    // Price view specific colors
                    if (type.toLowerCase() === 'price view') {
                        if (['PFT_percentage', 'TPFT'].includes(fieldTitle)) {
                            if (value < 10) return 'red';
                            if (value >= 10 && value < 15) return 'yellow';
                            if (value >= 15 && value < 20) return 'blue';
                            if (value >= 20 && value < 40) return 'green';
                            return 'pink'; // 40 and above
                        }
                        if (fieldTitle === 'ROI_percentage') {
                            if (value <= 50) return 'red';
                            if (value > 50 && value <= 75) return 'yellow';
                            if (value > 75 && value <= 100) return 'green';
                            return 'pink'; // Above 100
                        }
                        if (fieldTitle === 'Spft%') {
                            // Convert to percentage for easier comparison
                            const percentValue = Math.abs(value) < 100 ? value * 100 : value;
                            if (percentValue < 0) return 'red'; // Negative values (loss)
                            if (percentValue < 10) return 'red'; // Less than 10%
                            if (percentValue < 15) return 'yellow'; // 10-14.99%
                            if (percentValue < 20) return 'blue'; // 15-19.99%
                            if (percentValue < 40) return 'green'; // 20-39.99%
                            return 'pink'; // 40% and above
                        }
                    }

                    // Advertisement view specific colors
                    if (type.toLowerCase() === 'advertisement view') {
                        if (['KwAcos60', 'KwAcos30', 'PtAcos60', 'PtAcos30', 'DspAcos60',
                            'DspAcos30', 'TAcos60', 'TAcos30'
                        ].includes(fieldTitle)) {
                            if (value === 0) return 'red';
                            if (value > 0.01 && value <= 7) return 'pink';
                            if (value > 7 && value <= 14) return 'green';
                            if (value > 14 && value <= 21) return 'blue';
                            if (value > 21 && value <= 28) return 'yellow';
                            if (value > 28) return 'red';
                        }
                        if (['KwCvr60', 'KwCvr30', 'PtCvr60', 'DspCvr60', 'PtCvr30',
                            'DspCvr30', 'HdAcos60', 'HdAcos30', 'HdCvr60', 'HdCvr30',
                            'TCvr60', 'TCvr30'
                        ].includes(fieldTitle)) {
                            if (value <= 7) return 'red';
                            if (value > 7 && value <= 13) return 'green';
                            return fieldTitle.includes('PtCvr') || fieldTitle.includes('DspCvr') ||
                                fieldTitle.includes('HdCvr') || fieldTitle.includes('TCvr') ? 'pink' : 'gray';
                        }
                    }

                    // Conversion view specific colors
                    if (type.toLowerCase() === 'conversion view') {
                        if (['Scvr', 'KwCvr60', 'KwCvr30', 'PtCvr60', 'PtCvr30',
                            'DspCvr60', 'DspCvr30', 'HdCvr60', 'HdCvr30',
                            'TCvr60', 'TCvr30'
                        ].includes(fieldTitle)) {
                            if (value <= 7) return 'red';
                            if (value > 7 && value <= 13) return 'green';
                            return 'pink';
                        }
                    }

                    // Default color for all other cases
                    return 'gray';
                };

                let content = field.content === null || field.content === undefined || field.content === '' ? ' ' :
                    field.content;
                const showStatusIndicator = statusIndicatorFields[type]?.includes(field.title) || false;
                const indicatorColor = showStatusIndicator ? getIndicatorColor(field.title, content) : '';
                const isHyperlink = hyperlinkFields.includes(field.title) || field.isHyperlink;
                const isCheckbox = field.isCheckbox || false;

                // Create card element
                const card = document.createElement('div');
                card.className =
                    `card flex-shrink-0 position-relative ${showStatusIndicator ? 'card-bg-' + indicatorColor : ''}`;
                card.style.cssText = `
                                                                min-width: 160px;
                                                                width: auto;
                                                                max-width: 100%;
                                                                margin-top: 0px;
                                                                border-radius: 8px;
                                                                box-shadow: rgba(0, 0, 0, 0.1) 0px 2px 4px;
                                                            `;

                // Add hidden SL No input
                const slInput = document.createElement('input');
                slInput.type = 'hidden';
                slInput.className = 'hidden-sl-no';
                slInput.value = itemId;
                card.appendChild(slInput);

                // Add hidden field name input
                const fieldInput = document.createElement('input');
                fieldInput.type = 'hidden';
                fieldInput.className = 'hidden-field-name';
                fieldInput.value = field.title;
                card.appendChild(fieldInput);

                if (percentageFields.includes(field.title) && typeof content === 'number') {
                    // Skip multiplication for PFT_percentage and ROI_percentage
                    if (!['PFT_percentage', 'ROI_percentage'].includes(field.title)) {
                        content = `${(content * 100).toFixed(2)}%`;
                    } else {
                        content = `${content.toFixed(2)}%`;
                    }
                }

                // Add edit icon if field is editable
                if (editableFields.includes(field.title)) {
                    const editIcon = document.createElement('div');
                    editIcon.className = 'position-absolute top-0 end-0 p-2 edit-icon';
                    editIcon.style.cssText = 'cursor:pointer; z-index: 1;';
                    editIcon.innerHTML = '<i class="fas fa-pen text-primary"></i>';
                    card.appendChild(editIcon);
                }

                const cardBody = document.createElement('div');
                cardBody.className = 'card-body';
                cardBody.style.padding = '0.75rem';
                cardBody.style.position = 'relative';

                // Add card title
                const cardTitle = document.createElement('h6');
                cardTitle.className = 'card-title';
                cardTitle.style.cssText = `
                                                                font-size: 0.85rem;
                                                                margin-bottom: 0.5rem;
                                                                white-space: nowrap;
                                                                overflow: hidden;
                                                                text-overflow: ellipsis;
                                                                padding-right: 24px;
                                                            `;
                cardTitle.textContent = field.title;
                cardBody.appendChild(cardTitle);

                // Add card content
                const cardContent = document.createElement('p');
                cardContent.className = 'card-text editable-content';
                cardContent.setAttribute('data-is-hyperlink', isHyperlink);

                if (isCheckbox) {
                    // Create checkbox container
                    const checkboxContainer = document.createElement('div');
                    checkboxContainer.className = 'form-check form-switch';

                    // Create checkbox input
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'form-check-input';
                    checkbox.checked = content === true || content === 'true';
                    checkbox.disabled = true;

                    checkboxContainer.appendChild(checkbox);
                    cardContent.appendChild(checkboxContainer);
                } else if (isHyperlink) {
                    const link = document.createElement('a');
                    link.href = content;
                    link.target = '_blank';
                    link.rel = 'noopener noreferrer';
                    link.textContent = content.length > 30 ?
                        content.substring(0, 15) + '...' + content.slice(-10) :
                        content;
                    cardContent.appendChild(link);
                } else {
                    cardContent.textContent = content;
                }

                cardBody.appendChild(cardContent);
                card.appendChild(cardBody);

                return card;
            }

            function setupEditHandlers(modalId) {
                const modalElement = document.getElementById(modalId);
                if (!modalElement) return;

                $(modalElement).off('click', '.edit-icon, .save-icon');

                $(modalElement).on('click', '.edit-icon', function (e) {
                    e.stopPropagation();

                    const icon = $(this);
                    const card = icon.closest('.card');
                    const contentElement = card.find('.editable-content');
                    const checkbox = contentElement.find('.form-check-input');
                    const title = card.find('.card-title').text().trim();

                    // If it's a checkbox field, enable it and change to save icon
                    if (checkbox.length > 0) {
                        checkbox.prop('disabled', false);
                        icon.html('<i class="fas fa-check text-success"></i>')
                            .removeClass('edit-icon')
                            .addClass('save-icon');
                        return;
                    }

                    const isHyperlink = contentElement.data('is-hyperlink');
                    const slNo = card.find('.hidden-sl-no').val();

                    if (currentEditingElement && currentEditingElement.is(contentElement)) {
                        return;
                    }

                    if (isEditMode && currentEditingElement) {
                        exitEditMode(currentEditingElement);
                    }

                    let originalContent = contentElement.text().trim();
                    if (isHyperlink && contentElement.find('a').length) {
                        originalContent = contentElement.find('a').attr('href');
                    }

                    contentElement.data('original-content', originalContent)
                        .html(originalContent)
                        .attr('contenteditable', 'true')
                        .addClass('border border-primary')
                        .focus();

                    isEditMode = true;
                    currentEditingElement = contentElement;

                    icon.html('<i class="fas fa-check text-success"></i>')
                        .removeClass('edit-icon')
                        .addClass('save-icon');
                });

                // Save handler
                $(modalElement).on('click', '.save-icon', function (e) {
                    e.stopPropagation();
                    const icon = $(this);
                    const card = icon.closest('.card');
                    const contentElement = card.find('.editable-content');
                    const checkbox = contentElement.find('.form-check-input');
                    const title = card.find('.card-title').text().trim();
                    const slNo = card.find('.hidden-sl-no').val();
                    const isHyperlink = contentElement.data('is-hyperlink');

                    // Get the updated value
                    let updatedValue;
                    if (checkbox.length > 0) {
                        updatedValue = checkbox.prop('checked') ? "true" : "false";
                        checkbox.prop('disabled', true);
                    } else {
                        updatedValue = contentElement.text().trim();
                        if (isHyperlink && contentElement.find('a').length) {
                            updatedValue = contentElement.find('a').attr('href');
                        }
                    }

                    saveChanges(contentElement, title, slNo, isHyperlink, updatedValue, checkbox.length >
                        0);
                });
            }

            function exitEditMode(contentElement) {
                const isCheckbox = contentElement.find('.form-check-input').length > 0;

                if (isCheckbox) {
                    contentElement.find('.form-check-input').prop('disabled', true);
                } else {
                    const isHyperlink = contentElement.data('is-hyperlink');
                    const originalContent = contentElement.data('original-content');

                    if (originalContent) {
                        if (isHyperlink) {
                            const displayText = originalContent.length > 30 ?
                                originalContent.substring(0, 15) + '...' + originalContent.slice(-10) :
                                originalContent;
                            contentElement.html(
                                `<a href="${originalContent}" target="_blank" rel="noopener noreferrer">${displayText}</a>`
                            );
                        } else {
                            contentElement.text(originalContent);
                        }
                    }

                    contentElement.attr('contenteditable', 'false')
                        .removeClass('border border-primary');
                }

                const card = contentElement.closest('.card');
                card.find('.save-icon').html('<i class="fas fa-pen text-primary"></i>')
                    .removeClass('save-icon')
                    .addClass('edit-icon');

                isEditMode = false;
                currentEditingElement = null;
            }

            function saveChanges(contentElement, title, slNo, isHyperlink, updatedValue, isCheckbox, rowElement) {
                const card = contentElement.closest('.card') || contentElement.closest('tr');
                const itemId = card.find('.hidden-sl-no').val() || slNo;
                const saveIcon = card.find('.save-icon') || card.find('.edit-icon');

                // Prepare data for API call (only for the original field)
                const data = {
                    slNo: parseInt(itemId),
                    updates: {
                        [title]: updatedValue
                    }
                };

                // Show loading indicator
                if (saveIcon) {
                    saveIcon.html('<i class="fas fa-spinner fa-spin text-primary"></i>');
                }

                // 1. First update the cache immediately
                const cacheUpdateValue = isCheckbox ? (updatedValue === "true") : updatedValue;
                amazonDataCache.updateField(itemId, title, cacheUpdateValue);

                // 2. Update the filteredData array to reflect the change
                const index = filteredData.findIndex(item => item['SL No.'] == itemId);
                if (index !== -1) {
                    filteredData[index][title] = cacheUpdateValue;

                    // If this is an SPRICE update, calculate and update Spft% in cache using new formula
                    if (title === 'SPRICE' && filteredData[index].raw_data) {
                        const item = filteredData[index];
                        const price = parseFloat(item.price) || 0;
                        const SHIP = parseFloat(item.raw_data.SHIP) || 0;
                        const LP = parseFloat(item.raw_data.LP) || 0;
                        const SPRICE = parseFloat(updatedValue) || 0;

                        // Calculate Spft% using new formula: (SPRICE * price - SHIP - LP) / SPRICE
                        let Spft = 0;
                        if (SPRICE !== 0) {
                            Spft = (SPRICE * 0.71 - SHIP - LP) / SPRICE;

                        }

                        // Update Spft% in cache and local data
                        amazonDataCache.updateField(itemId, 'Spft%', Spft);
                        filteredData[index]['Spft%'] = Spft;
                        filteredData[index].raw_data['Spft%'] = Spft;
                    }

                    // If this is an R&A update, ensure the raw_data is also updated
                    if (title === 'R&A' && filteredData[index].raw_data) {
                        filteredData[index].raw_data[title] = cacheUpdateValue;
                    }
                }

                // 3. Update the UI immediately
                if (rowElement) {
                    // For table rows (R&A column)
                    const checkbox = rowElement.find('.ra-checkbox');
                    if (checkbox.length) {
                        checkbox.prop('checked', cacheUpdateValue);
                    }
                } else {
                    // For modal cards
                    if (isCheckbox) {
                        contentElement.find('.form-check-input').prop('checked', cacheUpdateValue);
                    } else if (isHyperlink) {
                        const displayText = cacheUpdateValue.length > 30 ?
                            cacheUpdateValue.substring(0, 15) + '...' + cacheUpdateValue.slice(-10) :
                            cacheUpdateValue;
                        contentElement.html(
                            `<a href="${cacheUpdateValue}" target="_blank" rel="noopener noreferrer">${displayText}</a>`
                        );
                    } else {
                        contentElement.text(cacheUpdateValue);
                    }
                }

                // 4. If we updated SPRICE, update the Spft% card in the modal if it's open
                if (title === 'SPRICE') {
                    const modalId = `modal-${itemId}-price-view`;
                    const modalElement = document.getElementById(modalId);
                    if (modalElement) {
                        // Find the Spft% card
                        const cards = modalElement.querySelectorAll('.card');
                        for (let card of cards) {
                            const cardTitle = card.querySelector('.card-title');
                            if (cardTitle && cardTitle.textContent.trim() === 'Spft%') {
                                const contentElement = card.querySelector('.card-text');
                                if (contentElement) {
                                    // Format Spft% for display
                                    const SpftValue = filteredData[index]['Spft%'] || 0;
                                    const absValue = Math.abs(SpftValue);
                                    let displayValue;

                                    if (absValue < 100) {
                                        displayValue = (SpftValue * 100).toFixed(2);
                                    } else {
                                        displayValue = SpftValue.toFixed(2);
                                    }

                                    contentElement.textContent = displayValue + ' %';
                                }
                                break;
                            }
                        }
                    }
                }

                // 5. Send the update to the server ONLY for the original field
                $.ajax({
                    method: 'POST',
                    url: window.location.origin + (window.location.pathname.includes('/public') ?
                        '/public' : '') + '/api/update-amazon-column',
                    data: JSON.stringify(data),
                    contentType: 'application/json',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function (response) {
                        // Update was already done in cache, just show success
                        if (saveIcon) {
                            saveIcon.html('<i class="fas fa-pen text-primary"></i>')
                                .removeClass('save-icon')
                                .addClass('edit-icon');
                        }

                        // Make the field uneditable again
                        if (!rowElement) {
                            if (isCheckbox) {
                                contentElement.find('.form-check-input').prop('disabled', true);
                            } else {
                                contentElement.attr('contenteditable', 'false')
                                    .removeClass('border border-primary');
                            }
                        }

                        showNotification('success', `${title} Updated Successfully`);

                        // If this was an R&A update from the table, ensure UI is consistent
                        if (rowElement) {
                            checkParentRAStatus();
                            renderTable();
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error('Update failed:', {
                            status: xhr.status,
                            error: error,
                            response: xhr.responseText
                        });

                        // Revert changes on error
                        const originalValue = contentElement.data('original-value');

                        // Revert cache
                        amazonDataCache.updateField(itemId, title, originalValue);

                        // Revert filteredData
                        if (index !== -1) {
                            filteredData[index][title] = originalValue;
                            if (title === 'R&A' && filteredData[index].raw_data) {
                                filteredData[index].raw_data[title] = originalValue;
                            }

                            // If this was an SPRICE update, revert Spft% as well
                            if (title === 'SPRICE') {
                                // We don't have original Spft% value, so we'll need to recalculate it
                                const item = filteredData[index];
                                const SHIP = parseFloat(item.raw_data.SHIP) || 0;
                                const LP = parseFloat(item.raw_data.LP) || 0;
                                const SPRICE = parseFloat(originalValue) || 0;

                                let Spft = 0;
                                if (SPRICE !== 0) {
                                    Spft = (SPRICE * price - SHIP - LP) / SPRICE;
                                }

                                amazonDataCache.updateField(itemId, 'Spft%', Spft);
                                filteredData[index]['Spft%'] = Spft;
                                filteredData[index].raw_data['Spft%'] = Spft;
                            }
                        }

                        // Revert UI
                        if (rowElement) {
                            const checkbox = rowElement.find('.ra-checkbox');
                            if (checkbox.length) {
                                checkbox.prop('checked', originalValue);
                            }
                            renderTable();
                        } else {
                            if (isCheckbox) {
                                contentElement.find('.form-check-input')
                                    .prop('checked', originalValue)
                                    .prop('disabled', true);
                            } else if (isHyperlink) {
                                const displayText = originalValue.length > 30 ?
                                    originalValue.substring(0, 15) + '...' + originalValue.slice(-10) :
                                    originalValue;
                                contentElement.html(
                                    `<a href="${originalValue}" target="_blank" rel="noopener noreferrer">${displayText}</a>`
                                );
                            } else {
                                contentElement.text(originalValue);
                            }
                        }

                        if (saveIcon) {
                            saveIcon.html('<i class="fas fa-pen text-primary"></i>')
                                .removeClass('save-icon')
                                .addClass('edit-icon');
                        }

                        // Make sure field is uneditable after error
                        if (!rowElement && !isCheckbox) {
                            contentElement.attr('contenteditable', 'false')
                                .removeClass('border border-primary');
                        }

                        let errorMessage = 'Update failed - please try again';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }

                        showNotification('danger', errorMessage, title);
                    }
                });
            }

            // Initialize tooltips
            function initTooltips() {
                $('[data-bs-toggle="tooltip"]').tooltip({
                    trigger: 'hover',
                    placement: 'top',
                    boundary: 'window',
                    container: 'body',
                    offset: [0, 5],
                    template: '<div class="tooltip" role="tooltip">' +
                        '<div class="tooltip-arrow"></div>' +
                        '<div class="tooltip-inner"></div></div>'
                });
            }

            // Make columns resizable
            function initResizableColumns() {
                const $table = $('#ebay-table');
                const $headers = $table.find('th');
                let startX, startWidth, columnIndex;

                $headers.each(function () {
                    $(this).append('<div class="resize-handle"></div>');
                });

                $table.on('mousedown', '.resize-handle', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    isResizing = true;
                    $(this).addClass('resizing');

                    const $th = $(this).parent();
                    columnIndex = $th.index();
                    startX = e.pageX;
                    startWidth = $th.outerWidth();

                    $('body').css('user-select', 'none');
                });

                $(document).on('mousemove', function (e) {
                    if (!isResizing) return;

                    const $resizer = $('.resize-handle.resizing');
                    if ($resizer.length) {
                        const $th = $resizer.parent();
                        const newWidth = startWidth + (e.pageX - startX);
                        $th.css('width', newWidth + 'px');
                        $th.css('min-width', newWidth + 'px');
                        $th.css('max-width', newWidth + 'px');
                    }
                });

                $(document).on('mouseup', function (e) {
                    if (!isResizing) return;

                    e.stopPropagation();
                    $('.resize-handle').removeClass('resizing');
                    $('body').css('user-select', '');
                    isResizing = false;
                });
            }

            // Initialize sorting functionality
            function initSorting() {
                $('th[data-field]').addClass('sortable').on('click', function (e) {
                    if (isResizing) {
                        e.stopPropagation();
                        return;
                    }

                    // Prevent sorting when clicking on search inputs
                    if ($(e.target).is('input') || $(e.target).closest('.position-relative').length) {
                        return;
                    }

                    const th = $(this).closest('th');
                    const thField = th.data('field');
                    const dataField = thField === 'parent' ? 'Parent' : thField;

                    // Toggle direction if clicking same column, otherwise reset to ascending
                    if (currentSort.field === dataField) {
                        currentSort.direction *= -1;
                    } else {
                        currentSort.field = dataField;
                        currentSort.direction = 1;
                    }

                    // Update UI arrows
                    $('.sort-arrow').html('↓');
                    $(this).find('.sort-arrow').html(currentSort.direction === 1 ? '↑' : '↓');

                    // Sort with fresh data
                    const freshData = [...tableData];
                    freshData.sort((a, b) => {
                        const valA = a[dataField] || '';
                        const valB = b[dataField] || '';

                        // Numeric comparison for numeric fields
                        if (dataField === 'sl_no' || dataField === 'INV' || dataField === 'L30') {
                            return (parseFloat(valA) - parseFloat(valB)) * currentSort.direction;
                        }

                        // String comparison for other fields
                        return String(valA).localeCompare(String(valB)) * currentSort.direction;
                    });

                    filteredData = freshData;
                    currentPage = 1;
                    renderTable();
                });
            }

            // Initialize pagination
            function initPagination() {
                // Remove rows-per-page related code

                // Keep these but modify to work with all rows
                $('#first-page').on('click', function () {
                    currentPage = 1;
                    renderTable();
                });

                // Similar modifications for other pagination buttons...
                // But since we're showing all rows, you might want to disable pagination completely
            }

            function updatePaginationInfo() {
                // Since we're showing all rows, you can either:
                // Option 1: Hide pagination completely
                $('.pagination-controls').hide();

                // Option 2: Show "Showing all rows" message
                $('#page-info').text('Showing all rows');
                $('#first-page, #prev-page, #next-page, #last-page').prop('disabled', true);
            }

            // Initialize search functionality
            function initSearch() {
                $('#search-input').on('keyup', function () {
                    const searchTerm = $(this).val().toLowerCase();

                    if (searchTerm) {
                        filteredData = tableData.filter(item => {
                            return Object.values(item).some(val => {
                                if (typeof val === 'boolean' || val === null) return false;
                                return val.toString().toLowerCase().includes(searchTerm);
                            });
                        });
                    } else {
                        filteredData = [...tableData];
                    }

                    currentPage = 1;
                    renderTable();
                    calculateTotals();
                });
            }

            // Initialize column toggle functionality
            function initColumnToggle() {
                const $table = $('#ebay-table');
                const $headers = $table.find('th[data-field]');
                const $menu = $('#columnToggleMenu');
                const $dropdownBtn = $('#hideColumnsBtn');

                $menu.empty();

                $headers.each(function () {
                    const $th = $(this);
                    const field = $th.data('field');
                    const title = $th.text().trim().replace(' ↓', '');

                    const $item = $(`
                                                                    <div class="column-toggle-item">
                                                                        <input type="checkbox" class="column-toggle-checkbox" 
                                                                               id="toggle-${field}" data-field="${field}" checked>
                                                                        <label for="toggle-${field}">${title}</label>
                                                                    </div>
                                                                `);

                    $menu.append($item);
                });

                $dropdownBtn.on('click', function (e) {
                    e.stopPropagation();
                    $menu.toggleClass('show');
                });

                $(document).on('click', function (e) {
                    if (!$(e.target).closest('.custom-dropdown').length) {
                        $menu.removeClass('show');
                    }
                });

                $menu.on('change', '.column-toggle-checkbox', function () {
                    const field = $(this).data('field');
                    const isVisible = $(this).is(':checked');

                    const colIndex = $headers.filter(`[data-field="${field}"]`).index();
                    $table.find('tr').each(function () {
                        $(this).find('td, th').eq(colIndex).toggle(isVisible);
                    });
                });

                $('#showAllColumns').on('click', function () {
                    $menu.find('.column-toggle-checkbox').prop('checked', true).trigger('change');
                    $menu.removeClass('show');
                });
            }

            // Initialize filters
            function initFilters() {
                $('.dropdown-menu').on('click', '.column-filter', function (e) {
                    e.preventDefault();
                    const $this = $(this);
                    const column = $this.data('column');
                    const color = $this.data('color');
                    const text = $this.find('span').text().trim();

                    $this.closest('.dropdown')
                        .find('.dropdown-toggle')
                        .html(`<span class="status-circle ${color}"></span> ${column} (${text})`);

                    state.filters[column] = color;
                    $this.closest('.dropdown-menu').removeClass('show');
                    applyColumnFilters();
                });

                // Entry type filter
                $('.entry-type-filter').on('click', function (e) {
                    e.preventDefault();
                    const value = $(this).data('value');
                    const text = $(this).text();

                    $('#entryTypeFilter').html(`Entry Type: ${text}`);
                    state.filters.entryType = value;
                    $('.dropdown-menu').removeClass('show');
                    applyColumnFilters();
                });
            }

            // Add this script after your other filter initializations:
            $('#inv-filter').on('change', function () {
                applyColumnFilters();
            });

            // Update applyColumnFilters function to include INV filter:
            function applyColumnFilters() {
                // Reset filteredData to all data first
                filteredData = [...tableData];

                // Apply row type filter first
                const rowTypeFilter = $('#row-data-type').val();
                if (rowTypeFilter === 'parent') {
                    filteredData = filteredData.filter(item => item.is_parent);
                } else if (rowTypeFilter === 'sku') {
                    filteredData = filteredData.filter(item => !item.is_parent);
                }

                // --- INV filter ---
                const invFilter = $('#inv-filter').val();
                if (invFilter && invFilter !== 'all') {
                    filteredData = filteredData.filter(item => {
                        const inv = Number(item.INV) || 0;
                        if (invFilter === '0') return inv === 0;
                        if (invFilter === '1-15') return inv >= 1 && inv <= 15;
                        if (invFilter === '16-30') return inv >= 16 && inv <= 30;
                        if (invFilter === '31-50') return inv >= 31 && inv <= 50;
                        if (invFilter === '51-75') return inv >= 51 && inv <= 75;
                        if (invFilter === '76-100') return inv >= 76 && inv <= 100;
                        if (invFilter === '101+') return inv > 100;
                        return true;
                    });
                }

                // Then apply other filters as before
                Object.entries(state.filters).forEach(([column, filterValue]) => {
                    if (filterValue === 'all') return;

                    filteredData = filteredData.filter(item => {
                        if (column === 'entryType') {
                            if (filterValue === 'parent') return item.is_parent;
                            if (filterValue === 'child') return !item.is_parent;
                            return true;
                        }

                        const color = getColorForColumn(column, item);
                        return color === filterValue;
                    });
                });

                currentPage = 1;
                renderTable();
                calculateTotals();
            }

            // Get color for column based on value
            function getColorForColumn(column, rowData) {
                if (!rowData || rowData[column] === undefined || rowData[column] === null || rowData[column] ===
                    '') {
                    return '';
                }

                // Only multiply by 100 for columns that are stored as decimals
                let value = parseFloat(rowData[column]);
                if (['Dil%', 'A Dil%', 'Tacos30', 'SCVR'].includes(column)) {
                    value = value * 100;
                }

                // Special cases for numeric columns that must be valid numbers
                const numericColumns = ['PFT_percentage', 'ROI_percentage', 'Tacos30', 'SCVR'];
                if (numericColumns.includes(column) && isNaN(value)) {
                    return '';
                }

                const colorRules = {
                    'Dil%': {
                        ranges: [16.66, 25, 50], // Key change here
                        colors: ['red', 'yellow', 'green', 'pink']
                    },
                    'A Dil%': {
                        ranges: [16.66, 25, 50],
                        colors: ['red', 'yellow', 'green', 'pink']
                    },
                    'PFT_percentage': {
                        ranges: [10, 15, 20, 40],
                        colors: ['red', 'yellow', 'blue', 'green', 'pink']
                    },
                    'ROI_percentage': {
                        ranges: [50, 75, 100],
                        colors: ['red', 'yellow', 'green', 'pink']
                    },
                    'Tacos30': {
                        ranges: [5, 10, 15, 20],
                        colors: ['pink', 'green', 'blue', 'yellow', 'red']
                    },
                    'SCVR': {
                        ranges: [7, 13],
                        colors: ['red', 'green', 'pink']
                    }
                };

                const rule = colorRules[column] || {};
                if (!rule.ranges) return '';

                let colorIndex = rule.ranges.length; // Default to last color
                for (let i = 0; i < rule.ranges.length; i++) {
                    if (value < rule.ranges[i]) {
                        colorIndex = i;
                        break;
                    }
                }

                return rule.colors[colorIndex] || '';
            }

            // Calculate and display totals
            function calculateTotals() {
                try {
                    if (isLoading || filteredData.length === 0) {
                        resetMetricsToZero();
                        return;
                    }

                    const metrics = {
                        invTotal: 0,
                        ovL30Total: 0,
                        ovDilTotal: 0,
                        impTotal: 0,
                        clicksTotal: 0,
                        ctrTotal: 0,
                        spendTotal: 0,
                        adSalesTotal: 0,
                        cpcTotal: 0,
                        adSoldTotal: 0,
                        acosTotal: 0,
                        cvrTotal: 0,
                        budgetTotal: 0,
                        ubl7Total: 0,
                        ubl1Total: 0,
                        rowCount: 0
                    };

                    filteredData.forEach(item => {
                        metrics.invTotal += parseFloat(item.INV) || 0;
                        metrics.ovL30Total += parseFloat(item.L30) || 0;

                        // Advertising metrics
                        const impressions = parseFloat(item.raw_data.Impressions) || 0;
                        const clicksL30 = parseFloat(item.raw_data.Clicks) || 0;
                        const adSpendL30 = parseFloat(item.raw_data['Ad fees'] || item.raw_data['TSpnd30'] || 0);
                        const adSalesL30 = parseFloat(item.raw_data['Sales'] || 0);
                        const adSoldL30 = parseFloat(item.raw_data['Sold quantity'] || 0);
                        const dailyBudget = parseFloat(item.raw_data['Daily budget'] || 0);
                        const avgCPC = parseFloat(item.raw_data['Average cost per click'] || 0);
                        const ubl7 = parseFloat(item.raw_data['UBL7'] || 0);
                        const ubl1 = parseFloat(item.raw_data['UBL1'] || 0);

                        metrics.impTotal += impressions;
                        metrics.clicksTotal += clicksL30;
                        metrics.spendTotal += adSpendL30;
                        metrics.adSalesTotal += adSalesL30;
                        metrics.cpcTotal += avgCPC;
                        metrics.adSoldTotal += adSoldL30;
                        metrics.budgetTotal += dailyBudget;
                        metrics.ubl7Total += ubl7;
                        metrics.ubl1Total += ubl1;

                        metrics.rowCount++;
                    });

                    // Calculate OV DIL% (sum of L30 / sum of INV * 100)
                    metrics.ovDilTotal = metrics.invTotal > 0 ? (metrics.ovL30Total / metrics.invTotal) * 100 : 0;
                    metrics.ctrTotal = (metrics.clicksTotal / metrics.impTotal) * 100;
                    metrics.cvrTotal = (metrics.adSoldTotal / metrics.clicksTotal) * 100
                    metrics.acosTotal = metrics.spendTotal > 0 ? (metrics.adSalesTotal === 0 ? 100 : (metrics.spendTotal / metrics.adSalesTotal) * 100) : 0;

                    const divisor = metrics.rowCount || 1;

                    // Update metric displays
                    $('#inv-total').text(metrics.invTotal.toLocaleString());
                    $('#ovl30-total').text(metrics.ovL30Total.toLocaleString());
                    $('#ovdil-total').text(Math.round(metrics.ovDilTotal) + '%');

                    // Advertising metrics
                    $('#imp-l30-total').text(metrics.impTotal.toLocaleString());
                    $('#clicks-l30-total').text(metrics.clicksTotal.toLocaleString());
                    $('#ctr-l30-total').text(metrics.ctrTotal.toFixed(2) + '%');
                    $('#spend-l30-total').text(metrics.spendTotal.toFixed(2));
                    $('#ad-sales-l30-total').text(metrics.adSalesTotal.toFixed(2));
                    $('#cpc-l1-total').text((metrics.cpcTotal).toFixed(2));
                    $('#ad-sold-l30-total').text(metrics.adSoldTotal.toLocaleString());
                    $('#acos-l30-total').text(metrics.acosTotal.toFixed(2) + '%');
                    $('#cvr-l30-total').text(metrics.cvrTotal.toFixed(2) + '%');
                    $('#bgt-crnt-total').text(metrics.budgetTotal.toFixed(2));
                    $('#ubl7-total').text(metrics.ubl7Total.toLocaleString());
                    $('#ubl1-total').text(metrics.ubl1Total.toLocaleString());

                } catch (error) {
                    console.error('Error in calculateTotals:', error);
                    resetMetricsToZero();
                }
            }

            function resetMetricsToZero() {
                // Inventory metrics
                $('#inv-total').text('0');
                $('#ovl30-total').text('0');
                $('#ovdil-total').text('0%');

                // Advertising metrics
                $('#imp-l30-total').text('0');
                $('#clicks-l30-total').text('0');
                $('#ctr-l30-total').text('0%');
                $('#spend-l30-total').text('0');
                $('#ad-sales-l30-total').text('0');
                $('#cpc-l1-total').text('0');
                $('#ad-sold-l30-total').text('0');
                $('#acos-l30-total').text('0%');
                $('#cvr-l30-total').text('0%');
                $('#bgt-crnt-total').text('0');
                $('#ubl7-total').text('0');
                $('#ubl1-total').text('0');
            }

            // Initialize enhanced dropdowns
            function initEnhancedDropdowns() {
                // Define constants at the function level
                const minSearchLength = 1;

                // Parent dropdown
                const $parentSearch = $('#parentSearch');
                const $parentResults = $('#parentSearchResults');

                // SKU dropdown
                const $skuSearch = $('#skuSearch');
                const $skuResults = $('#skuSearchResults');

                // Initialize both dropdowns
                initEnhancedDropdown($parentSearch, $parentResults, 'Parent');
                initEnhancedDropdown($skuSearch, $skuResults, '(Child) sku');

                // Close dropdowns when clicking outside
                $(document).on('click', function (e) {
                    if (!$(e.target).closest('.dropdown-search-container').length) {
                        $('.dropdown-search-results').hide();
                    }
                });

                // Function to update dropdown results
                function updateDropdownResults($results, field, searchTerm) {
                    if (!tableData.length) return;

                    $results.empty();

                    // Get unique values for the field
                    const uniqueValues = [...new Set(tableData.map(item => String(item[field] || '')))];

                    // Filter based on search term if provided
                    const filteredValues = searchTerm.length >= minSearchLength ?
                        uniqueValues.filter(value =>
                            value.toLowerCase().includes(searchTerm.toLowerCase())
                        ) :
                        uniqueValues;

                    if (filteredValues.length) {
                        filteredValues.sort().forEach(value => {
                            if (value) {
                                $results.append(
                                    `<div class="dropdown-search-item" tabindex="0" data-value="${value}">${value}</div>`
                                );
                            }
                        });
                    } else {
                        $results.append('<div class="dropdown-search-item no-results">No matches found</div>');
                    }

                    $results.show();
                }

                // Function to filter the table by column value
                function filterByColumn(column, value) {
                    if (value === '') {
                        filteredData = [...tableData];
                    } else {
                        filteredData = tableData.filter(item =>
                            String(item[column] || '').toLowerCase() === value.toLowerCase()
                        );
                    }

                    currentPage = 1;
                    renderTable();
                    calculateTotals();
                }

                // Initialize a single dropdown
                function initEnhancedDropdown($input, $results, field) {
                    let timeout;

                    // Show dropdown when input is focused
                    $input.on('focus', function (e) {
                        e.stopPropagation();
                        updateDropdownResults($results, field, $(this).val().trim().toLowerCase());
                    });

                    // Handle input events
                    $input.on('input', function () {
                        clearTimeout(timeout);
                        const searchTerm = $(this).val().trim().toLowerCase();

                        timeout = setTimeout(() => {
                            updateDropdownResults($results, field, searchTerm);
                        }, 300);
                    });

                    // Handle item selection
                    $results.on('click', '.dropdown-search-item:not(.no-results)', function (e) {
                        e.preventDefault();
                        e.stopPropagation();

                        const value = $(this).data('value');
                        $input.val(value);
                        filterByColumn(field, value);
                        $results.hide();
                    });

                    // Handle keyboard navigation
                    $input.on('keydown', function (e) {
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            const $firstItem = $results.find('.dropdown-search-item').first();
                            if ($firstItem.length) {
                                $firstItem.focus();
                                $results.show();
                            }
                        } else if (e.key === 'Escape') {
                            $results.hide();
                        }
                    });

                    $results.on('keydown', '.dropdown-search-item', function (e) {
                        if (e.key === 'ArrowDown') {
                            e.preventDefault();
                            $(this).next('.dropdown-search-item').focus();
                        } else if (e.key === 'ArrowUp') {
                            e.preventDefault();
                            const $prev = $(this).prev('.dropdown-search-item');
                            if ($prev.length) {
                                $prev.focus();
                            } else {
                                $input.focus();
                            }
                        } else if (e.key === 'Enter') {
                            e.preventDefault();
                            $(this).click();
                            $results.hide();
                        } else if (e.key === 'Escape') {
                            $results.hide();
                            $input.focus();
                        }
                    });
                }

                $('#row-data-type').on('change', function () {
                    const filterType = $(this).val();
                    applyRowTypeFilter(filterType);
                });
            }

            function initEnhancedDropdown($input, $results, field) {
                let timeout;
                const minSearchLength = 1;

                // Show dropdown when input is clicked
                $input.on('click', function (e) {
                    e.stopPropagation();
                    updateDropdownResults($results, field, $(this).val().trim().toLowerCase());
                });

                // Handle input events
                $input.on('input', function () {
                    clearTimeout(timeout);
                    const searchTerm = $(this).val().trim().toLowerCase();

                    // If search is cleared, trigger filtering immediately
                    if (searchTerm === '') {
                        filterByColumn(field, '');
                        return;
                    }

                    timeout = setTimeout(() => {
                        updateDropdownResults($results, field, searchTerm);
                    }, 300);
                });

                // Handle item selection
                $results.on('click', '.dropdown-search-item', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const value = $(this).data('value');
                    $input.val(value);
                    filterByColumn(field, value);

                    // Close the dropdown after selection
                    $results.hide();

                    // If you want to clear the filter when clicking the same value again
                    if ($input.data('last-value') === value) {
                        $input.val('');
                        filterByColumn(field, '');
                    }
                    $input.data('last-value', value);
                });

                // Handle keyboard navigation
                $input.on('keydown', function (e) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        const $firstItem = $results.find('.dropdown-search-item').first();
                        if ($firstItem.length) {
                            $firstItem.focus();
                        }
                    }
                });

                $results.on('keydown', '.dropdown-search-item', function (e) {
                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        $(this).next('.dropdown-search-item').focus();
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        const $prev = $(this).prev('.dropdown-search-item');
                        if ($prev.length) {
                            $prev.focus();
                        } else {
                            $input.focus();
                        }
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        $(this).click();
                        $results.hide();
                    } else if (e.key === 'Escape') {
                        $results.hide();
                        $input.focus();
                    }
                });
            }

            function updateDropdownResults($results, field, searchTerm) {
                if (!tableData.length) return;

                $results.empty();

                if (searchTerm.length < minSearchLength) {
                    // Show all unique values when search is empty
                    const uniqueValues = [...new Set(tableData.map(item => String(item[field] || '')))];
                    uniqueValues.sort().forEach(value => {
                        if (value) {
                            $results.append(
                                `<div class="dropdown-search-item" data-value="${value}">${value}</div>`
                            );
                        }
                    });
                } else {
                    // Filter results based on search term
                    const matches = tableData.filter(item =>
                        String(item[field] || '').toLowerCase().includes(searchTerm)
                    );

                    if (matches.length) {
                        const uniqueMatches = [...new Set(matches.map(item => String(item[field] || '')))];
                        uniqueMatches.sort().forEach(value => {
                            if (value) {
                                $results.append(
                                    `<div class="dropdown-search-item" data-value="${value}">${value}</div>`
                                );
                            }
                        });
                    } else {
                        $results.append('<div class="dropdown-search-item no-results">No matches found</div>');
                    }
                }

                $results.show();
            }

            function applyRowTypeFilter(filterType) {
                // Reset to all data first
                filteredData = [...tableData];

                // Apply the row type filter
                if (filterType === 'parent') {
                    filteredData = filteredData.filter(item => item.is_parent);
                } else if (filterType === 'sku') {
                    filteredData = filteredData.filter(item => !item.is_parent);
                }
                // else 'all' - no filtering needed

                // Reset to first page and render
                currentPage = 1;
                renderTable();
                calculateTotals();
            }

            // Initialize manual dropdowns
            function initManualDropdowns() {
                // Toggle dropdown when any filter button is clicked
                $(document).on('click', '.dropdown-toggle', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).next('.dropdown-menu').toggleClass('show');

                    // Close other open dropdowns
                    $('.dropdown-menu').not($(this).next('.dropdown-menu')).removeClass('show');
                });

                // Close dropdown when clicking outside
                $(document).on('click', function (e) {
                    if (!$(e.target).closest('.dropdown').length) {
                        $('.dropdown-menu').removeClass('show');
                    }
                });

                // Handle dropdown item selection for all filters
                $(document).on('click', '.dropdown-item', function (e) {
                    e.preventDefault();
                    const $dropdown = $(this).closest('.dropdown');

                    // Update button text
                    const color = $(this).data('color');
                    const text = $(this).text().trim();
                    $dropdown.find('.dropdown-toggle').html(
                        `<span class="status-circle ${color}"></span> ${text.split(' ')[0]}`
                    );

                    // Close dropdown
                    $dropdown.find('.dropdown-menu').removeClass('show');

                    // Apply filter logic
                    const column = $(this).data('column');
                    state.filters[column] = color;
                    applyColumnFilters();
                });

                // Keyboard navigation for dropdowns
                $(document).on('keydown', '.dropdown', function (e) {
                    const $menu = $(this).find('.dropdown-menu');
                    const $items = $menu.find('.dropdown-item');
                    const $active = $items.filter(':focus');

                    switch (e.key) {
                        case 'Escape':
                            $menu.removeClass('show');
                            $(this).find('.dropdown-toggle').focus();
                            break;
                        case 'ArrowDown':
                            if ($menu.hasClass('show')) {
                                e.preventDefault();
                                $active.length ? $active.next().focus() : $items.first().focus();
                            }
                            break;
                        case 'ArrowUp':
                            if ($menu.hasClass('show')) {
                                e.preventDefault();
                                $active.length ? $active.prev().focus() : $items.last().focus();
                            }
                            break;
                        case 'Enter':
                            if ($active.length) {
                                e.preventDefault();
                                $active.click();
                            }
                            break;
                    }
                });
            }

            // Show notification
            function showNotification(type, message) {
                const notification = $(`
                                                                <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
                                                                    <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                                                                        ${message}
                                                                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                                                    </div>
                                                                </div>
                                                            `);

                $('body').append(notification);

                setTimeout(() => {
                    notification.find('.alert').alert('close');
                }, 3000);
            }

            // Loader functions
            function showLoader() {
                $('#data-loader').fadeIn();
            }

            function hideLoader() {
                $('#data-loader').fadeOut();
            }

            // Initialize everything
            initTable();
        });
    </script>
@endsection