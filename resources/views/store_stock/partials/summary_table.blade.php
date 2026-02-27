


@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const form = document.getElementById('filter-form');
            const tableContainer = document.getElementById('table-container');
            let timeout = null;

            function loadData(url = null) {

                const params = new URLSearchParams(new FormData(form)).toString();
                const fetchUrl = url ?? "{{ route('store-stock-summary.index') }}?" + params;

                fetch(fetchUrl, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(res => res.text())
                    .then(html => {
                        tableContainer.innerHTML = html;
                    });
            }

            // Select & checkbox change
            form.querySelectorAll('select, input[type="checkbox"]').forEach(el => {
                el.addEventListener('change', () => loadData());
            });

            // Search typing
            const search = form.querySelector('input[name="search"]');
            if (search) {
                search.addEventListener('keyup', function () {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => loadData(), 400);
                });
            }

            // Pagination AJAX
            document.addEventListener('click', function (e) {
                if (e.target.closest('.pagination a')) {
                    e.preventDefault();
                    loadData(e.target.href);
                }
            });

        });
    </script>
@endpush