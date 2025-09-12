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
        setupSorting();
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

    function setupSorting() {
        const table = document.querySelector('.treasury-portal-admin-table');
        if (!table) {
            return;
        }

        const tbody = table.querySelector('tbody');
        const headers = table.querySelectorAll('th[data-sort-key]');
        const originalRows = Array.from(tbody.querySelectorAll('tr'));
        const numericColumns = new Set(['founded_year']);
        let currentSort = { key: null, direction: null };

        headers.forEach(function(th, index) {
            th.addEventListener('click', function(e) {
                if (e.target.closest('.tp-resizer')) {
                    return;
                }

                const key = th.dataset.sortKey;
                let direction = 'asc';
                if (currentSort.key === key) {
                    direction = currentSort.direction === 'asc' ? 'desc' : currentSort.direction === 'desc' ? null : 'asc';
                }
                currentSort = { key: direction ? key : null, direction: direction };

                headers.forEach(function(h) {
                    h.classList.remove('sorted-asc', 'sorted-desc');
                });

                if (!direction) {
                    originalRows.forEach(function(row) {
                        tbody.appendChild(row);
                    });
                    return;
                }

                th.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const isNumeric = numericColumns.has(key);
                rows.sort(function(a, b) {
                    const aText = a.children[index].textContent.trim();
                    const bText = b.children[index].textContent.trim();
                    if (isNumeric) {
                        const aNum = parseFloat(aText);
                        const bNum = parseFloat(bText);
                        return (isNaN(aNum) ? 0 : aNum) - (isNaN(bNum) ? 0 : bNum);
                    }
                    return aText.localeCompare(bText);
                });
                if (direction === 'desc') {
                    rows.reverse();
                }
                rows.forEach(function(row) {
                    tbody.appendChild(row);
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
