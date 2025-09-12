(function() {
    let filterRow;
    let filterPanel;
    let filterPanelContent;
    let tableBody;
    let filtersInPanel = false;

    function debounce(fn, delay) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function init() {
        const searchInput = document.getElementById('treasury-portal-admin-search-input');
        const table = document.querySelector('.treasury-portal-admin-table');
        const keyToIndex = {};
        if (table) {
            Array.from(table.querySelectorAll('th')).forEach(function(th, i) {
                const key = th.dataset.sortKey;
                if (key) {
                    keyToIndex[key] = i;
                }
            });
        }

        tableBody = table ? table.querySelector('tbody') : null;
        filterRow = tableBody ? tableBody.querySelector('.tp-filter-row') : null;
        filterPanel = document.querySelector('.tp-filter-panel');
        filterPanelContent = filterPanel ? filterPanel.querySelector('.tp-filter-panel-content') : null;
        const filterToggleBtn = document.querySelector('.tp-filter-toggle');

        const columnFilters = {};
        let searchValue = '';

        const applyFilters = debounce(function() {
            if (!table) {
                return;
            }
            const rows = table.querySelectorAll('tbody tr:not(.tp-filter-row)');
            rows.forEach(function(row) {
                let matches = true;
                const rowText = row.textContent.toLowerCase();
                if (searchValue) {
                    const searchTokens = searchValue.split(/[\s,]+/).filter(Boolean);
                    if (!searchTokens.every(token => rowText.includes(token))) {
                        matches = false;
                    }
                }
                if (matches) {
                    for (const key in columnFilters) {
                        const cell = row.children[keyToIndex[key]];
                        const cellValue = cell ? (cell.dataset.filterValue !== undefined ? cell.dataset.filterValue : cell.textContent) : '';
                        const cellText = cellValue.toLowerCase().trim();
                        const filter = columnFilters[key];
                        if (filter.exact) {
                            if (cellText !== filter.value) {
                                matches = false;
                                break;
                            }
                        } else {
                            const tokens = filter.value.split(/[\s,]+/).filter(Boolean);
                            if (!tokens.every(token => cellText.includes(token))) {
                                matches = false;
                                break;
                            }
                        }
                    }
                }
                row.style.display = matches ? '' : 'none';
            });
        }, 200);

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                searchValue = searchInput.value.toLowerCase();
                applyFilters();
            });
        }

        document.querySelectorAll('.tp-filter-control').forEach(function(control) {
            const eventType = control.tagName === 'SELECT' ? 'change' : 'input';
            control.addEventListener(eventType, function() {
                const key = control.dataset.filterKey;
                const value = control.value.trim().toLowerCase();
                if (value) {
                    columnFilters[key] = { value: value, exact: control.dataset.match === 'exact' };
                } else {
                    delete columnFilters[key];
                }
                applyFilters();
            });
        });

        if (filterToggleBtn && filterPanel) {
            filterToggleBtn.addEventListener('click', function() {
                filterPanel.classList.toggle('open');
            });
        }

        applyFilters();

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
        handleResponsive();
        window.addEventListener('resize', handleResponsive);
    }

    function setupColumnResizers() {
        if (window.innerWidth <= 768) {
            return;
        }
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

    function handleResponsive() {
        const table = document.querySelector('.treasury-portal-admin-table');
        if (!table) {
            return;
        }
        if (window.innerWidth <= 768) {
            table.querySelectorAll('th, td').forEach(function(cell) {
                cell.style.width = '';
            });
            if (filterRow && filterPanelContent && !filtersInPanel) {
                filterPanelContent.appendChild(filterRow);
                filtersInPanel = true;
            }
        } else {
            if (filterRow && tableBody && filtersInPanel) {
                tableBody.insertBefore(filterRow, tableBody.firstChild);
                filtersInPanel = false;
            }
            if (filterPanel) {
                filterPanel.classList.remove('open');
            }
        }
    }

    function setupSorting() {
        const table = document.querySelector('.treasury-portal-admin-table');
        if (!table) {
            return;
        }

        const tbody = table.querySelector('tbody');
        const headers = table.querySelectorAll('th[data-sort-key]');
        const filterRow = tbody.querySelector('.tp-filter-row');
        const originalRows = Array.from(tbody.querySelectorAll('tr:not(.tp-filter-row)'));
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
                    if (filterRow) {
                        tbody.insertBefore(filterRow, tbody.firstChild);
                    }
                    return;
                }

                th.classList.add(direction === 'asc' ? 'sorted-asc' : 'sorted-desc');
                const rows = Array.from(tbody.querySelectorAll('tr:not(.tp-filter-row)'));
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
                if (filterRow) {
                    tbody.insertBefore(filterRow, tbody.firstChild);
                }
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
