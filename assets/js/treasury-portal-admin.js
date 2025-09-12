(function() {
    function debounce(fn, delay) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                fn.apply(context, args);
            }, delay);
        };
    }

    function init() {
        const searchInput = document.getElementById('treasury-portal-admin-search-input');
        const filterControls = document.querySelectorAll('.tp-filter-row [data-column]');
        const rows = document.querySelectorAll('.treasury-portal-admin-table-wrapper tbody tr');

        const applyFilters = debounce(function() {
            const searchVal = searchInput ? searchInput.value.toLowerCase() : '';
            const activeFilters = {};

            filterControls.forEach(function(control) {
                const value = control.value.toLowerCase();
                if (value) {
                    activeFilters[control.getAttribute('data-column')] = {
                        value: value,
                        type: control.tagName
                    };
                }
            });

            rows.forEach(function(row) {
                let visible = true;

                if (searchVal && !row.textContent.toLowerCase().includes(searchVal)) {
                    visible = false;
                }

                if (visible) {
                    for (const key in activeFilters) {
                        const colIndex = parseInt(key, 10);
                        const cell = row.children[colIndex];
                        const cellText = cell ? cell.textContent.toLowerCase() : '';
                        const filter = activeFilters[key];
                        if (filter.type === 'SELECT') {
                            if (cellText !== filter.value) {
                                visible = false;
                                break;
                            }
                        } else if (!cellText.includes(filter.value)) {
                            visible = false;
                            break;
                        }
                    }
                }

                row.style.display = visible ? '' : 'none';
            });
        }, 200);

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        filterControls.forEach(function(control) {
            const eventType = control.tagName === 'SELECT' ? 'change' : 'input';
            control.addEventListener(eventType, applyFilters);
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

