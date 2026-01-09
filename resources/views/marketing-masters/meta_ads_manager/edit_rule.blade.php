@extends('layouts.vertical', ['title' => 'Meta Ads Manager - Edit Automation Rule', 'mode' => $mode ?? '', 'demo' => $demo ?? ''])

@section('css')
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
@endsection

@section('content')
    @include('layouts.shared/page-title', ['sub_title' => 'Marketing Masters', 'page_title' => 'Edit Automation Rule'])

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('meta.ads.manager.automation.update', $rule->id) }}" method="POST" id="ruleForm">
                        @csrf
                        @method('PUT')
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Rule Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $rule->name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label for="entity_type" class="form-label">Entity Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="entity_type" name="entity_type" required>
                                    <option value="">Select...</option>
                                    <option value="campaign" {{ old('entity_type', $rule->entity_type) === 'campaign' ? 'selected' : '' }}>Campaign</option>
                                    <option value="adset" {{ old('entity_type', $rule->entity_type) === 'adset' ? 'selected' : '' }}>Ad Set</option>
                                    <option value="ad" {{ old('entity_type', $rule->entity_type) === 'ad' ? 'selected' : '' }}>Ad</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="2">{{ old('description', $rule->description) }}</textarea>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label for="schedule" class="form-label">Schedule</label>
                                <select class="form-select" id="schedule" name="schedule">
                                    <option value="hourly" {{ old('schedule', $rule->schedule) === 'hourly' ? 'selected' : '' }}>Hourly</option>
                                    <option value="daily" {{ old('schedule', $rule->schedule) === 'daily' || !$rule->schedule ? 'selected' : '' }}>Daily</option>
                                    <option value="weekly" {{ old('schedule', $rule->schedule) === 'weekly' ? 'selected' : '' }}>Weekly</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $rule->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">Active</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="dry_run_mode" name="dry_run_mode" value="1" {{ old('dry_run_mode', $rule->dry_run_mode) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="dry_run_mode">Dry Run Mode (test without making changes)</label>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <h5>Conditions</h5>
                        <p class="text-muted">Define when this rule should trigger</p>
                        
                        <div id="conditionsContainer">
                            @foreach(old('conditions', $rule->conditions ?? []) as $index => $condition)
                                <div class="condition-item mb-3 p-3 border rounded">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label">Field</label>
                                            <select class="form-select condition-field" name="conditions[{{ $index }}][field]" required>
                                                <option value="">Select...</option>
                                                <optgroup label="Metrics">
                                                    <option value="spend" {{ ($condition['field'] ?? '') === 'spend' ? 'selected' : '' }}>Spend</option>
                                                    <option value="impressions" {{ ($condition['field'] ?? '') === 'impressions' ? 'selected' : '' }}>Impressions</option>
                                                    <option value="clicks" {{ ($condition['field'] ?? '') === 'clicks' ? 'selected' : '' }}>Clicks</option>
                                                    <option value="ctr" {{ ($condition['field'] ?? '') === 'ctr' ? 'selected' : '' }}>CTR (%)</option>
                                                    <option value="cpc" {{ ($condition['field'] ?? '') === 'cpc' ? 'selected' : '' }}>CPC</option>
                                                    <option value="cpm" {{ ($condition['field'] ?? '') === 'cpm' ? 'selected' : '' }}>CPM</option>
                                                    <option value="reach" {{ ($condition['field'] ?? '') === 'reach' ? 'selected' : '' }}>Reach</option>
                                                </optgroup>
                                                <optgroup label="Status">
                                                    <option value="status" {{ ($condition['field'] ?? '') === 'status' ? 'selected' : '' }}>Status</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Operator</label>
                                            <select class="form-select condition-operator" name="conditions[{{ $index }}][operator]" required>
                                                <option value=">" {{ ($condition['operator'] ?? '') === '>' ? 'selected' : '' }}>Greater Than (>)</option>
                                                <option value="<" {{ ($condition['operator'] ?? '') === '<' ? 'selected' : '' }}>Less Than (<)</option>
                                                <option value=">=" {{ ($condition['operator'] ?? '') === '>=' ? 'selected' : '' }}>Greater or Equal (>=)</option>
                                                <option value="<=" {{ ($condition['operator'] ?? '') === '<=' ? 'selected' : '' }}>Less or Equal (<=)</option>
                                                <option value="=" {{ ($condition['operator'] ?? '') === '=' ? 'selected' : '' }}>Equals (=)</option>
                                                <option value="!=" {{ ($condition['operator'] ?? '') === '!=' ? 'selected' : '' }}>Not Equals (!=)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Value</label>
                                            <input type="text" class="form-control condition-value" name="conditions[{{ $index }}][value]" value="{{ $condition['value'] ?? '' }}" required>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Time Period</label>
                                            <select class="form-select condition-aggregation" name="conditions[{{ $index }}][aggregation]">
                                                <option value="last_1d" {{ ($condition['aggregation'] ?? 'last_7d') === 'last_1d' ? 'selected' : '' }}>Last 1 Day</option>
                                                <option value="last_7d" {{ ($condition['aggregation'] ?? 'last_7d') === 'last_7d' ? 'selected' : '' }}>Last 7 Days</option>
                                                <option value="last_30d" {{ ($condition['aggregation'] ?? 'last_7d') === 'last_30d' ? 'selected' : '' }}>Last 30 Days</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Type</label>
                                            <input type="hidden" name="conditions[{{ $index }}][type]" value="{{ $condition['type'] ?? 'metric' }}">
                                            <input type="text" class="form-control" value="Metric" disabled>
                                        </div>
                                        <div class="col-md-1">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-sm btn-danger remove-condition">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" id="addCondition">+ Add Condition</button>

                        <hr>

                        <h5>Actions</h5>
                        <p class="text-muted">Define what to do when conditions are met</p>
                        
                        <div id="actionsContainer">
                            @foreach(old('actions', $rule->actions ?? []) as $index => $action)
                                <div class="action-item mb-3 p-3 border rounded">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Action Type</label>
                                            <select class="form-select action-type" name="actions[{{ $index }}][type]" required>
                                                <option value="">Select...</option>
                                                <option value="pause" {{ ($action['type'] ?? '') === 'pause' ? 'selected' : '' }}>Pause</option>
                                                <option value="resume" {{ ($action['type'] ?? '') === 'resume' ? 'selected' : '' }}>Resume</option>
                                                <option value="update_budget" {{ ($action['type'] ?? '') === 'update_budget' ? 'selected' : '' }}>Update Budget</option>
                                                <option value="update_status" {{ ($action['type'] ?? '') === 'update_status' ? 'selected' : '' }}>Update Status</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 action-value-container" style="display: {{ in_array($action['type'] ?? '', ['update_budget', 'update_status']) ? 'block' : 'none' }};">
                                            <label class="form-label">Value</label>
                                            <input type="text" class="form-control action-value" name="actions[{{ $index }}][value]" value="{{ $action['value'] ?? '' }}" placeholder="e.g., 100 (for budget in $)">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">&nbsp;</label>
                                            <button type="button" class="btn btn-sm btn-danger remove-action">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-secondary" id="addAction">+ Add Action</button>

                        <hr>

                        <div class="d-flex justify-content-between">
                            <a href="{{ route('meta.ads.manager.automation') }}" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Rule</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        let conditionIndex = {{ count(old('conditions', $rule->conditions ?? [])) }};
        let actionIndex = {{ count(old('actions', $rule->actions ?? [])) }};

        // Add condition
        $('#addCondition').on('click', function() {
            const html = `
                <div class="condition-item mb-3 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-3">
                            <label class="form-label">Field</label>
                            <select class="form-select condition-field" name="conditions[${conditionIndex}][field]" required>
                                <option value="">Select...</option>
                                <optgroup label="Metrics">
                                    <option value="spend">Spend</option>
                                    <option value="impressions">Impressions</option>
                                    <option value="clicks">Clicks</option>
                                    <option value="ctr">CTR (%)</option>
                                    <option value="cpc">CPC</option>
                                    <option value="cpm">CPM</option>
                                    <option value="reach">Reach</option>
                                </optgroup>
                                <optgroup label="Status">
                                    <option value="status">Status</option>
                                </optgroup>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Operator</label>
                            <select class="form-select condition-operator" name="conditions[${conditionIndex}][operator]" required>
                                <option value=">">Greater Than (>)</option>
                                <option value="<">Less Than (<)</option>
                                <option value=">=">Greater or Equal (>=)</option>
                                <option value="<=">Less or Equal (<=)</option>
                                <option value="=">Equals (=)</option>
                                <option value="!=">Not Equals (!=)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Value</label>
                            <input type="text" class="form-control condition-value" name="conditions[${conditionIndex}][value]" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Time Period</label>
                            <select class="form-select condition-aggregation" name="conditions[${conditionIndex}][aggregation]">
                                <option value="last_1d">Last 1 Day</option>
                                <option value="last_7d" selected>Last 7 Days</option>
                                <option value="last_30d">Last 30 Days</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Type</label>
                            <input type="hidden" name="conditions[${conditionIndex}][type]" value="metric">
                            <input type="text" class="form-control" value="Metric" disabled>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-sm btn-danger remove-condition">Remove</button>
                        </div>
                    </div>
                </div>
            `;
            $('#conditionsContainer').append(html);
            conditionIndex++;
        });

        // Remove condition
        $(document).on('click', '.remove-condition', function() {
            if ($('.condition-item').length > 1) {
                $(this).closest('.condition-item').remove();
            } else {
                alert('At least one condition is required');
            }
        });

        // Add action
        $('#addAction').on('click', function() {
            const html = `
                <div class="action-item mb-3 p-3 border rounded">
                    <div class="row">
                        <div class="col-md-4">
                            <label class="form-label">Action Type</label>
                            <select class="form-select action-type" name="actions[${actionIndex}][type]" required>
                                <option value="">Select...</option>
                                <option value="pause">Pause</option>
                                <option value="resume">Resume</option>
                                <option value="update_budget">Update Budget</option>
                                <option value="update_status">Update Status</option>
                            </select>
                        </div>
                        <div class="col-md-4 action-value-container" style="display: none;">
                            <label class="form-label">Value</label>
                            <input type="text" class="form-control action-value" name="actions[${actionIndex}][value]" placeholder="e.g., 100 (for budget in $)">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-sm btn-danger remove-action">Remove</button>
                        </div>
                    </div>
                </div>
            `;
            $('#actionsContainer').append(html);
            actionIndex++;
        });

        // Remove action
        $(document).on('click', '.remove-action', function() {
            if ($('.action-item').length > 1) {
                $(this).closest('.action-item').remove();
            } else {
                alert('At least one action is required');
            }
        });

        // Show/hide action value based on type
        $(document).on('change', '.action-type', function() {
            const container = $(this).closest('.action-item').find('.action-value-container');
            const value = $(this).val();
            if (value === 'update_budget' || value === 'update_status') {
                container.show();
            } else {
                container.hide();
            }
        });
    </script>
@endsection

