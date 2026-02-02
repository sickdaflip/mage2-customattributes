# FlipDev_CustomAttributes for Magento 2.4.8

**Virtual Product Attributes for Price Comparison Feeds (Idealo, Billiger.de)**

## Overview

This module adds virtual (calculated) product attributes that are essential for price comparison platform feeds but missing from Magento's standard export capabilities. Specifically designed for use with Firebear Import/Export module.

## Problem Solved

Firebear Import/Export and similar export tools have limitations:

1. **No Tax Calculation**: Exports net prices, but Idealo/Billiger.de require gross prices (including tax)
2. **URL Key Only**: Exports `url_key` instead of full product URL
3. **Relative Image Paths**: Exports relative paths instead of full image URLs
4. **No Category Path**: Missing formatted category paths required by Idealo

## Features

This module adds **6 virtual attributes** to every product:

### Price Attributes (with Tax)

| Attribute Code | Description | Example |
|----------------|-------------|---------|
| `fdca_price_incl_tax` | Base price including tax | 119.00 (for 100.00 + 19% VAT) |
| `fdca_special_price_incl_tax` | Special/sale price including tax | 95.20 (for 80.00 + 19% VAT) |
| `fdca_final_price_incl_tax` | Final price including tax (uses special price if active) | 95.20 or 119.00 |

**Tax Calculation:**
- Automatically respects product tax class (19%, 7%, 0%, custom)
- Considers special price validity dates
- Uses Magento's native tax calculation engine

### URL Attributes

| Attribute Code | Description | Example |
|----------------|-------------|---------|
| `fdca_product_url` | Full product URL | https://www.gastrodax.de/product-name |
| `fdca_image_url` | Full image URL | https://www.gastrodax.de/media/catalog/product/image.jpg |
| `fdca_category_path` | Category path (Idealo format) | Gastro > Kühlung > Kühlschränke |

## Installation

```bash
# Navigate to Magento root
cd /var/www/customers/webs/gastrodax/html

# Create module directory
mkdir -p app/code/FlipDev/CustomAttributes

# Copy module files (upload ZIP and extract, or use git)
# ... upload files to app/code/FlipDev/CustomAttributes/ ...

# Enable module
php bin/magento module:enable FlipDev_CustomAttributes

# Run setup upgrade
php bin/magento setup:upgrade

# Deploy static content
php bin/magento setup:static-content:deploy de_DE en_US -f -j $(nproc)

# Compile
php bin/magento setup:di:compile -f -j $(nproc)

# Flush cache
php bin/magento cache:flush
```

## Configuration in Firebear Import/Export

After installation, the new attributes appear in Firebear's attribute dropdown.

### Example: Idealo Product Feed

**Admin → Stores → Improved Import / Export → Export Jobs → Add New Job**

#### Map Attributes Configuration:

| System Attribute (Magento) | Export Attribute (Idealo) |
|----------------------------|---------------------------|
| `sku` | `sku` |
| `name` | `title` |
| `manufacturer` | `brand` |
| **`fdca_final_price_incl_tax`** ← NEW | `price` |
| **`fdca_price_incl_tax`** ← NEW | `old_price` |
| `gtin` | `eans` |
| **`fdca_product_url`** ← NEW | `url` |
| **`fdca_image_url`** ← NEW | `imageUrl` |
| **`fdca_category_path`** ← NEW | `categoryPath` |
| `short_description` | `description` |

#### Static Values (Default Value field):

| Export Attribute | Default Value |
|------------------|---------------|
| `deliveryTime` | Lieferung in 2-3 Werktagen |
| `deliveryCosts_dhl` | 0.00 |
| `paymentCosts_paypal` | 0.00 |

## How It Works

### Architecture

The module uses **Observers** to intercept product loads:

1. `catalog_product_collection_load_after` - Triggered when Firebear exports products
2. `catalog_product_load_after` - Triggered for single product loads

For each product, the module:

1. **Price Calculation**:
   - Retrieves product's tax class ID
   - Gets applicable tax rate from Magento's tax calculation engine
   - Calculates: `price_incl_tax = net_price * (1 + tax_rate/100)`
   - Rounds to 2 decimal places
   
2. **URL Generation**:
   - Gets store base URL
   - Appends product `url_key`
   - Example: `store_url` + `product-name`

3. **Image URL**:
   - Gets store media URL
   - Appends image path
   - Example: `https://gastrodax.de/media/catalog/product/image.jpg`

4. **Category Path**:
   - Loads product categories
   - Builds path from root to leaf
   - Formats with ` > ` separator (Idealo requirement)

### Virtual Attributes

These attributes are **NOT stored in the database**. They are calculated on-the-fly when products are loaded, which means:

✅ No database modifications required  
✅ No migration/upgrade scripts needed  
✅ No storage overhead  
✅ Always up-to-date values  
✅ Respects store context

## Technical Details

### Tax Rate Handling

The module correctly handles different tax rates:

```php
// Germany examples
19% VAT: 100.00 EUR → 119.00 EUR (regular products)
7% VAT:  100.00 EUR → 107.00 EUR (food items)
0% VAT:  100.00 EUR → 100.00 EUR (books, exports)
```

### Special Price Validation

Special prices are only used when valid:

```php
// Checks performed:
1. Special price exists (not null/empty)
2. Current date >= special_from_date (if set)
3. Current date <= special_to_date (if set)

// If valid: uses special_price_incl_tax
// If invalid: uses price_incl_tax
```

### Multi-Store Support

All calculations respect the product's store context:
- Tax rates per store
- URLs per store
- Base URLs per store

## Requirements

- **Magento**: ^2.4.8
- **PHP**: ^8.4
- **Dependencies**: 
  - Magento_Catalog
  - Magento_Tax
  - Magento_Store

## Compatibility

- ✅ Firebear Import/Export
- ✅ Magento Native Export
- ✅ Any export tool that loads products via standard Magento methods
- ✅ Hyvä Theme compatible
- ✅ Multi-store setups
- ✅ MSI (Multi-Source Inventory)

## Performance

**Impact**: Minimal

- Virtual attributes calculated only when accessed
- No database queries for attribute storage
- No additional indexing required
- Uses Magento's native caching for tax rates

**Export Speed**: ~0.01s additional processing time per 100 products

## Troubleshooting

### Attributes not appearing in Firebear dropdown

```bash
# Clear cache
php bin/magento cache:flush

# Reindex if needed
php bin/magento indexer:reindex

# Check module is enabled
php bin/magento module:status FlipDev_CustomAttributes
```

### Tax calculation incorrect

```bash
# Verify tax classes are configured
Admin → Stores → Tax Rules
Admin → Stores → Tax Zones and Rates

# Check product tax class
Product Edit → Advanced Settings → Tax Class
```

### URLs incorrect

```bash
# Verify store base URL
Admin → Stores → Configuration → Web → Base URLs

# Check product URL key
Product Edit → Search Engine Optimization → URL Key
```

## Support & Customization

For custom attributes or additional feed formats, modify:
- `Helper/Data.php` - Add new attribute codes
- `Helper/Price.php` - Custom price calculations
- `Helper/Url.php` - Custom URL/path formatting
- `Observer/AddCustomAttributesToCollection.php` - Add attribute logic

## License

MIT License

## Author

**Philipp Breitsprecher**  
Email: philippbreitsprecher@gmail.com

## Version History

### 1.0.0 (Initial Release)
- Price including tax calculation (19%/7%/0%)
- Full product URL generation
- Full image URL generation
- Category path formatting (Idealo)
- Multi-store support
- PHP 8.4 compatibility
