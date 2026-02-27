@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Prevent accidental form submission when user presses Enter inside inputs/selects.
    // Applies only to forms explicitly marked with data-prevent-enter-submit="1".
    document.querySelectorAll('form[data-prevent-enter-submit="1"]').forEach(function (form) {
        form.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;

            const el = event.target;
            if (!el) return;

            const tag = (el.tagName || '').toLowerCase();
            const type = (el.type || '').toLowerCase();

            // Allow Enter inside textareas (new line).
            if (tag === 'textarea') return;

            // If user is focused on an actual submit button, don't block.
            if (type === 'submit' || type === 'button') return;

            // Block Enter-to-submit everywhere else.
            event.preventDefault();
        });
    });
});
</script>
@endpush
