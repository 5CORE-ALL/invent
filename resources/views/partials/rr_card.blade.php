@php
    // Check if content exists (stored in role field as combined content)
    $hasContent = $userRR && !empty(trim(strip_tags($userRR->role ?? '')));
    // For backward compatibility, also check separate fields
    $hasSeparateContent = $userRR && (
        !empty(trim(strip_tags($userRR->role ?? ''))) ||
        !empty(trim(strip_tags($userRR->responsibilities ?? ''))) ||
        !empty(trim(strip_tags($userRR->goals ?? '')))
    );
    $displayContent = $userRR ? $userRR->role : '';
    // If role is empty but we have separate fields, combine them
    if (empty($displayContent) && $userRR) {
        $parts = [];
        if (!empty(trim(strip_tags($userRR->role ?? '')))) $parts[] = '<h3>Role</h3>' . $userRR->role;
        if (!empty(trim(strip_tags($userRR->responsibilities ?? '')))) $parts[] = '<h3>Responsibilities</h3>' . $userRR->responsibilities;
        if (!empty(trim(strip_tags($userRR->goals ?? '')))) $parts[] = '<h3>Goals</h3>' . $userRR->goals;
        $displayContent = implode('<br><br>', $parts);
    }
@endphp

<div class="rr-container" style="opacity: 0; transition: opacity 0.3s ease-in;">
    @if($userRR && ($hasContent || $hasSeparateContent))
        {{-- Single Combined Content Card --}}
        <div class="card mb-3 shadow-sm border-0" style="border-left: 4px solid #007bff !important;">
            <div class="card-body">
                <div class="d-flex align-items-start">
                    <div class="rr-icon-wrapper me-3" style="width: 48px; height: 48px; background: linear-gradient(135deg, #007bff 0%, #0056b3 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,123,255,0.3); flex-shrink: 0;">
                        <i class="fas fa-user-tie text-white" style="font-size: 20px;"></i>
                    </div>
                    <div class="flex-grow-1" style="min-width: 0;">
                        <div class="mb-0" style="font-size: 14px; color: #495057; line-height: 1.8; word-wrap: break-word;">
                            {!! $displayContent !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- No R&R Assigned --}}
        <div class="card shadow-sm border-0" style="border-left: 4px solid #6c757d !important;">
            <div class="card-body text-center py-5">
                <div class="rr-icon-wrapper mb-3 mx-auto" style="width: 64px; height: 64px; background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(108,117,125,0.3);">
                    <i class="fas fa-info-circle text-white" style="font-size: 28px;"></i>
                </div>
                <h6 class="mb-1 text-muted" style="font-size: 14px; font-weight: 600;">No Role & Responsibility Assigned</h6>
                <p class="mb-0 text-muted" style="font-size: 12px;">
                    @if($userName)
                        {{ $userName }} has not been assigned a role, responsibilities, or goals yet.
                    @else
                        Please select a user to view their Role & Responsibility information.
                    @endif
                </p>
            </div>
        </div>
    @endif
</div>

<script>
    // Fade in animation
    document.addEventListener('DOMContentLoaded', function() {
        const rrContainer = document.querySelector('.rr-container');
        if (rrContainer) {
            setTimeout(function() {
                rrContainer.style.opacity = '1';
            }, 50);
        }
    });
</script>
