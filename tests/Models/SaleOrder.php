<?php

namespace Obuchmann\OdooJsonRpc\Tests\Models;

use Obuchmann\OdooJsonRpc\Attributes\Field;
use Obuchmann\OdooJsonRpc\Attributes\HasMany;
use Obuchmann\OdooJsonRpc\Attributes\Model;
use Obuchmann\OdooJsonRpc\Odoo\OdooModel;

#[Model('sale.order')]
class SaleOrder extends OdooModel
{
    #[Field('name')]
    public string $name;
    
    #[Field('partner_id')]
    public int $partnerId;
    
    #[HasMany(SaleOrderLine::class, 'order_line')]
    public array $orderLines = [];
    
    #[Field('date_order')]
    public \DateTime $dateOrder;
}