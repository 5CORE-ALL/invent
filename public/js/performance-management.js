/**
 * Performance Management System - Main JavaScript
 */

// Global variables
let trendChart = null;
let categoryChart = null;
let currentDesignationId = null;
let currentEmployeeId = null;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Check if performance management container exists
    const perfContainer = document.querySelector('.performance-management-container');
    if (!perfContainer) {
        return; // Exit if not on performance management page
    }
    
    // Initialize immediately if tab is already active
    const performanceContent = document.getElementById('performance-content');
    if (performanceContent && performanceContent.classList.contains('show') && performanceContent.classList.contains('active')) {
        initializePerformanceManagement();
    }
    
    // Listen for tab changes - when Performance Management tab is shown
    const performanceTab = document.getElementById('performance-tab');
    if (performanceTab) {
        performanceTab.addEventListener('shown.bs.tab', function() {
            // Re-initialize when Performance Management tab is shown
            setTimeout(() => {
                initializePerformanceManagement();
            }, 200);
        });
    }
    
    // Also listen for inner tab changes within Performance Management
    const performanceTabs = document.querySelectorAll('#performanceTabs button[data-bs-toggle="tab"]');
    performanceTabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function() {
            // Reload designations when switching tabs (in case they weren't loaded)
            setTimeout(() => {
                loadDesignations();
            }, 100);
        });
    });
});

function initializePerformanceManagement() {
    // Load designations
    loadDesignations();
    
    // Load employees for review
    loadEmployees();
    
    // Setup event listeners
    setupEventListeners();
    
    // Load dashboard data if on dashboard tab
    const dashboardTab = document.getElementById('dashboard-tab');
    if (dashboardTab && (dashboardTab.classList.contains('active') || dashboardTab.getAttribute('aria-selected') === 'true')) {
        setTimeout(() => loadDashboardData(), 500);
    }
    
    // Load review history
    loadReviewHistory();
}

function setupEventListeners() {
    // Designation select change
    const checklistDesignationSelect = document.getElementById('checklist-designation-select');
    if (checklistDesignationSelect) {
        checklistDesignationSelect.addEventListener('change', function() {
            currentDesignationId = this.value;
            if (currentDesignationId) {
                loadChecklist(currentDesignationId);
            } else {
                document.getElementById('checklist-container').style.display = 'none';
            }
        });
    }

    // Dashboard employee select change (for admins/managers)
    const dashboardEmployeeSelect = document.getElementById('dashboard-employee-select');
    if (dashboardEmployeeSelect) {
        dashboardEmployeeSelect.addEventListener('change', function() {
            if (this.value) {
                currentEmployeeId = this.value;
                loadDashboardData();
                updateDashboardUserName(this.options[this.selectedIndex].text);
            } else {
                currentEmployeeId = null;
                loadDashboardData();
            }
        });
    }

    // Review employee/designation change
    const reviewEmployeeSelect = document.getElementById('review-employee-select');
    const reviewDesignationSelect = document.getElementById('review-designation-select');
    
    if (reviewDesignationSelect) {
        reviewDesignationSelect.addEventListener('change', function() {
            if (this.value) {
                loadReviewChecklist(this.value);
            }
        });
    }
    
    if (reviewEmployeeSelect) {
        reviewEmployeeSelect.addEventListener('change', function() {
            currentEmployeeId = this.value;
            if (this.value) {
                // Fetch employee data to get their designation
                fetch(`/api/users/active`)
                    .then(res => res.json())
                    .then(users => {
                        const employee = users.find(u => u.id == this.value);
                        if (employee && employee.designation && reviewDesignationSelect) {
                            // Try to find matching option
                            for (let option of reviewDesignationSelect.options) {
                                if (option.value === employee.designation || 
                                    option.textContent.includes(employee.designation)) {
                                    reviewDesignationSelect.value = option.value;
                                    loadReviewChecklist(option.value);
                                    break;
                                }
                            }
                        } else if (this.value && reviewDesignationSelect?.value) {
                            // If designation already selected, load checklist
                            loadReviewChecklist(reviewDesignationSelect.value);
                        }
                    })
                    .catch(err => {
                        console.error('Error fetching employee data:', err);
                        if (this.value && reviewDesignationSelect?.value) {
                            loadReviewChecklist(reviewDesignationSelect.value);
                        }
                    });
            }
        });
    }

    // Review period change
    const reviewPeriodSelect = document.getElementById('review-period-select');
    if (reviewPeriodSelect) {
        reviewPeriodSelect.addEventListener('change', function() {
            const isCustom = this.value === 'Custom';
            document.getElementById('period-start-container').style.display = isCustom ? 'block' : 'none';
            document.getElementById('period-end-container').style.display = isCustom ? 'block' : 'none';
        });
    }

    // Form submissions
    const designationForm = document.getElementById('designation-form');
    if (designationForm) {
        designationForm.addEventListener('submit', handleDesignationSubmit);
    }

    const categoryForm = document.getElementById('category-form');
    if (categoryForm) {
        categoryForm.addEventListener('submit', handleCategorySubmit);
    }

    const itemForm = document.getElementById('item-form');
    if (itemForm) {
        itemForm.addEventListener('submit', handleItemSubmit);
    }

    const reviewForm = document.getElementById('review-form');
    if (reviewForm) {
        reviewForm.addEventListener('submit', handleReviewSubmit);
    }

    // Add buttons
    const addDesignationBtn = document.getElementById('add-designation-btn');
    if (addDesignationBtn) {
        addDesignationBtn.addEventListener('click', () => openDesignationModal());
    }

    const addCategoryBtn = document.getElementById('add-category-btn');
    if (addCategoryBtn) {
        addCategoryBtn.addEventListener('click', () => openCategoryModal());
    }

    const exportChecklistBtn = document.getElementById('export-checklist-btn');
    if (exportChecklistBtn) {
        exportChecklistBtn.addEventListener('click', handleExportChecklist);
    }

    const importChecklistBtn = document.getElementById('import-checklist-btn');
    if (importChecklistBtn) {
        importChecklistBtn.addEventListener('click', () => openImportCsvModal());
    }

    const importCsvForm = document.getElementById('import-csv-form');
    if (importCsvForm) {
        importCsvForm.addEventListener('submit', handleImportCsv);
    }

    // Tab change events
    const performanceTabs = document.querySelectorAll('#performanceTabs button[data-bs-toggle="tab"]');
    performanceTabs.forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            const targetId = e.target.getAttribute('data-bs-target');
            if (targetId === '#dashboard-content') {
                // Load employees first (to populate selector for admins/managers)
                loadEmployees().then(() => {
                    setTimeout(() => loadDashboardData(), 300);
                });
            } else if (targetId === '#history-content') {
                loadReviewHistory();
            }
        });
    });
}

// Load Functions
async function loadDesignations() {
    try {
        console.log('Loading designations from /performance/designations...');
        const response = await fetch('/performance/designations', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            const errorText = await response.text();
            console.error('Failed to fetch designations:', response.status, response.statusText, errorText);
            showToast('Error loading designations: ' + response.statusText, 'error');
            return;
        }
        
        const data = await response.json();
        
        console.log('Designations loaded:', data); // Debug log
        
        const checklistSelect = document.getElementById('checklist-designation-select');
        const reviewSelect = document.getElementById('review-designation-select');
        
        const selects = [checklistSelect, reviewSelect].filter(s => s !== null);
        
        if (selects.length === 0) {
            console.warn('Designation select elements not found. They may not be rendered yet.');
            return;
        }
        
        selects.forEach(select => {
            select.innerHTML = '<option value="">-- Select Designation --</option>';
            
            if (data && Array.isArray(data) && data.length > 0) {
                data.forEach(des => {
                    if (des.is_active !== false) {
                        const option = document.createElement('option');
                        // Use name as value for dynamic designations, ID for table designations
                        option.value = des.is_dynamic ? des.name : (des.id || des.name);
                        option.textContent = des.name + (des.user_count ? ` (${des.user_count} users)` : '');
                        option.setAttribute('data-is-dynamic', des.is_dynamic ? 'true' : 'false');
                        select.appendChild(option);
                    }
                });
                console.log(`Populated ${selects.length} designation select(s) with ${data.length} options`);
            } else {
                // If no designations found, show message
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No designations found';
                option.disabled = true;
                select.appendChild(option);
                console.warn('No designations found in response');
            }
        });
    } catch (error) {
        console.error('Error loading designations:', error);
        showToast('Error loading designations: ' + error.message, 'error');
    }
}

async function loadEmployees() {
    try {
        // Load employees for review form
        const response = await fetch('/api/users/active', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            console.error('Failed to load employees');
            return;
        }
        
        const employees = await response.json();
        
        // Populate review employee select
        const reviewEmployeeSelect = document.getElementById('review-employee-select');
        if (reviewEmployeeSelect) {
            reviewEmployeeSelect.innerHTML = '<option value="">-- Select Employee --</option>';
            employees.forEach(emp => {
                const option = document.createElement('option');
                option.value = emp.id;
                option.textContent = `${emp.name} (${emp.email})`;
                option.setAttribute('data-designation', emp.designation || '');
                reviewEmployeeSelect.appendChild(option);
            });
        }
        
        // Populate dashboard employee select (for admins/managers)
        const dashboardEmployeeSelect = document.getElementById('dashboard-employee-select');
        if (dashboardEmployeeSelect) {
            dashboardEmployeeSelect.innerHTML = '<option value="">-- Select Employee --</option>';
            employees.forEach(emp => {
                const option = document.createElement('option');
                option.value = emp.id;
                option.textContent = `${emp.name} (${emp.email})`;
                dashboardEmployeeSelect.appendChild(option);
            });
            
            // Set default to current user
            const currentUserId = getCurrentUserId();
            if (currentUserId) {
                dashboardEmployeeSelect.value = currentUserId;
                const selectedOption = dashboardEmployeeSelect.options[dashboardEmployeeSelect.selectedIndex];
                if (selectedOption) {
                    updateDashboardUserName(selectedOption.textContent);
                }
            }
        }
    } catch (error) {
        console.error('Error loading employees:', error);
    }
}

async function loadEmployeesOld() {
    try {
        const response = await fetch('/users/add');
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        
        // Extract users from the page (you may need to create an API endpoint for this)
        const employeeSelect = document.getElementById('review-employee-select');
        if (employeeSelect) {
            // Load via API
        fetch('/api/users/active')
            .then(res => res.json())
            .then(users => {
                if (employeeSelect) {
                    employeeSelect.innerHTML = '<option value="">-- Select Employee --</option>';
                    users.forEach(user => {
                        const option = document.createElement('option');
                        option.value = user.id;
                        option.textContent = user.name + ' (' + user.email + ')' + (user.designation ? ' - ' + user.designation : '');
                        option.setAttribute('data-designation', user.designation || '');
                        employeeSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Error loading employees:', error);
                if (employeeSelect) {
                    employeeSelect.innerHTML = '<option value="">Error loading employees</option>';
                }
            });
        }
    } catch (error) {
        console.error('Error loading employees:', error);
    }
}

async function loadChecklist(designationIdOrName) {
    try {
        console.log('Loading checklist for:', designationIdOrName);
        const response = await fetch(`/performance/checklist/${encodeURIComponent(designationIdOrName)}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ error: 'Unknown error' }));
            console.error('Failed to load checklist:', response.status, errorData);
            
            if (response.status === 404) {
                const container = document.getElementById('checklist-container');
                const categoriesContainer = document.getElementById('categories-container');
                if (container && categoriesContainer) {
                    categoriesContainer.innerHTML = `
                        <div class="alert alert-info">
                            <i class="ri-information-line me-2"></i>
                            No checklist found for this designation. Click "Add Category" to create one.
                        </div>
                    `;
                    container.style.display = 'block';
                }
                return;
            }
            
            showToast('Error loading checklist: ' + (errorData.message || errorData.error || 'Unknown error'), 'error');
            throw new Error('Failed to load checklist: ' + response.status);
        }
        
        const designation = await response.json();
        console.log('Checklist loaded:', designation);
        
        const container = document.getElementById('checklist-container');
        const categoriesContainer = document.getElementById('categories-container');
        
        if (!container || !categoriesContainer) {
            console.error('Checklist container elements not found');
            return;
        }
        
        // Handle empty categories
        if (!designation.categories || designation.categories.length === 0) {
            categoriesContainer.innerHTML = `
                <div class="alert alert-info">
                    <i class="ri-information-line me-2"></i>
                    No categories found for this designation. Click "Add Category" to create one.
                </div>
            `;
            container.style.display = 'block';
            return;
        }

        let html = '';
        designation.categories.forEach(category => {
            html += `
                <div class="card mb-3 category-card" data-category-id="${category.id}">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">${category.name}</h6>
                            ${category.description ? `<small class="text-muted">${category.description}</small>` : ''}
                        </div>
                        <div>
                            <button class="btn btn-sm btn-primary edit-category-btn" data-category-id="${category.id}">
                                <i class="ri-edit-line"></i>
                            </button>
                            <button class="btn btn-sm btn-success add-item-btn" data-category-id="${category.id}">
                                <i class="ri-add-line"></i> Add Item
                            </button>
                            <button class="btn btn-sm btn-danger delete-category-btn" data-category-id="${category.id}">
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="items-list" data-category-id="${category.id}">
                            ${renderItems(category.items || [])}
                        </div>
                    </div>
                </div>
            `;
        });

        categoriesContainer.innerHTML = html;
        container.style.display = 'block';

        // Attach event listeners to new elements
        attachChecklistEventListeners();
    } catch (error) {
        console.error('Error loading checklist:', error);
        showToast('Error loading checklist: ' + error.message, 'error');
        
        // Show error in container if it exists
        const container = document.getElementById('checklist-container');
        const categoriesContainer = document.getElementById('categories-container');
        if (container && categoriesContainer) {
            categoriesContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="ri-error-warning-line me-2"></i>
                    Error loading checklist: ${error.message}
                </div>
            `;
            container.style.display = 'block';
        }
    }
}

function renderItems(items) {
    if (!items || items.length === 0) {
        return '<p class="text-muted mb-0">No items in this category.</p>';
    }

    return items.map(item => `
        <div class="d-flex justify-content-between align-items-start mb-2 p-2 border rounded item-row" data-item-id="${item.id}">
            <div class="flex-grow-1">
                <strong>${item.question}</strong>
                <br>
                <small class="text-muted">Weight: ${item.weight}</small>
            </div>
            <div>
                <button class="btn btn-sm btn-primary edit-item-btn" data-item-id="${item.id}">
                    <i class="ri-edit-line"></i>
                </button>
                <button class="btn btn-sm btn-danger delete-item-btn" data-item-id="${item.id}">
                    <i class="ri-delete-bin-line"></i>
                </button>
            </div>
        </div>
    `).join('');
}

function attachChecklistEventListeners() {
    // Edit category buttons
    document.querySelectorAll('.edit-category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const categoryId = this.getAttribute('data-category-id');
            editCategory(categoryId);
        });
    });

    // Add item buttons
    document.querySelectorAll('.add-item-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const categoryId = this.getAttribute('data-category-id');
            openItemModal(null, categoryId);
        });
    });

    // Delete category buttons
    document.querySelectorAll('.delete-category-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const categoryId = this.getAttribute('data-category-id');
            if (confirm('Are you sure you want to delete this category?')) {
                deleteCategory(categoryId);
            }
        });
    });

    // Edit item buttons
    document.querySelectorAll('.edit-item-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            editItem(itemId);
        });
    });

    // Delete item buttons
    document.querySelectorAll('.delete-item-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const itemId = this.getAttribute('data-item-id');
            if (confirm('Are you sure you want to delete this item?')) {
                deleteItem(itemId);
            }
        });
    });
}

// Modal Functions
function openDesignationModal(designationId = null) {
    const modal = new bootstrap.Modal(document.getElementById('designationModal'));
    const form = document.getElementById('designation-form');
    const title = document.getElementById('designationModalTitle');
    
    form.reset();
    document.getElementById('designation-id').value = '';
    
    if (designationId) {
        title.textContent = 'Edit Designation';
        // Load designation data
        loadDesignationData(designationId);
    } else {
        title.textContent = 'Add Designation';
    }
    
    modal.show();
}

function openCategoryModal(categoryId = null) {
    const modal = new bootstrap.Modal(document.getElementById('categoryModal'));
    const form = document.getElementById('category-form');
    const title = document.getElementById('categoryModalTitle');
    
    form.reset();
    document.getElementById('category-id').value = '';
    document.getElementById('category-designation-id').value = currentDesignationId;
    
    if (categoryId) {
        title.textContent = 'Edit Category';
        loadCategoryData(categoryId);
    } else {
        title.textContent = 'Add Category';
    }
    
    modal.show();
}

function openItemModal(itemId = null, categoryId = null) {
    const modal = new bootstrap.Modal(document.getElementById('itemModal'));
    const form = document.getElementById('item-form');
    const title = document.getElementById('itemModalTitle');
    
    form.reset();
    document.getElementById('item-id').value = '';
    document.getElementById('item-category-id').value = categoryId || '';
    
    if (itemId) {
        title.textContent = 'Edit Checklist Item';
        loadItemData(itemId);
    } else {
        title.textContent = 'Add Checklist Item';
    }
    
    modal.show();
}

// Form Handlers
async function handleDesignationSubmit(e) {
    e.preventDefault();
    
    const id = document.getElementById('designation-id').value;
    const data = {
        name: document.getElementById('designation-name').value,
        description: document.getElementById('designation-description').value,
        is_active: document.getElementById('designation-is-active').checked,
    };

    try {
        const url = id ? `/performance/designations/${id}` : '/performance/designations';
        const method = id ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('designationModal')).hide();
            loadDesignations();
            if (currentDesignationId == id || !id) {
                loadChecklist(currentDesignationId || result.data.id);
            }
        } else {
            showToast(result.message || 'Error saving designation', 'error');
        }
    } catch (error) {
        console.error('Error saving designation:', error);
        showToast('Error saving designation', 'error');
    }
}

async function handleCategorySubmit(e) {
    e.preventDefault();
    
    const id = document.getElementById('category-id').value;
    const designationIdOrName = document.getElementById('category-designation-id').value;
    const data = {
        designation_id: designationIdOrName,
        name: document.getElementById('category-name').value.trim(),
        description: document.getElementById('category-description').value.trim() || null,
        order: parseInt(document.getElementById('category-order').value) || 0,
        is_active: true,
    };

    // If designation is not numeric, it's a name - send as designation_name
    if (designationIdOrName && !isNaN(designationIdOrName) && designationIdOrName !== '') {
        // It's numeric, keep as designation_id
    } else if (designationIdOrName && designationIdOrName !== '') {
        // It's a name, send as designation_name
        data.designation_name = designationIdOrName;
        delete data.designation_id;
    }

    console.log('Submitting category:', data);

    try {
        const url = id ? `/performance/checklist/category/${id}` : '/performance/checklist/category';
        const method = id ? 'PUT' : 'POST';
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken) {
            showToast('CSRF token not found', 'error');
            return;
        }
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Unknown error' }));
            console.error('Failed to save category:', response.status, errorData);
            showToast(errorData.message || 'Error saving category', 'error');
            return;
        }
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
            loadChecklist(currentDesignationId);
        } else {
            showToast(result.message || 'Error saving category', 'error');
        }
    } catch (error) {
        console.error('Error saving category:', error);
        showToast('Error saving category: ' + error.message, 'error');
    }
}

async function handleItemSubmit(e) {
    e.preventDefault();
    
    const id = document.getElementById('item-id').value;
    const data = {
        category_id: document.getElementById('item-category-id').value,
        question: document.getElementById('item-question').value,
        weight: parseFloat(document.getElementById('item-weight').value),
        order: parseInt(document.getElementById('item-order').value) || 0,
        is_active: true,
    };

    try {
        const url = id ? `/performance/checklist/item/${id}` : '/performance/checklist/item';
        const method = id ? 'PUT' : 'POST';
        
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        
        if (result.success) {
            showToast(result.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
            loadChecklist(currentDesignationId);
        } else {
            showToast(result.message || 'Error saving item', 'error');
        }
    } catch (error) {
        console.error('Error saving item:', error);
        showToast('Error saving item', 'error');
    }
}

async function handleReviewSubmit(e) {
    e.preventDefault();
    
    const employeeId = document.getElementById('review-employee-select').value;
    const designationIdOrName = document.getElementById('review-designation-select').value;
    const reviewPeriod = document.getElementById('review-period-select').value;
    const reviewDate = document.getElementById('review-date').value;
    
    // Collect ratings
    const ratings = [];
    document.querySelectorAll('[data-checklist-item-id]').forEach(item => {
        const itemId = item.getAttribute('data-checklist-item-id');
        const rating = item.querySelector('.rating-input')?.value;
        const comment = item.querySelector('.comment-input')?.value || '';
        
        if (rating) {
            ratings.push({
                checklist_item_id: parseInt(itemId),
                rating: parseInt(rating),
                comment: comment
            });
        }
    });

    if (ratings.length === 0) {
        showToast('Please provide at least one rating', 'error');
        return;
    }

    // Check if designation is dynamic (name) or from table (ID)
    const designationSelect = document.getElementById('review-designation-select');
    const selectedOption = designationSelect.options[designationSelect.selectedIndex];
    const isDynamic = selectedOption?.getAttribute('data-is-dynamic') === 'true';

    const data = {
        employee_id: parseInt(employeeId),
        review_period: reviewPeriod,
        review_date: reviewDate,
        period_start_date: document.getElementById('period-start-date').value || null,
        period_end_date: document.getElementById('period-end-date').value || null,
        ratings: ratings,
        overall_feedback: document.getElementById('overall-feedback').value || '',
    };

    // Add designation_id or designation_name based on type
    if (isDynamic || !designationIdOrName.match(/^\d+$/)) {
        data.designation_name = designationIdOrName;
    } else {
        data.designation_id = parseInt(designationIdOrName);
    }

    try {
        console.log('Submitting review data:', data);
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken) {
            showToast('CSRF token not found', 'error');
            return;
        }

        const response = await fetch('/performance/reviews', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken.content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(data)
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Unknown error' }));
            console.error('Failed to create review:', response.status, errorData);
            
            let errorMessage = errorData.message || 'Error creating review';
            if (errorData.errors) {
                const errorList = Object.values(errorData.errors).flat().join(', ');
                errorMessage += ': ' + errorList;
            }
            
            showToast(errorMessage, 'error');
            return;
        }

        const result = await response.json();
        
        if (result.success) {
            showToast('Performance review created successfully!', 'success');
            document.getElementById('review-form').reset();
            document.getElementById('review-checklist-container').style.display = 'none';
            document.getElementById('review-checklist-items').innerHTML = '';
            loadReviewHistory();
        } else {
            showToast(result.message || 'Error creating review', 'error');
        }
    } catch (error) {
        console.error('Error creating review:', error);
        showToast('Error creating review: ' + error.message, 'error');
    }
}

// Load Review Checklist
async function loadReviewChecklist(designationIdOrName) {
    try {
        const response = await fetch(`/performance/checklist/${encodeURIComponent(designationIdOrName)}`);
        
        if (!response.ok) {
            if (response.status === 404) {
                const container = document.getElementById('review-checklist-container');
                container.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="ri-alert-line me-2"></i>
                        No checklist found for this designation. Please ask admin to create one first.
                    </div>
                `;
                container.style.display = 'block';
                return;
            }
            throw new Error('Failed to load checklist');
        }
        
        const designation = await response.json();
        
        const container = document.getElementById('review-checklist-items');
        const reviewContainer = document.getElementById('review-checklist-container');
        
        if (!designation.categories || designation.categories.length === 0) {
            container.innerHTML = '<p class="text-danger">No checklist found for this designation. Please create one first.</p>';
            reviewContainer.style.display = 'block';
            return;
        }

        let html = '';
        designation.categories.forEach(category => {
            html += `
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">${category.name}</h6>
                    </div>
                    <div class="card-body">
                        ${(category.items || []).map(item => `
                            <div class="mb-3 p-3 border rounded" data-checklist-item-id="${item.id}">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div class="flex-grow-1">
                                        <strong>${item.question}</strong>
                                        <small class="text-muted d-block">Weight: ${item.weight}</small>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small">Rating (1-5) <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm rating-input" required>
                                        <option value="">Select Rating</option>
                                        <option value="1">1 - Poor</option>
                                        <option value="2">2 - Below Average</option>
                                        <option value="3">3 - Average</option>
                                        <option value="4">4 - Good</option>
                                        <option value="5">5 - Excellent</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label small">Comment (Optional)</label>
                                    <textarea class="form-control form-control-sm comment-input" rows="2" placeholder="Add comment..."></textarea>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        });

        container.innerHTML = html;
        reviewContainer.style.display = 'block';
    } catch (error) {
        console.error('Error loading review checklist:', error);
        showToast('Error loading checklist', 'error');
    }
}

// Dashboard Functions
function renderFocusAreas(categoryScores) {
    const el = document.getElementById('weak-areas-content');
    if (!el) return;

    const rows = Array.isArray(categoryScores) ? categoryScores : [];
    const weak = rows.filter(d => {
        const v = d.avg_score ?? d.avgScore;
        const n = typeof v === 'number' ? v : parseFloat(v);
        return !isNaN(n) && n < 3;
    });

    if (weak.length === 0) {
        el.innerHTML = '<p class="text-muted mb-0">No weak areas identified (or no category data yet).</p>';
        return;
    }

    el.innerHTML = '<ul class="mb-0 ps-3">' + weak.map(d => {
        const name = d.category_name || d.categoryName || 'Category';
        const v = d.avg_score ?? d.avgScore;
        const n = typeof v === 'number' ? v : parseFloat(v);
        return `<li><strong>${name}</strong>: avg ${isNaN(n) ? '—' : n.toFixed(1)} / 5</li>`;
    }).join('') + '</ul>';
}

function formatDashboardScore(value) {
    if (value === null || value === undefined || value === '') return '-';
    const n = typeof value === 'number' ? value : parseFloat(value);
    return isNaN(n) ? '-' : n.toFixed(1);
}

function updateDashboardUserName(userText) {
    const userNameEl = document.getElementById('dashboard-user-name');
    if (userNameEl && userText) {
        // Extract name from "Name (email)" format
        const nameMatch = userText.match(/^([^(]+)/);
        userNameEl.textContent = nameMatch ? nameMatch[1].trim() : userText;
    }
}

async function loadDashboardData() {
    const dashboardContainer = document.querySelector('.performance-management-container');
    
    // Get user ID from dashboard selector (for admins/managers) or default to current user
    const dashboardSelect = document.getElementById('dashboard-employee-select');
    const selectedUserId = dashboardSelect && dashboardSelect.value ? dashboardSelect.value : null;
    
    const userId = selectedUserId || currentEmployeeId || (dashboardContainer ? dashboardContainer.getAttribute('data-user-id') : null) || getCurrentUserId();
    
    if (!userId) {
        console.warn('No user ID available for dashboard');
        return;
    }

    const setText = (id, text) => {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    };

    try {
        const statsResponse = await fetch(`/performance/reviews/employee/${userId}/stats`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const stats = await statsResponse.json();

        if (!statsResponse.ok) {
            console.error('Stats error:', stats);
            setText('current-score', '-');
            setText('predicted-score', '-');
            setText('performance-level', '-');
            setText('total-reviews', '0');
            renderTrendChart([]);
            renderCategoryChart([]);
            return;
        }

        setText('current-score', formatDashboardScore(stats.latest_score));
        setText('predicted-score', formatDashboardScore(stats.predicted_score));
        setText('performance-level', stats.performance_level || '-');
        setText('total-reviews', String(stats.total_reviews ?? 0));

        const chartResponse = await fetch(`/performance/chart-data/${userId}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        });
        const chartData = await chartResponse.json();

        if (!chartResponse.ok) {
            console.error('Chart data error:', chartData);
            renderTrendChart([]);
            renderCategoryChart([]);
        } else {
            const trend = Array.isArray(chartData.trend) ? chartData.trend : Object.values(chartData.trend || {});
            const categoryScores = Array.isArray(chartData.category_scores)
                ? chartData.category_scores
                : Object.values(chartData.category_scores || {});
            renderTrendChart(trend);
            renderCategoryChart(categoryScores);
            renderFocusAreas(categoryScores);
        }

        if (stats.latest_score !== null && stats.latest_score !== undefined && stats.latest_score !== '') {
            loadAIFeedback(userId);
        }
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        renderTrendChart([]);
        renderCategoryChart([]);
    }
}

function renderTrendChart(trendData) {
    const ctx = document.getElementById('trend-chart');
    if (!ctx) return;

    if (trendChart) {
        trendChart.destroy();
    }

    const labels = trendData.map(d => d.date);
    const scores = trendData.map(d => d.score);

    trendChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Performance Score',
                data: scores,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    min: 1,
                    max: 5
                }
            }
        }
    });
}

function renderCategoryChart(categoryData) {
    const ctx = document.getElementById('category-chart');
    if (!ctx) return;

    if (typeof Chart === 'undefined') {
        console.warn('Chart.js is not loaded');
        return;
    }

    if (categoryChart) {
        categoryChart.destroy();
        categoryChart = null;
    }

    const rows = Array.isArray(categoryData) ? categoryData : [];
    const labels = rows.map(d => d.category_name || d.categoryName || '—');
    const scores = rows.map(d => {
        const v = d.avg_score ?? d.avgScore;
        const n = typeof v === 'number' ? v : parseFloat(v);
        return isNaN(n) ? 0 : n;
    });

    categoryChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Average Score',
                data: scores,
                backgroundColor: scores.map(s => s >= 4 ? '#10b981' : s >= 3 ? '#3b82f6' : s >= 2 ? '#f59e0b' : '#ef4444')
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: false,
                    min: 1,
                    max: 5
                }
            }
        }
    });
}

async function loadAIFeedback(employeeId) {
    try {
        const response = await fetch(`/performance/reviews/employee/${employeeId}/stats`);
        const stats = await response.json();
        
        // Get latest review for AI feedback
        const reviewsResponse = await fetch(`/performance/reviews?employee_id=${employeeId}&limit=1`);
        const reviewsData = await reviewsResponse.json();
        
        if (reviewsData.data && reviewsData.data.length > 0) {
            const latestReview = reviewsData.data[0];
            document.getElementById('ai-feedback-content').innerHTML = `<p>${latestReview.ai_feedback || 'No feedback available.'}</p>`;
        }
    } catch (error) {
        console.error('Error loading AI feedback:', error);
    }
}

async function loadReviewHistory() {
    try {
        const tbody = document.getElementById('reviews-tbody');
        if (!tbody) {
            console.warn('Reviews tbody element not found');
            return;
        }

        const response = await fetch('/performance/reviews', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Unknown error' }));
            console.error('Failed to load reviews:', response.status, errorData);
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error loading reviews: ${errorData.message || response.statusText}</td></tr>`;
            return;
        }

        const data = await response.json();
        console.log('Reviews data loaded:', data);
        
        // Handle paginated response
        const reviews = data.data || [];
        
        if (reviews.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted">No reviews found</td></tr>';
            return;
        }

        tbody.innerHTML = reviews.map(review => {
            const reviewDate = review.review_date ? new Date(review.review_date).toLocaleDateString() : '-';
            
            // Safely handle normalized_score - convert to number if needed
            let score = '-';
            if (review.normalized_score !== null && review.normalized_score !== undefined) {
                const numScore = typeof review.normalized_score === 'string' 
                    ? parseFloat(review.normalized_score) 
                    : review.normalized_score;
                if (!isNaN(numScore)) {
                    score = numScore.toFixed(1);
                }
            }
            
            const level = review.performance_level || '-';
            const levelClass = level !== '-' ? level.toLowerCase().replace(/\s+/g, '-') : 'average';
            
            return `
                <tr>
                    <td>${reviewDate}</td>
                    <td>${review.review_period || '-'}</td>
                    <td>${review.employee?.name || '-'}</td>
                    <td>${review.reviewer?.name || '-'}</td>
                    <td><strong>${score}</strong></td>
                    <td><span class="performance-badge badge-${levelClass}">${level}</span></td>
                    <td>
                        <button class="btn btn-sm btn-primary view-review-btn" data-review-id="${review.id}">
                            <i class="ri-eye-line"></i> View
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        // Attach view review listeners
        document.querySelectorAll('.view-review-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const reviewId = this.getAttribute('data-review-id');
                viewReview(reviewId);
            });
        });
    } catch (error) {
        console.error('Error loading review history:', error);
        const tbody = document.getElementById('reviews-tbody');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger">Error loading reviews: ${error.message}</td></tr>`;
        }
    }
}

async function viewReview(reviewId) {
    try {
        console.log('Loading review:', reviewId);
        
        const response = await fetch(`/performance/reviews/${reviewId}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Unknown error' }));
            console.error('Failed to load review:', response.status, errorData);
            showToast('Error loading review: ' + (errorData.message || response.statusText), 'error');
            return;
        }

        const review = await response.json();
        console.log('Review data loaded:', review);
        console.log('Review items:', review.reviewItems || review.review_items);
        console.log('Employee:', review.employee);
        console.log('Reviewer:', review.reviewer);
        
        const modal = new bootstrap.Modal(document.getElementById('viewReviewModal'));
        const content = document.getElementById('review-details-content');
        
        if (!content) {
            console.error('Review details content element not found');
            return;
        }
        
        // Safely handle normalized_score (check both camelCase and snake_case)
        let scoreDisplay = '-';
        const normalizedScore = review.normalized_score !== null && review.normalized_score !== undefined 
            ? review.normalized_score 
            : (review.normalizedScore !== null && review.normalizedScore !== undefined ? review.normalizedScore : null);
            
        if (normalizedScore !== null && normalizedScore !== undefined) {
            const numScore = typeof normalizedScore === 'string' 
                ? parseFloat(normalizedScore) 
                : normalizedScore;
            if (!isNaN(numScore)) {
                scoreDisplay = numScore.toFixed(1);
            }
        }
        
        // Handle performance_level (check both camelCase and snake_case)
        const level = review.performance_level || review.performanceLevel || '-';
        const levelClass = level !== '-' ? level.toLowerCase().replace(/\s+/g, '-') : 'average';
        
        // Handle both camelCase and snake_case for employee/reviewer
        const employee = review.employee || {};
        const reviewer = review.reviewer || {};
        const reviewDate = review.review_date || review.reviewDate;
        const reviewPeriod = review.review_period || review.reviewPeriod;
        
        let html = `
            <div class="mb-3">
                <strong>Employee:</strong> ${employee.name || '-'}<br>
                <strong>Reviewer:</strong> ${reviewer.name || '-'}<br>
                <strong>Date:</strong> ${reviewDate ? new Date(reviewDate).toLocaleDateString() : '-'}<br>
                <strong>Period:</strong> ${reviewPeriod || '-'}<br>
                <strong>Score:</strong> <strong>${scoreDisplay}</strong><br>
                <strong>Level:</strong> <span class="performance-badge badge-${levelClass}">${level}</span>
            </div>
            <hr>
            <h6>Ratings:</h6>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Question</th>
                            <th>Rating</th>
                            <th>Comments/Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        // Handle both camelCase (Laravel JSON) and snake_case (Laravel array) property names
        // When using toArray(), Laravel converts to snake_case
        const reviewItems = review.review_items || review.reviewItems || [];
        
        console.log('Review items found:', reviewItems.length);
        
        if (reviewItems.length === 0) {
            html += `
                <tr>
                    <td colspan="4" class="text-center text-muted">No ratings found</td>
                </tr>
            `;
        } else {
            reviewItems.forEach((item, index) => {
                // Handle both camelCase and snake_case for nested properties
                const checklistItem = item.checklist_item || item.checklistItem || {};
                const category = checklistItem.category || {};
                
                console.log(`Item ${index}:`, {
                    rating: item.rating,
                    question: checklistItem.question,
                    category: category.name
                });
                
                html += `
                    <tr>
                        <td>${category.name || '-'}</td>
                        <td>${checklistItem.question || '-'}</td>
                        <td><strong>${item.rating || '-'}</strong></td>
                        <td>${item.comment || '-'}</td>
                    </tr>
                `;
            });
        }

        html += `
                    </tbody>
                </table>
            </div>
        `;

        // Handle feedback fields (check both camelCase and snake_case)
        const overallFeedback = review.overall_feedback || review.overallFeedback;
        const aiFeedback = review.ai_feedback || review.aiFeedback;
        
        if (overallFeedback) {
            html += `<hr><h6>Overall Feedback:</h6><p>${overallFeedback}</p>`;
        }

        if (aiFeedback) {
            html += `<hr><h6>AI Feedback:</h6><p>${aiFeedback}</p>`;
        }

        content.innerHTML = html;
        modal.show();
    } catch (error) {
        console.error('Error loading review:', error);
        showToast('Error loading review details', 'error');
    }
}

// Delete Functions
async function deleteCategory(categoryId) {
    try {
        const response = await fetch(`/performance/checklist/category/${categoryId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const result = await response.json();
        
        if (result.success) {
            showToast('Category deleted successfully', 'success');
            loadChecklist(currentDesignationId);
        } else {
            showToast(result.message || 'Error deleting category', 'error');
        }
    } catch (error) {
        console.error('Error deleting category:', error);
        showToast('Error deleting category', 'error');
    }
}

async function deleteItem(itemId) {
    try {
        const response = await fetch(`/performance/checklist/item/${itemId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });

        const result = await response.json();
        
        if (result.success) {
            showToast('Item deleted successfully', 'success');
            loadChecklist(currentDesignationId);
        } else {
            showToast(result.message || 'Error deleting item', 'error');
        }
    } catch (error) {
        console.error('Error deleting item:', error);
        showToast('Error deleting item', 'error');
    }
}

// Helper Functions
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'success' ? 'success' : 'danger'} position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.textContent = message;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

// Edit operations
async function loadDesignationData(id) {
    try {
        const response = await fetch(`/performance/designations`);
        const designations = await response.json();
        const designation = designations.find(d => d.id == id);
        
        if (designation) {
            document.getElementById('designation-id').value = designation.id;
            document.getElementById('designation-name').value = designation.name;
            document.getElementById('designation-description').value = designation.description || '';
            document.getElementById('designation-is-active').checked = designation.is_active;
        }
    } catch (error) {
        console.error('Error loading designation:', error);
    }
}

async function loadCategoryData(id) {
    try {
        const response = await fetch(`/performance/checklist/category/${id}`);
        const category = await response.json();
        
        document.getElementById('category-id').value = category.id;
        document.getElementById('category-designation-id').value = category.designation_id;
        document.getElementById('category-name').value = category.name;
        document.getElementById('category-description').value = category.description || '';
        document.getElementById('category-order').value = category.order || 0;
    } catch (error) {
        console.error('Error loading category:', error);
        showToast('Error loading category data', 'error');
    }
}

async function editCategory(id) {
    openCategoryModal(id);
    await loadCategoryData(id);
}

async function loadItemData(id) {
    try {
        const response = await fetch(`/performance/checklist/item/${id}`);
        const item = await response.json();
        
        document.getElementById('item-id').value = item.id;
        document.getElementById('item-category-id').value = item.category_id;
        document.getElementById('item-question').value = item.question;
        document.getElementById('item-weight').value = item.weight;
        document.getElementById('item-order').value = item.order || 0;
    } catch (error) {
        console.error('Error loading item:', error);
        showToast('Error loading item data', 'error');
    }
}

async function editItem(id) {
    openItemModal(id);
    await loadItemData(id);
}

// Helper function to get current user ID
function getCurrentUserId() {
    // Try to get from meta tag or data attribute
    const metaUserId = document.querySelector('meta[name="user-id"]');
    if (metaUserId) {
        return metaUserId.getAttribute('content');
    }
    // Fallback: try to get from page data
    const pageData = document.querySelector('[data-user-id]');
    if (pageData) {
        return pageData.getAttribute('data-user-id');
    }
    return null;
}

// Export Checklist to CSV
function handleExportChecklist() {
    if (!currentDesignationId) {
        showToast('Please select a designation first', 'error');
        return;
    }

    try {
        const url = `/performance/checklist/export/${encodeURIComponent(currentDesignationId)}`;
        window.location.href = url;
        showToast('Export started. File will download shortly.', 'success');
    } catch (error) {
        console.error('Error exporting checklist:', error);
        showToast('Error exporting checklist: ' + error.message, 'error');
    }
}

// Open Import CSV Modal
function openImportCsvModal() {
    if (!currentDesignationId) {
        showToast('Please select a designation first', 'error');
        return;
    }

    const modal = new bootstrap.Modal(document.getElementById('importCsvModal'));
    document.getElementById('import-csv-form').reset();
    document.getElementById('import-progress').style.display = 'none';
    document.getElementById('import-results').style.display = 'none';
    document.getElementById('import-results').innerHTML = '';
    modal.show();
}

// Handle CSV Import
async function handleImportCsv(e) {
    e.preventDefault();

    const fileInput = document.getElementById('csv-file-input');
    const updateExisting = document.getElementById('csv-update-existing').checked;
    const progressDiv = document.getElementById('import-progress');
    const resultsDiv = document.getElementById('import-results');

    if (!fileInput.files || fileInput.files.length === 0) {
        showToast('Please select a CSV file', 'error');
        return;
    }

    if (!currentDesignationId) {
        showToast('Please select a designation first', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('csv_file', fileInput.files[0]);
    formData.append('update_existing', updateExisting ? '1' : '0');

    progressDiv.style.display = 'block';
    resultsDiv.style.display = 'none';

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!csrfToken) {
            showToast('CSRF token not found', 'error');
            return;
        }

        const response = await fetch(`/performance/checklist/import/${encodeURIComponent(currentDesignationId)}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken.content,
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: formData
        });

        progressDiv.style.display = 'none';

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({ message: 'Unknown error' }));
            showToast(errorData.message || 'Error importing CSV', 'error');
            return;
        }

        const result = await response.json();

        if (result.success) {
            let resultsHtml = `
                <div class="alert alert-success">
                    <i class="ri-check-line me-2"></i>
                    <strong>Import Successful!</strong><br>
                    <ul class="mb-0 mt-2">
                        <li>Items imported: ${result.data.imported}</li>
                        <li>Items updated: ${result.data.updated}</li>
                        ${result.data.errors > 0 ? `<li class="text-warning">Errors: ${result.data.errors}</li>` : ''}
                    </ul>
                </div>
            `;

            if (result.data.error_messages && result.data.error_messages.length > 0) {
                resultsHtml += `
                    <div class="alert alert-warning mt-2">
                        <strong>Warnings:</strong>
                        <ul class="mb-0 mt-2">
                            ${result.data.error_messages.map(msg => `<li>${msg}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            resultsDiv.innerHTML = resultsHtml;
            resultsDiv.style.display = 'block';

            showToast(result.message, 'success');

            // Reload checklist after successful import
            setTimeout(() => {
                loadChecklist(currentDesignationId);
                bootstrap.Modal.getInstance(document.getElementById('importCsvModal')).hide();
            }, 2000);
        } else {
            showToast(result.message || 'Error importing CSV', 'error');
        }
    } catch (error) {
        progressDiv.style.display = 'none';
        console.error('Error importing CSV:', error);
        showToast('Error importing CSV: ' + error.message, 'error');
    }
}
