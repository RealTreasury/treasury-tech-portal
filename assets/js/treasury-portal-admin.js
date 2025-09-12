(function() {
    function init() {
        const searchInput = document.getElementById('treasury-portal-admin-search-input');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const filter = searchInput.value.toLowerCase();
                const rows = document.querySelectorAll('.treasury-portal-admin-table-wrapper tbody tr');
                rows.forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        }

        const tokenField = document.getElementById('ttp_airbase_token');
        const toggleBtn = document.getElementById('ttp_airbase_token_toggle');
        if (tokenField && toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const isPassword = tokenField.type === 'password';
                tokenField.type = isPassword ? 'text' : 'password';
                toggleBtn.textContent = isPassword ? 'Hide' : 'Reveal';
            });
        }

        setupColumnResizers();
    }

    function setupColumnResizers() {
        const table = document.querySelector('.treasury-portal-admin-table');
        if (!table) {
            return;
        }
        const headers = table.querySelectorAll('th');
        const bodyRows = table.querySelectorAll('tbody tr');

        headers.forEach(function(th, index) {
            const handle = th.querySelector('.tp-resizer');
            if (!handle) {
                return;
            }
            handle.addEventListener('mousedown', function(e) {
                e.preventDefault();
                const startX = e.pageX;
                const startWidth = th.offsetWidth;

                function onMouseMove(moveEvent) {
                    const newWidth = startWidth + (moveEvent.pageX - startX);
                    th.style.width = newWidth + 'px';
                    bodyRows.forEach(function(row) {
                        const cell = row.children[index];
                        if (cell) {
                            cell.style.width = newWidth + 'px';
                        }
                    });
                }

                function onMouseUp() {
                    document.removeEventListener('mousemove', onMouseMove);
                    document.removeEventListener('mouseup', onMouseUp);
                }

                document.addEventListener('mousemove', onMouseMove);
                document.addEventListener('mouseup', onMouseUp);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
