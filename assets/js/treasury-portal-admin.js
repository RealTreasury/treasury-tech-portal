(function() {
    function debounce(fn, delay = 200) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(null, args), delay);
        };
    }

    function init() {
        const searchInput = document.getElementById('treasury-portal-admin-search-input');
        const filterControls = document.querySelectorAll('.tp-filter-row input, .tp-filter-row select');

        const applyFilters = () => {
            const searchFilter = searchInput ? searchInput.value.toLowerCase() : '';
            const active = {};
            filterControls.forEach(control => {
                const value = control.value.trim().toLowerCase();
                if (value) {
                    active[control.dataset.column] = {
                        value,
                        type: control.tagName === 'SELECT' ? 'select' : 'text'
                    };
                }
            });
            const rows = document.querySelectorAll('.treasury-portal-admin-table-wrapper tbody tr');
            rows.forEach(row => {
                if (row.classList.contains('tp-filter-row')) {
                    return;
                }
                if (searchFilter && !row.textContent.toLowerCase().includes(searchFilter)) {
                    row.style.display = 'none';
                    return;
                }
                let show = true;
                Object.keys(active).forEach(index => {
                    if (!show) {
                        return;
                    }
                    const filter = active[index];
                    const cell = row.cells[index];
                    if (!cell) {
                        return;
                    }
                    const cellText = cell.textContent.toLowerCase();
                    if (filter.type === 'select') {
                        if (cellText !== filter.value) {
                            show = false;
                        }
                    } else if (!cellText.includes(filter.value)) {
                        show = false;
                    }
                });
                row.style.display = show ? '' : 'none';
            });
        };

        const debouncedApplyFilters = debounce(applyFilters, 200);

        if (searchInput) {
            searchInput.addEventListener('input', debouncedApplyFilters);
        }
        filterControls.forEach(control => {
            const eventName = control.tagName === 'SELECT' ? 'change' : 'input';
            control.addEventListener(eventName, debouncedApplyFilters);
        });

        const tokenField = document.getElementById('ttp_airbase_token');
        const toggleBtn = document.getElementById('ttp_airbase_token_toggle');
        if (tokenField && toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const isPassword = tokenField.type === 'password';
                tokenField.type = isPassword ? 'text' : 'password';
                toggleBtn.textContent = isPassword ? 'Hide' : 'Reveal';
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
