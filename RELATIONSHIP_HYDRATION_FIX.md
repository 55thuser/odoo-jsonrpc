# Relationship Hydration Fix for HasMany/BelongsTo

## Issue Summary
The original issue (#4) reported that HasMany and BelongsTo relationships were not being hydrated when loading models from Odoo. This caused errors like "must not be accessed before initialization" when trying to access relationship properties.

## Solution Overview

The fix implements automatic hydration of HasMany and BelongsTo relationships during the model loading process. When a model is fetched from Odoo, the relationships are automatically loaded and populated as proper model instances.

## Changes Made

### 1. Updated `BelongsTo` Attribute Class
**File:** `src/Attributes/BelongsTo.php`

Added constructor parameters to match HasMany structure:
```php
public function __construct(
    public string $class,  // The related model class
    public string $name    // The Odoo field name
)
```

### 2. Enhanced `HasFields` Trait
**File:** `src/Odoo/Mapping/HasFields.php`

#### Updated `fieldNames()` Method
Now includes relationship fields in the list of fields to fetch from Odoo:
- Processes HasMany attributes
- Processes BelongsTo attributes
- Ensures relationship data is included in API requests

#### Enhanced `hydrate()` Method
Added relationship hydration logic:

**HasMany Hydration:**
- Reads the array of IDs from the Odoo response
- Automatically calls `::read()` on the related model class to fetch all related records
- Populates the property with an array of hydrated model instances

**BelongsTo Hydration:**
- Handles Odoo's many2one format (returns `[id, name]` array)
- Extracts the ID from the response
- Automatically calls `::find()` on the related model class to fetch the related record
- Populates the property with a single hydrated model instance or null

#### Enhanced `dehydrate()` Method
Added BelongsTo handling for saving:
- Converts model instances to IDs
- Handles null values (converts to `false` for Odoo)

## Usage Examples

### Example 1: HasMany Relationship

```php
use Obuchmann\OdooJsonRpc\Attributes\Model;
use Obuchmann\OdooJsonRpc\Attributes\HasMany;
use Obuchmann\OdooJsonRpc\Attributes\Field;
use Obuchmann\OdooJsonRpc\Odoo\OdooModel;

#[Model('sale.order')]
class SaleOrder extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    #[HasMany(SaleOrderLine::class, 'order_line')]
    public array $orderLines = [];
}

#[Model('sale.order.line')]
class SaleOrderLine extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    #[Field('product_uom_qty')]
    public float $quantity;
}

// Usage
$order = SaleOrder::find(1);

// This now works! orderLines is automatically hydrated
foreach ($order->orderLines as $line) {
    echo $line->name . ': ' . $line->quantity . PHP_EOL;
}
```

### Example 2: BelongsTo Relationship

```php
use Obuchmann\OdooJsonRpc\Attributes\Model;
use Obuchmann\OdooJsonRpc\Attributes\BelongsTo;
use Obuchmann\OdooJsonRpc\Attributes\Field;
use Obuchmann\OdooJsonRpc\Odoo\OdooModel;

#[Model('sale.order.line')]
class SaleOrderLine extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    #[BelongsTo(SaleOrder::class, 'order_id')]
    public ?SaleOrder $order;
}

// Usage
$line = SaleOrderLine::find(1);

// This now works! order is automatically hydrated
if ($line->order) {
    echo 'Order: ' . $line->order->name . PHP_EOL;
}
```

### Example 3: Complex Relationships

```php
#[Model('stock.picking')]
class StockPicking extends OdooModel
{
    #[BelongsTo(Partner::class, 'partner_id')]
    public ?Partner $vendor;

    #[Field('name')]
    public string $reference;

    #[HasMany(StockMoveLine::class, 'move_line_ids')]
    public array $moveLines = [];
}

// Query with relationships
$pickings = StockPicking::query()
    ->where('state', '=', 'assigned')
    ->get();

foreach ($pickings as $picking) {
    // Access vendor (BelongsTo)
    echo 'Vendor: ' . $picking->vendor?->name . PHP_EOL;
    
    // Access move lines (HasMany)
    foreach ($picking->moveLines as $line) {
        echo '  Line: ' . $line->productQty . PHP_EOL;
    }
}
```

## Migration from Manual Relationships

### Before (Manual Approach)
```php
class SaleOrder extends OdooModel
{
    #[Field('order_line')]  // Just stores IDs
    public array $orderLineIds;
    
    public function getOrderLines(): array
    {
        return SaleOrderLine::read($this->orderLineIds);
    }
}
```

### After (Automatic Hydration)
```php
class SaleOrder extends OdooModel
{
    #[HasMany(SaleOrderLine::class, 'order_line')]
    public array $orderLines = [];  // Automatically hydrated!
}
```

## Performance Considerations

1. **Eager Loading:** Relationships are loaded automatically when the parent model is fetched. This means:
   - One additional API call per HasMany relationship
   - One additional API call per non-null BelongsTo relationship

2. **Optimization Tips:**
   - Use field selection in queries if you don't need relationships
   - Consider manual loading for large datasets
   - Cache frequently accessed relationships

## Testing

Tests have been added to verify the functionality:

1. **testHasManyHydration:** Verifies that HasMany relationships are properly hydrated as arrays of model instances
2. **testBelongsToHydration:** Verifies that BelongsTo relationships are properly hydrated as single model instances

## Backward Compatibility

The changes are backward compatible:
- Existing code using `#[Field]` attributes continues to work
- Manual relationship handling methods remain functional
- The dehydration process for saving HasMany relationships was already implemented

## Known Limitations

1. **Lazy Loading:** Relationships are not lazy-loaded. They are fetched immediately when the parent model is loaded.
2. **Circular References:** Be careful with circular relationships as they may cause infinite loops.
3. **Performance:** Each relationship requires an additional API call to Odoo.

## Future Enhancements

Potential improvements for future versions:
1. Implement lazy loading for relationships
2. Add support for HasManyThrough relationships
3. Implement relationship caching
4. Add query builder support for including/excluding relationships
5. Support for polymorphic relationships