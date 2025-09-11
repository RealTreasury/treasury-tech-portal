# Linked Field Resolution

This document explains how product data moves through the plugin and how linked Airtable fields are resolved.

## Data Flow
1. **`TTP_Airbase::get_vendors`** – Fetches raw product records from the Airbase API. Each record may contain linked field IDs for related tables.
2. **`TTP_Data::refresh_vendor_cache`** – Calls `get_vendors`, maps field IDs to readable names and resolves linked record IDs. The final product array is cached for faster access.
3. **`TTP_Airbase::resolve_linked_records`** – Given a table ID and a list of record IDs, retrieves the primary field from that table so that stored IDs become human‑readable values. Results are cached in-memory per table and record ID for the duration of the request to avoid redundant API calls. Pass the optional `$use_field_ids` flag to request and return values using field IDs instead of field names.

## Adding a New Linked Field
Linked fields are configured in `TTP_Data::refresh_vendor_cache()` via the `$linked_tables` array. Each entry defines the Airtable table and the primary field used for display:

```php
$linked_tables['Industry'] = [
    'table'         => 'tblIndustries', // Airtable table ID or name
    'primary_field' => 'Name',         // Field returned for each record ID
];
```

When processing product records, parse the field and resolve the IDs:

```php
$industry_field = self::parse_record_ids( $fields['Industry'] ?? [] );
$industries = [];
if ( self::contains_record_ids( $industry_field ) ) {
    /**
     * Replace placeholder IDs with names retrieved from Airtable.
     * Real code uses TTP_Data::resolve_linked_field() for this swap.
     */
    $resolved = TTP_Airbase::resolve_linked_records(
        $linked_tables['Industry']['table'],
        $industry_field,
        $linked_tables['Industry']['primary_field']
    );
    $industries = array_map( 'sanitize_text_field', (array) $resolved );
} else {
    $industries = array_map( 'sanitize_text_field', $industry_field );
}
```

Finally, store the resolved values in the product array:

```php
$products[] = [
    // ...
    'industries' => $industries,
];
```

## Testing and Cache Refresh
Updating linked‑field logic requires both testing and cache invalidation:

1. Run the full PHP test suite:
   ```bash
   ./scripts/test.sh
   ```
2. Clear cached product data so new logic runs on fresh records:
   ```bash
   wp transient delete ttp_products_cache
   wp option delete ttp_products
   ```
   Then regenerate data:
   ```php
   TTP_Data::refresh_vendor_cache();
   ```

These steps ensure that changes to linked field handling are validated and reflected in the stored product cache.
