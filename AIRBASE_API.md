# Airbase Vendor API Documentation

This project retrieves vendor information from an Airtable base via the Airbase API.
The following is a condensed reference for developers working with the API.

## Base Information
- **Base ID**: `appJdxdz3310aJ3Fd`
- **Products Table ID**: `tblOJ6yL9Jw5ZTdRc`

### Plugin Configuration Options
- `ttp_airbase_token` – API token used for authentication.
- `ttp_airbase_base_url` – Base API URL. Default: `https://api.airtable.com/v0`
- `ttp_airbase_base_id` – Airtable base identifier. Default: `appJdxdz3310aJ3Fd`
- `ttp_airbase_api_path` – Table/endpoint path. Default: `tblOJ6yL9Jw5ZTdRc`

These options are configurable from the **Airbase Settings** page in the WordPress admin.

## Authentication
All requests require a bearer token:

```bash
curl https://api.airtable.com/v0/appJdxdz3310aJ3Fd/Products \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

## Rate Limits
- Maximum of **5 requests per second** per base.
- Exceeding the limit returns HTTP `429`. Wait 30 seconds before retrying.

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

## Listing Products
Retrieve records from the Products table. Only non-empty fields are returned.

```bash
curl "https://api.airtable.com/v0/appJdxdz3310aJ3Fd/Products?maxRecords=3&view=Grid%20view" \
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
curl https://api.airtable.com/v0/appJdxdz3310aJ3Fd/Products/RECORD_ID \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

## Creating Products
Send up to 10 records per request.

```bash
curl -X POST https://api.airtable.com/v0/appJdxdz3310aJ3Fd/Products \
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
curl -X PATCH https://api.airtable.com/v0/appJdxdz3310aJ3Fd/Products \
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
curl -X DELETE "https://api.airtable.com/v0/appJdxdz3310aJ3Fd/Products?records[]=RECORD_ID" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

## Additional Notes
- Use `typecast=true` to let Airtable create new select options automatically.
- The API only accepts URLs shorter than 16,000 characters.
- Values for `Parent Category (from Sub Categories)` are computed and cannot be manually set.

