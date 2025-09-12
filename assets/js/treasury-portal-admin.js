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

        const table = document.querySelector('.treasury-portal-admin-table');
        if (table) {
            const tbody = table.querySelector('tbody');
            const headers = table.querySelectorAll('th[data-sort-key]');
            const originalRows = Array.from(tbody.querySelectorAll('tr'));
            const numericKeys = new Set(['founded_year']);
            let currentSort = { key: null, direction: null };

            headers.forEach((th, index) => {
                th.addEventListener('click', () => {
                    let direction = 'asc';
                    if (currentSort.key === th.dataset.sortKey) {
                        direction = currentSort.direction === 'asc' ? 'desc' : currentSort.direction === 'desc' ? null : 'asc';
                    }

                    currentSort = { key: th.dataset.sortKey, direction };

                    headers.forEach((h) => h.classList.remove('sorted-asc', 'sorted-desc'));

                    if (!direction) {
                        tbody.innerHTML = '';
                        originalRows.forEach((row) => tbody.appendChild(row));
                        currentSort = { key: null, direction: null };
                        return;
                    }

                    const rows = Array.from(tbody.querySelectorAll('tr'));
                    const isNumeric = numericKeys.has(th.dataset.sortKey);
                    rows.sort((a, b) => {
                        const aText = a.children[index].textContent.trim();
                        const bText = b.children[index].textContent.trim();
                        if (isNumeric) {
                            const aNum = parseFloat(aText) || 0;
                            const bNum = parseFloat(bText) || 0;
                            return direction === 'asc' ? aNum - bNum : bNum - aNum;
                        }
                        return direction === 'asc'
                            ? aText.localeCompare(bText)
                            : bText.localeCompare(aText);
                    });

                    tbody.innerHTML = '';
                    rows.forEach((row) => tbody.appendChild(row));
                    th.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');
                });
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
