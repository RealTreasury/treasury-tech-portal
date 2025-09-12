# Airbase Product API Documentation

This project retrieves product information from the **Products** table in an Airtable base via the Airbase API.
The following is a condensed reference for developers working with the API.

## Base Information
The plugin requires the following identifiers before it can query the Airbase API:

- **Base ID** – Airtable base identifier (e.g. `appJdxdz3310aJ3Fd`).
- **Products Table ID** – `tblOJ6yL9Jw5ZTdRc` (Products table).

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

## Linked Record Resolution
Linked record IDs are fetched in batches of **50**. Larger ID sets trigger multiple
requests which are merged on completion. This prevents exceeding Airtable's URL
and formula length limits.

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

## Response Formats
`TTP_Data::refresh_product_cache()` accepts multiple response shapes and
normalizes them into a single array of records built from product fields. The following formats are supported:

 - `{ "records": [...] }`
 - `{ "products": [...] }`
 - `[ ... ]` (top-level array of product records)

Any of these structures will be cached as a plain array of records built from product fields.

## Products Table Fields
| Field Name | Field ID | Type | Description |
|------------|----------|------|-------------|
| Product Name | `fld2hocSMtPQYWfPa` | Text | A single line of text. |
| Vendor | `fldsrlwpO9AfkmjcH` | Text | A single line of text. |
| Hosted Type | `fldGyZDaIUFFidaXA` | Multiple select | Array of option names ("On-Premise", "Cloud"). |
| Domain | `fldU53MVlWgkPbPDw` | Link to another record | Array of linked record IDs from the **Domain** table. |
| Product Website | `fldznljEJpn4lv79r` | Text | A single line of text. |
| Regions | `fldE8buvdk7TDG1ex` | Link to another record | Array of linked record IDs from the **Regions** table. |
| Sub Categories | `fldl2g5bYDq9TibuF` | Link to another record | Array of linked record IDs from the **Sub Categories** table. |
| Category | `fldXqnpKe8ioYOYhP` | Lookup | Array of Category fields in linked Sub Categories. |
| Status | `fldFsaznNFvfh3x7k` | Single select | Possible values: "Active", "Inactive", "Discontinued". |
| HQ Location | `fldTIplvUIwNH7C4X` | Text | A single line of text. |
| Founded Year | `fldwsUY6nSqxBk62J` | Text | A single line of text. |
| Founders | `fldoTMkJIl1i8oo0r` | Text | A single line of text. |
| Product Summary | `fldwBi5oBw6BwZiqV` | AI text | Automatically generated summary. |
| Core Capabilities | `fldwzQG0IQ0dbVcwF` | Lookup | Array of Core Capabilities from linked Sub Categories. |
| Additional Capabilities | `fldvvv8jnCKoJSI7x` | Link to another record | Linked record IDs from the **Capabilities** table. |
| Logo URL | `fldfZPuRMjQKCv3U6` | Text | URL to a product logo. |
| Demo Video URL | `fldHyVJRr3O5rkgd7` | Text | URL to a demo video. |
| Full Website URL | `fldpyWsRTiDiLX6nm` | Formula | Appends UTM parameters to `Product Website`. |

## Linked Record Field Names

The plugin resolves linked record identifiers for several product fields. The
following field names are accepted (including their common synonyms):

- **Domain** – `domain`, `domains`, `domain_id`, `domain_ids`
- **Regions** – `regions`, `region`, `region_id`, `region_ids`, `regions_id`
- **Sub Categories** – `sub_categories`, `sub_category`, `sub_category_id`,
  `sub_category_ids`
- **Core Capabilities** – `core_capabilities`, `core_capability`, `core_capability_id`,
  `core_capability_ids`
- **Additional Capabilities** – `capabilities`, `capability`, `capability_id`,
  `capability_ids`
- **Hosted Type** – `hosted_type`, `hosted_types`, `hosted_type_id`,
  `hosted_type_ids`
- **Category** – `category`, `categories`, `category_id`, `category_ids`

These variations are normalised before attempting to resolve linked records.

## Troubleshooting Unresolved Regions

If region identifiers returned from Airtable do not match any entry in the
**Regions** table, the plugin logs the unresolved IDs. The IDs are saved in the
`ttp_unresolved_report` option, grouped by field, and viewable from the
Unresolved Report page in the WordPress admin interface.

To resolve these warnings:

1. Confirm each ID exists in the Airtable **Regions** table.
2. Ensure product records reference the correct region IDs.
3. Refresh products from the admin page after correcting any data issues.


## Listing Products
To list records in **Products**, issue a `GET` request to the Products endpoint. Table names and table IDs can be used interchangeably. Using IDs means table name changes do not require modifications to your API request.

Returned records omit any fields with empty values (e.g. "", [], or false).

### Query Parameters
The following parameters may be supplied to filter, sort, and format the response. All parameters must be URL encoded. Helper libraries such as `airtable.js` handle this automatically.

- **`fields[]`** – Only include data for the specified field names or IDs.
- **`filterByFormula`** – Airtable formula used to filter records.
- **`maxRecords`** – Maximum total records returned.
- **`pageSize`** – Number of records per page (≤100).
- **`sort[]`** – List of sort objects with `field` and optional `direction` keys.
- **`view`** – Limit results to a specific view.
- **`cellFormat`** – Format for cell values (`json` or `string`).
- **`timeZone` / `userLocale`** – Required when `cellFormat=string`.
- **`returnFieldsByFieldId`** – If `true`, keys in the `fields` object are field IDs instead of names.
- **`recordMetadata[]`** – Include additional metadata such as `commentCount`.

### Pagination
The server returns one page at a time. Each page contains `pageSize` records (default 100). If more records exist, the response includes an `offset` value. Pass this `offset` in the next request to fetch the next page. Pagination stops when the end of the table is reached or `maxRecords` is satisfied.

### Example
```bash
curl "https://api.airtable.com/v0/appJdxdz3310aJ3Fd/Products?maxRecords=3&view=Grid%20view" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

```json
{
    "records": [
        {
            "id": "recby2FRT9GFatFbR",
            "createdTime": "2025-08-27T16:46:49.000Z",
            "fields": {
                "Product Name": "AccessPay",
                "Vendor": "AccessPay",
                "Status": "Active",
                "Product Summary": {
                    "state": "error",
                    "errorType": "emptyDependency",
                    "value": null,
                    "isStale": false
                },
                "Sub Categories": [
                    "recifmYqGMwBjGTfS"
                ],
                "Category": [
                    "recR1Oq7EIhxDSquU"
                ],
                "Product Website": "https://accesspay.com/",
                "Domain": [
                    "rec3ffWVfNTT08Mu2"
                ],
                "HQ Location": "Manchester, UK",
                "Hosted Type": [
                    "Cloud"
                ],
                "Founded Year": "2012",
                "Full Website URL": "https://accesspay.com/?utm_source=realtreasury&utm_medium=website&utm_campaign=vendor_referral"
            }
        },
        {
            "id": "recVCHbt66Eu4I0oA",
            "createdTime": "2025-08-06T18:25:58.000Z",
            "fields": {
                "Product Name": "Agicap",
                "Vendor": "Agicap",
                "Status": "Active",
                "Product Summary": {
                    "state": "error",
                    "errorType": "emptyDependency",
                    "value": "Agicap is a comprehensive treasury management platform that provides cash visibility and forecasting capabilities through automated bank connectivity via API and SFTP. The solution offers transaction management tools including search, sort, tag, and group functionalities, along with accounts payable and receivable matching for enhanced cash positioning. Key treasury capabilities include payment processing, basic forecasting tools, cash accounting, and debt management. The platform also supports investment management, market data integration, and derivatives handling, making it suitable for organizations seeking an integrated approach to treasury operations and cash flow optimization.",
                    "isStale": false
                },
                "Sub Categories": [
                    "recwQ8U1s7jXAq6PF"
                ],
                "Category": [
                    "reckz5jhQb2CBocKR"
                ],
                "Product Website": "https://agicap.com/en/",
                "Regions": [
                    "recGFTMlbSX4L69M9",
                    "rec75L3VdbgJC8GpT"
                ],
                "Domain": [
                    "rec3ffWVfNTT08Mu2"
                ],
                "HQ Location": "Lyon, France",
                "Hosted Type": [
                    "Cloud"
                ],
                "Founded Year": "2016",
                "Full Website URL": "https://agicap.com/en/?utm_source=realtreasury&utm_medium=website&utm_campaign=vendor_referral",
                "Core Capabilities": [
                    "recn4Y3PBFmPXh6Op",
                    "recKWB2J61wdkd9Px",
                    "recBesQQbKDTSrQpv",
                    "recbljsXl4bS0bNGE",
                    "recl1LDAxStyxrA8x",
                    "reczfTMWjinccAA39",
                    "rece2uGACplItLnov",
                    "recsmajy4cyeSgB9A"
                ]
            }
        },
        {
            "id": "recPOl8W1PGZzwR1z",
            "createdTime": "2025-09-05T18:52:17.000Z",
            "fields": {
                "Product Name": "AiVidens",
                "Vendor": "AiVidens",
                "Status": "Active",
                "Product Summary": {
                    "state": "error",
                    "errorType": "emptyDependency",
                    "value": null,
                    "isStale": false
                },
                "Sub Categories": [
                    "rec82D8pfGWAxpKGb"
                ],
                "Category": [
                    "recDcwJC0rWS3Ay7R"
                ],
                "Product Website": "https://aividens.com/",
                "Domain": [
                    "rec3ffWVfNTT08Mu2"
                ],
                "HQ Location": "Brussels, Belgium",
                "Hosted Type": [
                    "Cloud"
                ],
                "Founded Year": "2019",
                "Full Website URL": "https://aividens.com/?utm_source=realtreasury&utm_medium=website&utm_campaign=vendor_referral"
            }
        }
    ],
    "offset": "itrqhTDJQL21SekUM/recPOl8W1PGZzwR1z"
}
```

If iteration times out due to client inactivity or server restarts, a `422 LIST_RECORDS_ITERATOR_NOT_AVAILABLE` error is returned. Restart the iteration from the beginning.

## Retrieve a Products record
To retrieve an existing record, issue a `GET` request to the record endpoint. Empty fields are omitted from the response.

```bash
curl https://api.airtable.com/v0/appJdxdz3310aJ3Fd/Products/recby2FRT9GFatFbR \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

```json
{
    "id": "recby2FRT9GFatFbR",
    "createdTime": "2025-08-27T16:46:49.000Z",
    "fields": {
        "Product Name": "AccessPay",
        "Vendor": "AccessPay",
        "Status": "Active",
        "Product Summary": {
            "state": "error",
            "errorType": "emptyDependency",
            "value": null,
            "isStale": false
        },
        "Sub Categories": [
            "recifmYqGMwBjGTfS"
        ],
        "Category": [
            "recR1Oq7EIhxDSquU"
        ],
        "Product Website": "https://accesspay.com/",
        "Domain": [
            "rec3ffWVfNTT08Mu2"
        ],
        "HQ Location": "Manchester, UK",
        "Hosted Type": [
            "Cloud"
        ],
        "Founded Year": "2012",
        "Full Website URL": "https://accesspay.com/?utm_source=realtreasury&utm_medium=website&utm_campaign=vendor_referral"
    }
}
```

## Create Products records
To create new records, issue a `POST` request to the Products endpoint. Up to 10 records may be created per request. Each record must include a `fields` object keyed by field name or ID. Values for **Category**, **Core Capabilities** and **Full Website URL** are computed and cannot be directly created. Enable the `typecast` parameter to allow best-effort automatic data conversion.

```bash
curl -X POST https://api.airtable.com/v0/appJdxdz3310aJ3Fd/Products 
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" 
  -H "Content-Type: application/json" 
  --data '{
  "records": [
    {
      "fields": {
        "Product Name": "AccessPay",
        "Vendor": "AccessPay",
        "Hosted Type": [
          "Cloud"
        ],
        "Domain": [
          "rec3ffWVfNTT08Mu2"
        ],
        "Product Website": "https://accesspay.com/",
        "Sub Categories": [
          "recifmYqGMwBjGTfS"
        ],
        "Status": "Active",
        "HQ Location": "Manchester, UK",
        "Founded Year": "2012",
        "Product Summary": {
          "state": "error",
          "errorType": "emptyDependency",
          "value": null,
          "isStale": false
        }
      }
    },
    {
      "fields": {
        "Product Name": "Agicap",
        "Vendor": "Agicap",
        "Hosted Type": [
          "Cloud"
        ],
        "Domain": [
          "rec3ffWVfNTT08Mu2"
        ],
        "Product Website": "https://agicap.com/en/",
        "Regions": [
          "recGFTMlbSX4L69M9",
          "rec75L3VdbgJC8GpT"
        ],
        "Sub Categories": [
          "recwQ8U1s7jXAq6PF"
        ],
        "Status": "Active",
        "HQ Location": "Lyon, France",
        "Founded Year": "2016",
        "Product Summary": {
          "state": "error",
          "errorType": "emptyDependency",
          "value": "Agicap is a comprehensive treasury management platform that provides cash visibility and forecasting capabilities through automated bank connectivity via API and SFTP. The solution offers transaction management tools including search, sort, tag, and group functionalities, along with accounts payable and receivable matching for enhanced cash positioning. Key treasury capabilities include payment processing, basic forecasting tools, cash accounting, and debt management. The platform also supports investment management, market data integration, and derivatives handling, making it suitable for organizations seeking an integrated approach to treasury operations and cash flow optimization.",
          "isStale": false
        }
      }
    }
  ]
}'
```

## Update/Upsert Products records
Use `PATCH` for partial updates or `PUT` for destructive updates. Each record object must include an `id` and a `fields` object. To perform an upsert, include `performUpsert` with `fieldsToMergeOn` specifying 1–3 unique fields. Values for **Category**, **Core Capabilities** and **Full Website URL** are computed and cannot be updated directly. When modifying linked record fields or multiple select fields, include the full array of values you wish to retain.

```bash
curl -X PATCH https://api.airtable.com/v0/appJdxdz3310aJ3Fd/Products 
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" 
  -H "Content-Type: application/json" 
  --data '{
  "records": [
    {
      "id": "recby2FRT9GFatFbR",
      "fields": {
        "Product Name": "AccessPay",
        "Vendor": "AccessPay",
        "Hosted Type": [
          "Cloud"
        ],
        "Domain": [
          "rec3ffWVfNTT08Mu2"
        ],
        "Product Website": "https://accesspay.com/",
        "Sub Categories": [
          "recifmYqGMwBjGTfS"
        ],
        "Status": "Active",
        "HQ Location": "Manchester, UK",
        "Founded Year": "2012",
        "Product Summary": {
          "state": "error",
          "errorType": "emptyDependency",
          "value": null,
          "isStale": false
        }
      }
    },
    {
      "id": "recVCHbt66Eu4I0oA",
      "fields": {
        "Product Name": "Agicap",
        "Vendor": "Agicap",
        "Hosted Type": [
          "Cloud"
        ],
        "Domain": [
          "rec3ffWVfNTT08Mu2"
        ],
        "Product Website": "https://agicap.com/en/",
        "Regions": [
          "recGFTMlbSX4L69M9",
          "rec75L3VdbgJC8GpT"
        ],
        "Sub Categories": [
          "recwQ8U1s7jXAq6PF"
        ],
        "Status": "Active",
        "HQ Location": "Lyon, France",
        "Founded Year": "2016",
        "Product Summary": {
          "state": "error",
          "errorType": "emptyDependency",
          "value": "Agicap is a comprehensive treasury management platform that provides cash visibility and forecasting capabilities through automated bank connectivity via API and SFTP. The solution offers transaction management tools including search, sort, tag, and group functionalities, along with accounts payable and receivable matching for enhanced cash positioning. Key treasury capabilities include payment processing, basic forecasting tools, cash accounting, and debt management. The platform also supports investment management, market data integration, and derivatives handling, making it suitable for organizations seeking an integrated approach to treasury operations and cash flow optimization.",
          "isStale": false
        }
      }
    },
    {
      "id": "recPOl8W1PGZzwR1z",
      "fields": {
        "Product Name": "AiVidens",
        "Vendor": "AiVidens",
        "Hosted Type": [
          "Cloud"
        ],
        "Domain": [
          "rec3ffWVfNTT08Mu2"
        ],
        "Product Website": "https://aividens.com/",
        "Sub Categories": [
          "rec82D8pfGWAxpKGb"
        ],
        "Status": "Active",
        "HQ Location": "Brussels, Belgium",
        "Founded Year": "2019",
        "Product Summary": {
          "state": "error",
          "errorType": "emptyDependency",
          "value": null,
          "isStale": false
        }
      }
    }
  ]
}'
```

## Delete Products records
To delete records, issue a `DELETE` request to the Products endpoint with a URL‑encoded array of up to 10 record IDs. You may also delete a single record by requesting the record endpoint directly.

```bash
curl -X DELETE "https://api.airtable.com/v0/appJdxdz3310aJ3Fd/Products?records[]=RECORD_ID" 
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

## Domain Table

Table ID: `tbli7l5i5QxQzbpNV`

### Fields
| Field | Type | Notes |
|-------|------|-------|
| `Domain Name` (`fldoGBU2lWXKoFoAM`) | text | single line of text |
| `Products` (`fldAWqHU9rv7oX6MT`) | text | comma separated product names |
| `Product Count` (`fldlcq0OMqFUEJMla`) | count | number of product records |
| `Domain Summary` (`fldaMrJFqT5nkaa2L`) | rich text | AI-generated summary object |
| `Categories` (`fldFAUQgi8WT1hdlA`) | linked records to **Categories** table | array of record IDs |
| `Products` (`fldbhTNGdTp006wPd`) | linked records to **Products** table | array of record IDs |

### Listing Domains
```bash
curl "https://api.airtable.com/v0/BASE_ID/tbli7l5i5QxQzbpNV?maxRecords=3&view=Grid%20view" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

### Retrieving a Domain
```bash
curl https://api.airtable.com/v0/BASE_ID/tbli7l5i5QxQzbpNV/RECORD_ID \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

### Creating Domains
```bash
curl -X POST https://api.airtable.com/v0/BASE_ID/tbli7l5i5QxQzbpNV \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{
  "records": [
    {
      "fields": {
        "Domain Name": "Treasury",
        "Products": "Alpha Group, Atlar",
        "Categories": ["reckz5jhQb2CBocKR"],
        "Products": ["recOwgOvbUhsD8e30"]
      }
    }
  ]
}'
```

### Updating Domains
```bash
curl -X PATCH https://api.airtable.com/v0/BASE_ID/tbli7l5i5QxQzbpNV \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN" \
  -H "Content-Type: application/json" \
  --data '{
  "records": [
    {
      "id": "RECORD_ID",
      "fields": {
        "Domain Name": "Treasury"
      }
    }
  ]
}'
```

### Deleting Domains
```bash
curl -X DELETE "https://api.airtable.com/v0/BASE_ID/tbli7l5i5QxQzbpNV?records[]=RECORD_ID" \
  -H "Authorization: Bearer YOUR_SECRET_API_TOKEN"
```

### Notes
- Values for `Product Count` are computed by Airtable and cannot be set or updated directly.

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
| `Linked Additional Capabilities` (`fldMzl6st07X985kr`) | lookup | capability IDs from linked sub categories |
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
- Values for `Product Count (from Sub Categories)`, `Linked Products (from Sub Categories)`, `Count (Domain)`, `Linked Additional Capabilities`, `Sub Category Count` and `Linked Product Count` are computed by Airtable and cannot be set or updated directly.

## Additional Notes
- Use `typecast=true` to let Airtable create new select options automatically.
- The API only accepts URLs shorter than 16,000 characters.
- Values for `Category (from Sub Categories)` are computed and cannot be manually set.

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
| `Additional Capabilities` (`fldQXnjhbuiZS0vJV`) | linked records to **Capabilities** table |
| `Product Count` (`fldpeAWrpXwnkTbnC`) | count | auto-calculated number of linked products |
| `Count (Category)` (`fldV3e96JCcwfpdus`) | count | auto-calculated number of categories |
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

Values for `Product Count` and `Count (Category)` are computed and
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
        "Additional Capabilities": ["recn4Y3PBFmPXh6Op"],
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
| `Products` (`fldArBrHocWo0FxCr`) | text | list of products in that region |
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
        "Products": "Alpha Group, Bond Treasury",
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


