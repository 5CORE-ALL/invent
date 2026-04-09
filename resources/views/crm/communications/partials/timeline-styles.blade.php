<style>
    .crm-timeline {
        list-style: none;
        margin: 0;
        padding: 0;
        position: relative;
    }

    .crm-timeline-item {
        position: relative;
        padding: 0 0 1.5rem 2.25rem;
    }

    .crm-timeline-item:last-child {
        padding-bottom: 0;
    }

    .crm-timeline-item::before {
        content: '';
        position: absolute;
        left: 0.55rem;
        top: 1.35rem;
        bottom: 0;
        width: 2px;
        background: var(--bs-border-color);
    }

    .crm-timeline-item:last-child::before {
        display: none;
    }

    .crm-timeline-marker {
        position: absolute;
        left: 0;
        top: 0.15rem;
        width: 1.15rem;
        height: 1.15rem;
        border-radius: 50%;
        background: var(--bs-body-bg);
        border: 3px solid var(--bs-primary);
        box-shadow: 0 0 0 2px var(--bs-body-bg);
        z-index: 1;
    }

    .crm-timeline-marker--follow-up {
        border-color: var(--bs-primary);
    }

    .crm-timeline-marker--communication {
        border-color: var(--bs-info);
    }

    .crm-timeline-marker--status {
        border-color: var(--bs-warning);
    }

    .crm-timeline-card {
        border-left: 3px solid var(--bs-border-color);
    }

    .crm-timeline-item--follow_up .crm-timeline-card {
        border-left-color: var(--bs-primary);
    }

    .crm-timeline-item--communication .crm-timeline-card {
        border-left-color: var(--bs-info);
    }

    .crm-timeline-item--status_change .crm-timeline-card {
        border-left-color: var(--bs-warning);
    }
}
</style>
