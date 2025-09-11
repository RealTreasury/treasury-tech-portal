(function(){
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('treasury-portal-admin-search-input');
        if (!searchInput) return;
        searchInput.addEventListener('input', function() {
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('.treasury-portal-admin-table-wrapper tbody tr');
            rows.forEach(function(row){
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    });
})();
