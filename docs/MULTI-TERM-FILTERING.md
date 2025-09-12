# Manual Test: Multi-term Filtering

Follow these steps to verify that the admin table filters support multi-term queries with AND logic:

1. Log in to the WordPress admin dashboard and open the Treasury Tech Portal tools list.
2. In the filter panel, locate a text filter (e.g., "Vendor Name").
3. Enter multiple terms separated by spaces or commas (e.g., `bank, europe`).
4. Observe the table update automatically or click **Apply & Close** if using the side panel.
5. Only rows whose corresponding column contains *all* entered terms should remain visible.

This ensures that filters treat each term individually and require every term to be present before a row matches.
