(function () {
    var configNode = document.getElementById('excel-manager-config');
    if (!configNode) {
        return;
    }

    var config = JSON.parse(configNode.textContent || '{}');
    var state = {
        importData: config.import || null,
        worksheetId: config.import && config.import.selected_worksheet_id ? String(config.import.selected_worksheet_id) : '',
        page: 1,
        perPage: 25,
        search: '',
        sort: '',
        direction: 'asc',
        controller: null,
        searchTimer: null
    };

    var dropzone = document.getElementById('excel-dropzone');
    var fileInput = document.getElementById('excel-file');
    var uploadCard = document.getElementById('excel-upload-card');
    var workspace = document.getElementById('excel-workspace');
    var worksheetSelect = document.getElementById('excel-worksheet');
    var searchInput = document.getElementById('excel-search');
    var perPageSelect = document.getElementById('excel-per-page');
    var exportButton = document.getElementById('excel-export');
    var uploadAnother = document.getElementById('excel-upload-another');

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function endpoint(template, id) {
        return template.replace('__ID__', encodeURIComponent(id));
    }

    function showError(message) {
        var alert = document.getElementById('excel-alert');
        document.getElementById('excel-alert-text').textContent = message || 'Something went wrong.';
        alert.hidden = false;
    }

    function hideError() {
        document.getElementById('excel-alert').hidden = true;
    }

    document.getElementById('excel-alert-close').addEventListener('click', hideError);

    function setLoading(isLoading) {
        document.getElementById('excel-loading').hidden = !isLoading;
    }

    function selectedSheet() {
        if (!state.importData) {
            return null;
        }
        return state.importData.worksheets.find(function (sheet) {
            return String(sheet.id) === String(state.worksheetId);
        }) || null;
    }

    function renderSummary(filteredTotal) {
        var sheet = selectedSheet();
        document.getElementById('excel-filename').textContent = state.importData ? state.importData.original_filename : '—';
        document.getElementById('excel-filename').title = state.importData ? state.importData.original_filename : '';
        document.getElementById('excel-sheet-count').textContent = state.importData ? String(state.importData.total_worksheets) : '—';
        var total = sheet ? sheet.total_rows : 0;
        document.getElementById('excel-record-count').textContent = total.toLocaleString();
        exportButton.disabled = !sheet;
        if (typeof filteredTotal === 'number' && sheet && filteredTotal !== total) {
            document.getElementById('excel-record-count').textContent = filteredTotal.toLocaleString() + ' / ' + total.toLocaleString();
        }
    }

    function fillWorksheets() {
        worksheetSelect.innerHTML = '';
        if (!state.importData) {
            return;
        }
        state.importData.worksheets.forEach(function (sheet) {
            var option = document.createElement('option');
            option.value = String(sheet.id);
            option.textContent = sheet.worksheet_name;
            worksheetSelect.appendChild(option);
        });
        if (state.worksheetId) {
            worksheetSelect.value = String(state.worksheetId);
        }
    }

    function showWorkspace() {
        uploadCard.hidden = true;
        workspace.hidden = false;
        uploadAnother.hidden = false;
        renderSummary();
        fillWorksheets();
    }

    function showUploader() {
        uploadCard.hidden = false;
        fileInput.value = '';
        dropzone.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function renderTable(payload) {
        var head = document.getElementById('excel-head');
        var body = document.getElementById('excel-body');
        var empty = document.getElementById('excel-empty');
        var scroll = document.getElementById('excel-scroll');
        var headers = payload.worksheet.headers || [];
        var records = payload.records || [];

        head.innerHTML = '';
        body.innerHTML = '';

        var sheet = selectedSheet();
        if (sheet) {
            sheet.total_rows = payload.total_records;
            sheet.headers = headers;
        }
        renderSummary(payload.pagination.total);

        if (!records.length) {
            scroll.hidden = true;
            empty.hidden = false;
            empty.textContent = state.search
                ? 'No records match your search.'
                : 'This worksheet has no records.';
            renderPager(payload.pagination);
            return;
        }

        scroll.hidden = false;
        empty.hidden = true;

        var headerRow = document.createElement('tr');
        headers.forEach(function (header) {
            var cell = document.createElement('th');
            cell.scope = 'col';
            cell.dataset.header = header;
            if (state.sort === header) {
                cell.classList.add('is-sorted');
            }
            var label = document.createElement('span');
            label.textContent = header;
            var mark = document.createElement('span');
            mark.className = 'excel-sort-mark';
            mark.textContent = state.sort === header ? (state.direction === 'asc' ? ' ▲' : ' ▼') : '';
            cell.appendChild(label);
            cell.appendChild(mark);
            cell.title = 'Sort by ' + header;
            headerRow.appendChild(cell);
        });
        head.appendChild(headerRow);

        records.forEach(function (record) {
            var row = document.createElement('tr');
            var values = record.values || {};
            headers.forEach(function (header) {
                var cell = document.createElement('td');
                var value = Object.prototype.hasOwnProperty.call(values, header) ? values[header] : null;
                if (value === null || value === undefined || value === '') {
                    cell.classList.add('is-blank');
                    cell.textContent = '';
                } else {
                    cell.textContent = String(value);
                    cell.title = String(value);
                }
                row.appendChild(cell);
            });
            body.appendChild(row);
        });

        renderPager(payload.pagination);
    }

    function renderPager(pagination) {
        var info = document.getElementById('excel-page-info');
        var pager = document.getElementById('excel-pager');
        pager.innerHTML = '';

        var total = pagination.total || 0;
        if (!total) {
            info.textContent = state.search ? '0 matching records' : '0 records';
            return;
        }

        var from = pagination.from || 0;
        var to = pagination.to || 0;
        var text = 'Showing ' + from.toLocaleString() + '–' + to.toLocaleString() + ' of ' + total.toLocaleString() + ' records';
        text += ' · Page ' + pagination.current_page + ' of ' + pagination.last_page;
        if (state.search && pagination.total !== (selectedSheet() ? selectedSheet().total_rows : pagination.total)) {
            text += ' (filtered)';
        }
        info.textContent = text;

        addPageButton('Prev', pagination.current_page - 1, pagination.current_page === 1);
        var start = Math.max(1, pagination.current_page - 2);
        var end = Math.min(pagination.last_page, start + 4);
        start = Math.max(1, end - 4);
        for (var page = start; page <= end; page++) {
            addPageButton(String(page), page, false, page === pagination.current_page);
        }
        addPageButton('Next', pagination.current_page + 1, pagination.current_page === pagination.last_page);
    }

    function addPageButton(label, page, disabled, active) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm ' + (active ? 'btn-primary' : 'btn-light');
        button.textContent = label;
        button.disabled = disabled;
        if (!disabled) {
            button.addEventListener('click', function () {
                state.page = page;
                loadData();
            });
        }
        document.getElementById('excel-pager').appendChild(button);
    }

    function loadData() {
        if (!state.worksheetId) {
            return;
        }
        if (state.controller) {
            state.controller.abort();
        }
        state.controller = new AbortController();
        setLoading(true);
        hideError();

        var params = new URLSearchParams({
            page: String(state.page),
            per_page: String(state.perPage)
        });
        if (state.search) {
            params.set('search', state.search);
        }
        if (state.sort) {
            params.set('sort', state.sort);
            params.set('direction', state.direction);
        }

        fetch(endpoint(config.dataUrl, state.worksheetId) + '?' + params.toString(), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: state.controller.signal
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok) {
                    throw new Error(payload.message || 'The worksheet could not be loaded.');
                }
                return payload;
            });
        }).then(function (payload) {
            renderTable(payload);
        }).catch(function (error) {
            if (error.name === 'AbortError') {
                return;
            }
            showError(error.message);
        }).finally(function () {
            setLoading(false);
        });
    }

    function applyImport(importData) {
        state.importData = importData;
        state.worksheetId = importData.selected_worksheet_id ? String(importData.selected_worksheet_id) : '';
        state.page = 1;
        state.search = '';
        state.sort = '';
        state.direction = 'asc';
        searchInput.value = '';
        showWorkspace();
        if (state.worksheetId) {
            loadData();
        }
    }

    function uploadFile(file) {
        hideError();
        var name = (file && file.name ? file.name : '').toLowerCase();
        if (!name.endsWith('.xlsx') && !name.endsWith('.xls')) {
            showError('Choose an .xlsx or .xls workbook.');
            return;
        }
        if (file.size > 50 * 1024 * 1024) {
            showError('The file must be 50 MB or smaller.');
            return;
        }

        var body = new FormData();
        body.append('file', file);
        var progressWrap = document.getElementById('excel-progress-wrap');
        var progress = document.getElementById('excel-progress');
        progressWrap.hidden = false;
        progress.style.width = '0%';
        progress.textContent = 'Uploading…';
        exportButton.disabled = true;

        var request = new XMLHttpRequest();
        request.open('POST', config.importUrl);
        request.setRequestHeader('X-CSRF-TOKEN', csrfToken());
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.upload.onprogress = function (event) {
            if (!event.lengthComputable) {
                return;
            }
            var percent = Math.round((event.loaded / event.total) * 100);
            progress.style.width = percent + '%';
            progress.textContent = percent < 100 ? 'Uploading ' + percent + '%' : 'Reading worksheets…';
        };
        request.onload = function () {
            progressWrap.hidden = true;
            var payload = {};
            try {
                payload = JSON.parse(request.responseText || '{}');
            } catch (error) {
                showError('The server did not return a readable response.');
                return;
            }
            if (request.status < 200 || request.status >= 300) {
                var message = payload.message;
                if (!message && payload.errors && payload.errors.file) {
                    message = payload.errors.file[0];
                }
                showError(message || 'The workbook could not be imported.');
                return;
            }
            applyImport(payload.import);
        };
        request.onerror = function () {
            progressWrap.hidden = true;
            showError('The upload failed. Check your connection and try again.');
        };
        request.send(body);
    }

    dropzone.addEventListener('click', function (event) {
        if (event.target.id !== 'excel-browse') {
            fileInput.click();
        }
    });
    document.getElementById('excel-browse').addEventListener('click', function (event) {
        event.stopPropagation();
        fileInput.click();
    });
    dropzone.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            fileInput.click();
        }
    });
    fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files[0]) {
            uploadFile(fileInput.files[0]);
        }
    });

    ['dragenter', 'dragover'].forEach(function (name) {
        dropzone.addEventListener(name, function (event) {
            event.preventDefault();
            dropzone.classList.add('is-dragover');
        });
    });
    ['dragleave', 'drop'].forEach(function (name) {
        dropzone.addEventListener(name, function (event) {
            event.preventDefault();
            dropzone.classList.remove('is-dragover');
        });
    });
    dropzone.addEventListener('drop', function (event) {
        var file = event.dataTransfer && event.dataTransfer.files ? event.dataTransfer.files[0] : null;
        if (file) {
            uploadFile(file);
        }
    });

    worksheetSelect.addEventListener('change', function () {
        state.worksheetId = worksheetSelect.value;
        state.page = 1;
        state.search = '';
        state.sort = '';
        state.direction = 'asc';
        searchInput.value = '';
        loadData();
    });

    searchInput.addEventListener('input', function () {
        clearTimeout(state.searchTimer);
        state.searchTimer = setTimeout(function () {
            state.search = searchInput.value.trim();
            state.page = 1;
            loadData();
        }, 300);
    });

    perPageSelect.addEventListener('change', function () {
        state.perPage = parseInt(perPageSelect.value, 10) || 25;
        state.page = 1;
        loadData();
    });

    document.getElementById('excel-head').addEventListener('click', function (event) {
        var cell = event.target.closest('th');
        if (!cell || !cell.dataset.header) {
            return;
        }
        var header = cell.dataset.header;
        if (state.sort === header) {
            state.direction = state.direction === 'asc' ? 'desc' : 'asc';
        } else {
            state.sort = header;
            state.direction = 'asc';
        }
        state.page = 1;
        loadData();
    });

    exportButton.addEventListener('click', function () {
        if (!state.worksheetId) {
            return;
        }
        exportButton.disabled = true;
        fetch(endpoint(config.exportUrl, state.worksheetId), {
            headers: { 'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            var type = response.headers.get('content-type') || '';
            if (!response.ok || type.indexOf('application/json') !== -1) {
                return response.json().then(function (payload) {
                    throw new Error(payload.message || 'The worksheet could not be exported.');
                });
            }
            var disposition = response.headers.get('content-disposition') || '';
            var match = disposition.match(/filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i);
            var filename = match ? decodeURIComponent((match[1] || match[2] || '').trim()) : 'worksheet.xlsx';
            return response.blob().then(function (blob) {
                var link = document.createElement('a');
                var url = URL.createObjectURL(blob);
                link.href = url;
                link.download = filename || 'worksheet.xlsx';
                document.body.appendChild(link);
                link.click();
                link.remove();
                URL.revokeObjectURL(url);
            });
        }).catch(function (error) {
            showError(error.message);
        }).finally(function () {
            exportButton.disabled = !state.worksheetId;
        });
    });

    uploadAnother.addEventListener('click', showUploader);

    if (state.importData && state.importData.worksheets && state.importData.worksheets.length) {
        showWorkspace();
        if (state.worksheetId) {
            loadData();
        }
    }
})();
