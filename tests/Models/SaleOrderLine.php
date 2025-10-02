<?php

namespace Obuchmann\OdooJsonRpc\Tests\Models;

use Obuchmann\OdooJsonRpc\Attributes\BelongsTo;
use Obuchmann\OdooJsonRpc\Attributes\Field;
use Obuchmann\OdooJsonRpc\Attributes\Model;
use Obuchmann\OdooJsonRpc\Odoo\OdooModel;

#[Model('sale.order.line')]
class SaleOrderLine extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    #[Field('product_id')]
    public int $productId;
    
    #[BelongsTo(SaleOrder::class, 'order_id')]
    public ?SaleOrder $order;
    
    #[Field('product_uom_qty')]
    public float $quantity;
    
    #[Field('price_unit')]
    public float $priceUnit;
}