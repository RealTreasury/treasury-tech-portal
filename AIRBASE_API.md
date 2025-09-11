# Airbase Vendor API Documentation

This project retrieves vendor information from an Airtable base via the Airbase API.
The following is a condensed reference for developers working with the API.

## Base Information
The plugin requires the following identifiers before it can query the Airbase API:

- **Base ID** – Airtable base identifier.
- **Products Table ID** – ID of the Products table within that base.

### Plugin Configuration Options
- `ttp_airbase_token` – API token used for authentication (**required**).
- `ttp_airbase_base_url` – Base API URL. Default: `https://api.airtable.com/v0`.
- `ttp_airbase_base_id` – Airtable base identifier. No default; **must be set**.
- `ttp_airbase_api_path` – Table/endpoint path. No default; **must be set**.

These options are configurable from the **Airbase Settings** page in the WordPress admin. Until the token, base ID, and products table ID are provided, the plugin will not make any API requests.

## Authentication
All requests require a bearer token:

```bash
curl https://api.airtable.com/v0/BASE_ID/Products \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

## Rate Limits
- Maximum of **5 requests per second** per base.
- Exceeding the limit returns HTTP `429`. The plugin automatically retries up to three times using an exponential backoff (1s, 2s, 4s) before surfacing an error. If the limit continues to be exceeded, wait ~30 seconds before manual retries.

## JavaScript Integration
For build scripts or other Node-based tooling you can use the official [airtable.js library](https://github.com/Airtable/airtable.js).

Install the package and query records:

```bash
npm install airtable
```

```javascript
import Airtable from 'airtable';

const base = new Airtable({ apiKey: process.env.AIRTABLE_API_KEY }).base('BASE_ID');

base('Products')
  .select({ maxRecords: 3 })
  .eachPage((records, fetchNextPage) => {
    records.forEach(record => {
      console.log(record.get('Product Name'));
    });
    fetchNextPage();
  }, err => {
    if (err) {
      console.error(err);
      return;
    }
  });
```

### PHP vs JavaScript
Use the plugin's PHP integration for WordPress server-side requests where the API key remains hidden. Reserve `airtable.js` for build scripts or tooling that runs outside WordPress, such as Node-based ETL jobs. Avoid running the JavaScript client in the browser since it would expose your API token.

## Schema Caching
Table schemas are requested infrequently and cached in a transient named `ttp_airbase_schema` for 24 hours. Calls to `TTP_Airbase::get_table_schema()` read from this cache before performing network requests.

### Forcing a Refresh
The schema cache is refreshed automatically when missing or expired. To manually invalidate it:

- Use WP-CLI: `wp transient delete ttp_airbase_schema`
- Or programmatically: `delete_transient('ttp_airbase_schema');`

## Vendor Response Formats
`TTP_Data::refresh_vendor_cache()` accepts multiple response shapes and
normalizes them into a single vendor array. The following formats are supported:

- `{ "records": [...] }`
- `{ "products": [...] }`
- `{ "vendors": [...] }`
- `[ ... ]` (top-level array of vendors)

Any of these structures will be cached as a plain array of vendors.

## Products Table Fields
| Field | Type | Notes |
|-------|------|-------|
| `Product Name` (`fld2hocSMtPQYWfPa`) | text | required name of the product |
| `Linked Vendor` (`fldsrlwpO9AfkmjcH`) | text | vendor name |
| `Hosted Type` (`fldGyZDaIUFFidaXA`) | multiple select (`On-Premise`, `Cloud`) |
| `Domain` (`fldU53MVlWgkPbPDw`) | linked records to **Domain** table |
| `Product Website` (`fldznljEJpn4lv79r`) | text URL |
| `Regions` (`fldE8buvdk7TDG1ex`) | linked records to **Regions** table |
| `Sub Categories` (`fldl2g5bYDq9TibuF`) | linked records to **Sub Categories** table |
| `Parent Category` (`fldXqnpKe8ioYOYhP`) | lookup of parent category |
| `Status` (`fldFsaznNFvfh3x7k`) | single select (`Active`, `Inactive`, `Discontinued`) |
| `HQ Location` (`fldTIplvUIwNH7C4X`) | text |
| `Founded Year` (`fldwsUY6nSqxBk62J`) | text |
| `Founders` (`fldoTMkJIl1i8oo0r`) | text |
| `Capabilities` (`fldvvv8jnCKoJSI7x`) | linked records to **Capabilities** table |
| `Logo URL` (`fldfZPuRMjQKCv3U6`) | text URL |

## Linked Record Field Names

The plugin resolves linked record identifiers for several vendor fields. The
following field names are accepted (including their common synonyms):

- **Domain** – `domain`, `domains`, `domain_id`, `domain_ids`
- **Regions** – `regions`, `region`, `region_id`, `region_ids`, `regions_id`
- **Sub Categories** – `sub_categories`, `sub_category`, `sub_category_id`,
  `sub_category_ids`
- **Capabilities** – `capabilities`, `capability`, `capability_id`,
  `capability_ids`
- **Hosted Type** – `hosted_type`, `hosted_types`, `hosted_type_id`,
  `hosted_type_ids`
- **Parent Category** – `parent_category`, `parent_categories`,
  `parent_category_id`, `parent_category_ids`

These variations are normalised before attempting to resolve linked records.

## Troubleshooting Unresolved Regions

If region identifiers returned from Airtable do not match any entry in the
**Regions** table, the plugin logs the unresolved IDs. The IDs are saved in the
`ttp_unresolved_fields` option and displayed beneath the vendor table in the
WordPress admin interface.

To resolve these warnings:

1. Confirm each ID exists in the Airtable **Regions** table.
2. Ensure vendor records reference the correct region IDs.
3. Refresh vendors from the admin page after correcting any data issues.

## Listing Products
Retrieve records from the Products table. Only non-empty fields are returned.

```bash
curl "https://api.airtable.com/v0/BASE_ID/PRODUCTS_TABLE_ID?maxRecords=3&view=Grid%20view" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

Responses include a top-level `records` array containing matching rows.

Common query parameters:
- `fields[]`: restrict returned fields
- `filterByFormula`: Airtable formula for filtering
- `maxRecords` / `pageSize`: pagination controls
- `sort[]`: order results
- `view`: limit to a specific view

## Retrieving a Product
```bash
curl https://api.airtable.com/v0/BASE_ID/PRODUCTS_TABLE_ID/RECORD_ID \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

## Creating Products
Send up to 10 records per request.

```bash
curl -X POST https://api.airtable.com/v0/BASE_ID/PRODUCTS_TABLE_ID \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{
    "records": [{
      "fields": {
        "Product Name": "Example",
        "Linked Vendor": "Example Co",
        "Status": "Active"
      }
    }]
  }'
```

## Updating Products
Patch only the fields that change. Up to 10 records per request.

```bash
curl -X PATCH https://api.airtable.com/v0/BASE_ID/PRODUCTS_TABLE_ID \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{
    "records": [{
      "id": "RECORD_ID",
      "fields": {
        "Status": "Inactive"
      }
    }]
  }'
```

## Deleting Products
```bash
curl -X DELETE "https://api.airtable.com/v0/BASE_ID/PRODUCTS_TABLE_ID?records[]=RECORD_ID" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

## Categories Table

Table ID: `tblzGvVxiuzvf55a1`

### Fields
| Field | Type | Notes |
|-------|------|-------|
| `Category Name` (`fldEMQ4pAppOLeWNv`) | text | category label |
| `Description` (`fldK1McXCzKRt7vNS`) | long text | may include mention tokens |
| `Domain` (`fldoOnZ4sEyOtfwB6`) | linked records to **Domain** table | array of record IDs |
| `Sub Categories` (`fldAexZDE9t3I2KOC`) | linked records to **Sub Categories** table | array of record IDs |
| `Product Count (from Sub Categories)` (`fldNRKbve8To8z0Oi`) | lookup | counts from linked sub categories |
| `Linked Products (from Sub Categories)` (`fldTYdVWjYyQJXPS4`) | lookup | product IDs from linked sub categories |
| `Count (Domain)` (`fldviPn2IFlVTpzg2`) | count | number of linked domain records |
| `Linked Capabilities` (`fldMzl6st07X985kr`) | lookup | capability IDs from linked sub categories |
| `Sub Category Count` (`fldQX6bLq9mTLFxwk`) | count | number of linked sub categories |
| `Linked Product Count` (`fldmZLfQZKQCCbCGr`) | formula | sum of Product Count (from Sub Categories) |

### Listing Categories
```bash
curl "https://api.airtable.com/v0/BASE_ID/tblzGvVxiuzvf55a1?maxRecords=3&view=Grid%20view" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

### Retrieving a Category
```bash
curl https://api.airtable.com/v0/BASE_ID/tblzGvVxiuzvf55a1/RECORD_ID \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

### Creating Categories
```bash
curl -X POST https://api.airtable.com/v0/BASE_ID/tblzGvVxiuzvf55a1 \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{
  "records": [
    {
      "fields": {
        "Category Name": "Example",
        "Domain": ["rec123"],
        "Sub Categories": ["rec456"]
      }
    }
  ]
}'
```

### Updating Categories
```bash
curl -X PATCH https://api.airtable.com/v0/BASE_ID/tblzGvVxiuzvf55a1 \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{
  "records": [
    {
      "id": "RECORD_ID",
      "fields": {
        "Description": "Updated info"
      }
    }
  ]
}'
```

### Deleting Categories
```bash
curl -X DELETE "https://api.airtable.com/v0/BASE_ID/tblzGvVxiuzvf55a1?records[]=RECORD_ID" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

### Notes
- Values for `Product Count (from Sub Categories)`, `Linked Products (from Sub Categories)`, `Count (Domain)`, `Linked Capabilities`, `Sub Category Count` and `Linked Product Count` are computed by Airtable and cannot be set or updated directly.

## Additional Notes
- Use `typecast=true` to let Airtable create new select options automatically.
- The API only accepts URLs shorter than 16,000 characters.
- Values for `Parent Category (from Sub Categories)` are computed and cannot be manually set.

## Sub Categories Table

The Sub Categories table ID is `tblEDySEcdvwCweuq`. Table names and IDs are
interchangeable in API requests.

### Fields

| Field | Type | Notes |
|-------|------|-------|
| `Sub Category Name` (`fld6hOaUeXUsi8ZyM`) | text | name of the sub category |
| `Description` (`fld0WF293CfkNMDu7`) | long text | may include mention tokens |
| `Category` (`fldRv7UQGPVftu8Id`) | linked records to **Categories** table |
| `Linked Products` (`fld5Oxt2B7NPkZNxc`) | linked records to **Products** table |
| `Capabilities` (`fldQXnjhbuiZS0vJV`) | linked records to **Capabilities** table |
| `Product Count` (`fldpeAWrpXwnkTbnC`) | count | auto-calculated number of linked products |
| `Count (Parent Category)` (`fldV3e96JCcwfpdus`) | count | auto-calculated number of parent categories |
| `Region` (`flda8n6TBoZ9zn5vu`) | linked records to **Regions** table |

### Listing Sub Categories

```bash
curl "https://api.airtable.com/v0/BASE_ID/tblEDySEcdvwCweuq?maxRecords=3&view=Grid%20view" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

Returned records omit empty fields. All query parameters available to the
Products table (`fields[]`, `filterByFormula`, `maxRecords`, `pageSize`,
`sort[]`, `view`, `cellFormat`, `timeZone`, `userLocale`,
`returnFieldsByFieldId`, `recordMetadata`) are supported.

### Retrieving a Sub Category

```bash
curl https://api.airtable.com/v0/BASE_ID/tblEDySEcdvwCweuq/RECORD_ID \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

### Creating Sub Categories

Values for `Product Count` and `Count (Parent Category)` are computed and
cannot be set directly.

```bash
curl -X POST https://api.airtable.com/v0/BASE_ID/tblEDySEcdvwCweuq \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{
    "records": [{
      "fields": {
        "Sub Category Name": "Cash Tool",
        "Category": ["reckz5jhQb2CBocKR"],
        "Linked Products": ["recgOn3wZbYQE8gtZ"],
        "Capabilities": ["recn4Y3PBFmPXh6Op"],
        "Region": ["rec75L3VdbgJC8GpT"]
      }
    }]
  }'
```

### Updating Sub Categories

Patch only the fields that change. Up to 10 records per request.

```bash
curl -X PATCH https://api.airtable.com/v0/BASE_ID/tblEDySEcdvwCweuq \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{
    "records": [{
      "id": "RECORD_ID",
      "fields": {
        "Sub Category Name": "Cash Tool"
      }
    }]
  }'
```

### Deleting Sub Categories

```bash
curl -X DELETE "https://api.airtable.com/v0/BASE_ID/tblEDySEcdvwCweuq?records[]=RECORD_ID" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

## Regions Table

The Regions table ID is `tblmxl6BKXXjQHUez`. Table names and IDs are
interchangeable in API requests.

### Fields

| Field | Type | Notes |
|-------|------|-------|
| `Region` (`fldM1PNxbzPeV45Kj`) | text | single line region name |
| `Linked Vendors` (`fldArBrHocWo0FxCr`) | text | list of vendors in that region |
| `Sub Categories` (`fldwbPhWdTg3Hcdpu`) | linked records to **Sub Categories** table | array of record IDs |
| `Products` (`fld2CcSeWkli5ulg2`) | linked records to **Products** table | array of record IDs |

### Listing Regions

```bash
curl "https://api.airtable.com/v0/BASE_ID/tblmxl6BKXXjQHUez?maxRecords=3&view=Grid%20view" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

Returned records omit empty fields. All query parameters available to the
Products table (`fields[]`, `filterByFormula`, `maxRecords`, `pageSize`,
`sort[]`, `view`, `cellFormat`, `timeZone`, `userLocale`,
`returnFieldsByFieldId`, `recordMetadata`) are supported.

### Retrieving a Region

```bash
curl https://api.airtable.com/v0/BASE_ID/tblmxl6BKXXjQHUez/RECORD_ID \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

### Creating Regions

Up to 10 records may be created per request.

```bash
curl -X POST https://api.airtable.com/v0/BASE_ID/tblmxl6BKXXjQHUez \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{
    "records": [{
      "fields": {
        "Region": "EMEA",
        "Linked Vendors": "Alpha Group, Bond Treasury",
        "Products": ["recbtxzaIPTYxjzwK", "rec130cwb06hCCkwk"],
        "Sub Categories": ["rec8116cdd76088af", "rec245db9343f55e8"]
      }
    }]
  }'
```

### Updating Regions

Patch only the fields that change. Up to 10 records per request.

```bash
curl -X PATCH https://api.airtable.com/v0/BASE_ID/tblmxl6BKXXjQHUez \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{
    "records": [{
      "id": "RECORD_ID",
      "fields": {
        "Region": "APAC"
      }
    }]
  }'
```

### Deleting Regions

```bash
curl -X DELETE "https://api.airtable.com/v0/BASE_ID/tblmxl6BKXXjQHUez?records[]=RECORD_ID" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```


