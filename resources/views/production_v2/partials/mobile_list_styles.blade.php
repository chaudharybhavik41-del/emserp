<style>
@media (max-width: 767.98px) {
    .pv2-mobile-table {
        display: none;
    }

    .pv2-mobile-list {
        display: grid;
        gap: 0.75rem;
    }

    .pv2-mobile-card {
        border: 1px solid var(--bs-border-color);
        border-radius: 0.9rem;
        background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(248,249,250,0.92));
        padding: 0.9rem;
        box-shadow: 0 0.4rem 1rem rgba(17, 24, 39, 0.06);
    }

    .pv2-mobile-card__head {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        align-items: start;
        margin-bottom: 0.65rem;
    }

    .pv2-mobile-card__title {
        font-weight: 700;
    }

    .pv2-mobile-card__meta {
        display: grid;
        gap: 0.4rem;
    }

    .pv2-mobile-card__row {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
        font-size: 0.92rem;
    }

    .pv2-mobile-card__label {
        color: var(--bs-secondary-color);
    }
}

@media (min-width: 768px) {
    .pv2-mobile-list {
        display: none;
    }
}
</style>
