<?php

namespace Obuchmann\OdooJsonRpc\Tests;

use Obuchmann\OdooJsonRpc\Attributes\BelongsTo;
use Obuchmann\OdooJsonRpc\Attributes\Field;
use Obuchmann\OdooJsonRpc\Attributes\HasMany;
use Obuchmann\OdooJsonRpc\Attributes\Key;
use Obuchmann\OdooJsonRpc\Attributes\Model;
use Obuchmann\OdooJsonRpc\Odoo;
use Obuchmann\OdooJsonRpc\Odoo\OdooModel;

/**
 * Example demonstrating the relationship hydration feature
 * Based on the issue #4 and the provided example from rjocoleman
 */

#[Model('stock.picking')]
class StockPicking extends OdooModel
{
    #[BelongsTo(ResPartner::class, 'partner_id')]
    public ?ResPartner $vendor;

    #[Field('name')]
    public string $reference;

    #[Field('date')]
    public \DateTime $date;

    #[HasMany(StockMoveLine::class, 'move_line_ids')]
    public array $moveLines = [];
}

#[Model('res.partner')]
class ResPartner extends OdooModel
{
    #[Field]
    public string $name;

    #[Field('email')]
    public ?string $email;
}

#[Model('stock.move.line')]
class StockMoveLine extends OdooModel
{
    #[BelongsTo(StockMove::class, 'move_id')]
    public ?StockMove $move;

    #[Field('price_unit')]
    public ?float $priceUnit;

    #[Field('product_qty')]
    public int $productQty;

    #[Field('quantity_done')]
    public int $quantityDone;
}

#[Model('stock.move')]
class StockMove extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    #[Field('product_id'), Key]
    public int $productId;
}

// Example usage:
class RelationshipHydrationExample
{
    public function demonstrateHydration(Odoo $odoo)
    {
        // Boot the OdooModel with the Odoo instance
        OdooModel::boot($odoo);
        
        // Query stock pickings with relationships
        $pickings = StockPicking::query()
            ->where('partner_id', '=', 1)
            ->where('state', '=', 'assigned')
            ->get();

        // Access hydrated relationships - these will now work!
        if (!empty($pickings)) {
            $firstPicking = $pickings[0];
            
            // HasMany relationship - automatically hydrated as array of StockMoveLine models
            foreach ($firstPicking->moveLines as $moveLine) {
                echo "Move Line Quantity: " . $moveLine->productQty . PHP_EOL;
                
                // BelongsTo relationship on the move line
                if ($moveLine->move) {
                    echo "Move Name: " . $moveLine->move->name . PHP_EOL;
                }
            }
            
            // BelongsTo relationship - automatically hydrated as ResPartner model
            if ($firstPicking->vendor) {
                echo "Vendor Name: " . $firstPicking->vendor->name . PHP_EOL;
                echo "Vendor Email: " . $firstPicking->vendor->email . PHP_EOL;
            }
        }
    }
    
    /**
     * Demonstrates the original issue scenario that now works
     */
    public function demonstrateSaleOrderScenario(Odoo $odoo)
    {
        OdooModel::boot($odoo);
        
        // Situation 2 from the issue - now works!
        $order = SaleOrderExample::find(1);
        
        // These now work without errors:
        $lines = $order->orderLines; // Direct property access
        
        // Or using a method
        $linesViaMethod = $order->getOrderLines(); // Custom method
        
        foreach ($lines as $line) {
            echo "Line: " . $line->name . " - Qty: " . $line->quantity . PHP_EOL;
        }
    }
}

#[Model('sale.order')]
class SaleOrderExample extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    #[HasMany(SaleOrderLineExample::class, 'order_line')]
    public array $orderLines = [];
    
    public function getOrderLines(): array
    {
        // This now works because $this->orderLines is already hydrated
        return $this->orderLines;
    }
}

#[Model('sale.order.line')]
class SaleOrderLineExample extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    #[Field('product_uom_qty')]
    public float $quantity;
    
    #[Field('price_unit')]
    public float $priceUnit;
}