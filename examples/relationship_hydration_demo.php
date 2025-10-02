<?php

/**
 * Demonstration of the Relationship Hydration Feature
 * 
 * This example shows how the HasMany and BelongsTo relationships
 * are now automatically hydrated when fetching models from Odoo.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Obuchmann\OdooJsonRpc\Odoo;
use Obuchmann\OdooJsonRpc\Odoo\OdooModel;
use Obuchmann\OdooJsonRpc\Attributes\Model;
use Obuchmann\OdooJsonRpc\Attributes\Field;
use Obuchmann\OdooJsonRpc\Attributes\HasMany;
use Obuchmann\OdooJsonRpc\Attributes\BelongsTo;
use Obuchmann\OdooJsonRpc\Attributes\Key;

// ============================================================================
// BEFORE THE FIX (Issue #4 - This would cause errors)
// ============================================================================

echo "=== BEFORE THE FIX (Would cause errors) ===\n\n";

#[Model('sale.order')]
class SaleOrderOld extends OdooModel
{
    // Without relationship attributes, just storing IDs
    #[Field('order_line')]
    public array $orderLineIds;
    
    #[Field('name')]
    public string $name;
    
    // Manual method to fetch lines
    public function getOrderLines(): array
    {
        // This was the workaround - manually calling read()
        return SaleOrderLineOld::read($this->orderLineIds);
    }
}

#[Model('sale.order.line')]
class SaleOrderLineOld extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    #[Field('product_uom_qty')]
    public float $quantity;
}

// This approach required manual fetching:
/*
$oldOrder = SaleOrderOld::find(1);
// $oldOrder->orderLineIds contains [1, 2, 3] - just IDs

// Manual fetch required:
$lines = $oldOrder->getOrderLines(); // Extra method call needed
*/

// ============================================================================
// AFTER THE FIX (Automatic Hydration)
// ============================================================================

echo "=== AFTER THE FIX (Automatic Hydration) ===\n\n";

#[Model('sale.order')]
class SaleOrder extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    #[Field('partner_id'), Key]
    public int $partnerId;
    
    // HasMany relationship - automatically hydrated!
    #[HasMany(SaleOrderLine::class, 'order_line')]
    public array $orderLines = [];
    
    #[Field('amount_total')]
    public float $amountTotal;
}

#[Model('sale.order.line')]
class SaleOrderLine extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    // BelongsTo relationship - automatically hydrated!
    #[BelongsTo(SaleOrder::class, 'order_id')]
    public ?SaleOrder $order;
    
    #[BelongsTo(Product::class, 'product_id')]
    public ?Product $product;
    
    #[Field('product_uom_qty')]
    public float $quantity;
    
    #[Field('price_unit')]
    public float $priceUnit;
}

#[Model('product.product')]
class Product extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    #[Field('list_price')]
    public float $listPrice;
    
    // HasMany relationship to order lines
    #[HasMany(SaleOrderLine::class, 'product_id')]
    public array $orderLines = [];
}

// ============================================================================
// USAGE EXAMPLES
// ============================================================================

// Initialize Odoo connection
$odoo = new Odoo();
$odoo->connect('http://localhost:8069', 'odoo', 'admin', 'admin');
OdooModel::boot($odoo);

echo "1. Fetching a Sale Order with HasMany relationship:\n";
echo "---------------------------------------------------\n";

try {
    // Find a sale order
    $order = SaleOrder::find(1);
    
    echo "Order: {$order->name}\n";
    echo "Total: \${$order->amountTotal}\n";
    echo "Number of lines: " . count($order->orderLines) . "\n\n";
    
    // The orderLines property is automatically hydrated with SaleOrderLine instances!
    echo "Order Lines:\n";
    foreach ($order->orderLines as $line) {
        echo "  - {$line->name}: {$line->quantity} x \${$line->priceUnit}\n";
        
        // The product BelongsTo relationship is also hydrated!
        if ($line->product) {
            echo "    Product: {$line->product->name} (List Price: \${$line->product->listPrice})\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n2. Fetching an Order Line with BelongsTo relationship:\n";
echo "-------------------------------------------------------\n";

try {
    // Find a specific order line
    $line = SaleOrderLine::find(1);
    
    echo "Line: {$line->name}\n";
    echo "Quantity: {$line->quantity}\n";
    echo "Price: \${$line->priceUnit}\n";
    
    // The order BelongsTo relationship is automatically hydrated!
    if ($line->order) {
        echo "Belongs to Order: {$line->order->name}\n";
        echo "Order Total: \${$line->order->amountTotal}\n";
    }
    
    // The product BelongsTo relationship is also hydrated!
    if ($line->product) {
        echo "Product: {$line->product->name}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n3. Querying with relationships:\n";
echo "--------------------------------\n";

try {
    // Query orders with automatic relationship hydration
    $orders = SaleOrder::query()
        ->where('amount_total', '>', 1000)
        ->limit(5)
        ->get();
    
    foreach ($orders as $order) {
        echo "Order: {$order->name} - \${$order->amountTotal}\n";
        
        // All relationships are hydrated automatically!
        foreach ($order->orderLines as $line) {
            echo "  - {$line->quantity} x {$line->product?->name ?? 'Unknown'}\n";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n4. Creating records with relationships:\n";
echo "----------------------------------------\n";

try {
    // Create a new order with lines
    $newOrder = new SaleOrder();
    $newOrder->name = 'SO/TEST/001';
    $newOrder->partnerId = 1; // Assuming partner with ID 1 exists
    
    // Create order lines
    $line1 = new SaleOrderLine();
    $line1->name = 'Test Product 1';
    $line1->quantity = 5;
    $line1->priceUnit = 100;
    
    $line2 = new SaleOrderLine();
    $line2->name = 'Test Product 2';
    $line2->quantity = 3;
    $line2->priceUnit = 200;
    
    // Assign lines to order (they'll be created automatically)
    $newOrder->orderLines = [$line1, $line2];
    
    // Save the order (lines are saved automatically)
    $newOrder->save();
    
    echo "Created order: {$newOrder->name} with ID: {$newOrder->id}\n";
    echo "Order has " . count($newOrder->orderLines) . " lines\n";
    
    // Fetch it back to verify relationships are hydrated
    $fetchedOrder = SaleOrder::find($newOrder->id);
    echo "Fetched back - Lines count: " . count($fetchedOrder->orderLines) . "\n";
    foreach ($fetchedOrder->orderLines as $line) {
        echo "  - {$line->name}: {$line->quantity} x \${$line->priceUnit}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== SUMMARY ===\n";
echo "The relationship hydration feature provides:\n";
echo "1. Automatic loading of HasMany relationships as arrays of models\n";
echo "2. Automatic loading of BelongsTo relationships as single models\n";
echo "3. No need for manual ::read() or ::find() calls\n";
echo "4. Clean, intuitive syntax for accessing related data\n";
echo "5. Support for nested relationships\n";