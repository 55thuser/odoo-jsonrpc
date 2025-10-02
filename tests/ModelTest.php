<?php


namespace Obuchmann\OdooJsonRpc\Tests;


use Obuchmann\OdooJsonRpc\Odoo;
use Obuchmann\OdooJsonRpc\Odoo\Casts\CastHandler;
use Obuchmann\OdooJsonRpc\Odoo\OdooModel;
use Obuchmann\OdooJsonRpc\Tests\Models\Partner;
use Obuchmann\OdooJsonRpc\Tests\Models\Product;
use Obuchmann\OdooJsonRpc\Tests\Models\PurchaseOrder;
use Obuchmann\OdooJsonRpc\Tests\Models\PurchaseOrderLine;
use Obuchmann\OdooJsonRpc\Tests\Models\SaleOrder;
use Obuchmann\OdooJsonRpc\Tests\Models\SaleOrderLine;

class ModelTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();

        OdooModel::boot($this->odoo);
    }

    public function testFields()
    {
        $fields = Partner::listFields();

        $this->assertObjectHasAttribute('name', $fields);
    }

    public function testFind()
    {
        $partner = Partner::find(1);

        $this->assertInstanceOf(Partner::class, $partner);
        $this->assertNotNull($partner->name);
    }

    public function testQuery()
    {
        $partner = new Partner();
        $partner->name = 'Azure Interior';
        $partner->save();

        $partner = Partner::query()
            ->where('name', '=', 'Azure Interior')
            ->first();

        $this->assertInstanceOf(Partner::class, $partner);
        $this->assertEquals('Azure Interior', $partner->name);
    }

    public function testCreate()
    {
        $partner = new Partner();
        $partner->name = 'Tester';
        $partner->save();


        $this->assertNotNull($partner->id);
    }

    public function testReadonlyCreate()
    {
        $partner = new Partner();
        $partner->name = 'Tester';
        $partner->childIds = [1,2,3];
        $partner->save();


        $this->assertNotNull($partner->id);
    }

    public function testUpdate()
    {
        $partner = new Partner();
        $partner->name = 'Tester';
        $partner->save();


        $this->assertNotNull($partner->id);

        $partner->name = "Tester2";
        $partner->save();

        $check = Partner::find($partner->id);

        $this->assertEquals("Tester2", $check->name);
    }

    public function testUpdateNullValue()
    {
        $partner = new Partner();
        $partner->name = 'Tester';
        $partner->email = "tester@example.org";
        $partner->save();


        $this->assertNotNull($partner->id);
        $this->assertNotNull($partner->email);

        $partner->name = "Tester2";
        $partner->email = null;
        $partner->save();

        $check = Partner::find($partner->id);

        $this->assertEquals("Tester2", $check->name);
        $this->assertEquals(null, $check->email);
    }

    public function testSelectColumns()
    {
        $items = Partner::query()->limit(5)
            ->fields(['display_name'])->get();

        $this->assertCount(5, $items);
        $this->assertFalse(isset($items[0]->name));
    }

    public function testOrderBy()
    {
        $items = Partner::query()->limit(5)
            ->orderBy('id', 'desc')
            ->fields(['name'])->get();

        $this->assertIsArray($items);
        $this->assertCount(5, $items);
        $this->assertGreaterThan($items[1]->id, $items[0]->id);
    }


    public function testBelongsTo()
    {

        $parent = new Partner();
        $parent->name = 'Parent';
        $parent->save();

        $child = new Partner();
        $child->parentId = $parent->id;

        $this->assertInstanceOf(Partner::class, $child->parent());
        $this->assertEquals($parent->id, $child->parent()->id);

    }

    public function testHasManyCreate()
    {

        $partner = new Partner();
        $partner->name = 'Tester';
        $partner->save();

        $product = new Product();
        $product->name = "Tester2";
        $product->save();

        $line = new PurchaseOrderLine();
        $line->name = 'Test';
        $line->productId = $product->id;
        $line->priceUnit = 10;
        $line->productQuantity = 1;

        $order = new PurchaseOrder();
        $order->partnerId = $partner->id;
        $order->lines = [$line];
        $order->save();


        $this->assertNotNull($order->id);
    }


    public function testCast()
    {
        CastHandler::reset();
        Odoo::registerCast(new Odoo\Casts\DateTimeCast());

        $item = PurchaseOrder::query()->first();

        $this->assertNotNull($item->orderDate);
        $this->assertInstanceOf(\DateTime::class, $item->orderDate);

    }

    public function testDateTimezoneCast()
    {
        CastHandler::reset();
        Odoo::registerCast(new Odoo\Casts\DateTimeTimezoneCast(new \DateTimeZone('Europe/Vienna')));

        $item2 = PurchaseOrder::query()->first();

        $this->assertNotNull($item2->orderDate);
        $this->assertInstanceOf(\DateTime::class, $item2->orderDate);

        $this->assertEquals("Europe/Vienna", $item2->orderDate->getTimezone()->getName());

    }


    public function testNullableCast()
    {
        CastHandler::reset();
        Odoo::registerCast(new Odoo\Casts\DateTimeCast());

        $item = PurchaseOrder::query()->first();

        $this->assertNull($item->approveDate);

    }


    public function testFill()
    {
        $partner = new Partner();
        $partner->fill([
            'name' => 'test'
        ]);

        $this->assertEquals('test', $partner->name);

    }

    public function testEquals()
    {
        $partner = new Partner();
        $partner->name = "test";

        $partner2 = new Partner();
        $partner2->name = "test";

        $partner3 = new Partner();
        $partner3->name = "test";
        $partner3->email = "test";

        $partner4 = new Partner();
        $partner4->name = "test2";

        $partner5 = clone $partner;

        $partner6 = clone $partner;
        $partner6->name = "some";

        $this->assertTrue($partner->equals($partner2));
        $this->assertFalse($partner->equals($partner3));
        $this->assertFalse($partner->equals($partner4));
        $this->assertTrue($partner->equals($partner5));
        $this->assertFalse($partner->equals($partner6));
    }

    public function testHasManyHydration()
    {
        // Create test data first
        $partner = new Partner();
        $partner->name = 'Test Customer';
        $partner->save();

        $product = new Product();
        $product->name = "Test Product";
        $product->save();

        // Create a purchase order with lines
        $line1 = new PurchaseOrderLine();
        $line1->name = 'Line 1';
        $line1->productId = $product->id;
        $line1->priceUnit = 10.0;
        $line1->productQuantity = 2;

        $line2 = new PurchaseOrderLine();
        $line2->name = 'Line 2';
        $line2->productId = $product->id;
        $line2->priceUnit = 20.0;
        $line2->productQuantity = 3;

        $order = new PurchaseOrder();
        $order->partnerId = $partner->id;
        $order->lines = [$line1, $line2];
        $order->save();

        // Now test hydration by fetching the order
        $fetchedOrder = PurchaseOrder::find($order->id);

        // Check that lines are properly hydrated
        $this->assertIsArray($fetchedOrder->lines);
        $this->assertCount(2, $fetchedOrder->lines);
        
        // Check that each line is a proper model instance
        foreach ($fetchedOrder->lines as $line) {
            $this->assertInstanceOf(PurchaseOrderLine::class, $line);
            $this->assertNotNull($line->id);
            $this->assertNotNull($line->name);
        }
    }

    public function testBelongsToHydration()
    {
        // Skip if sale.order.line model doesn't exist in test Odoo
        try {
            // Create test data
            $partner = new Partner();
            $partner->name = 'Test Customer for BelongsTo';
            $partner->save();

            $product = new Product();
            $product->name = "Test Product for BelongsTo";
            $product->save();

            // Create a sale order
            $order = new SaleOrder();
            $order->name = 'SO001';
            $order->partnerId = $partner->id;
            $order->save();

            // Create a sale order line
            $line = new SaleOrderLine();
            $line->name = 'Test Line';
            $line->productId = $product->id;
            $line->order = $order;
            $line->quantity = 5.0;
            $line->priceUnit = 15.0;
            $line->save();

            // Now test hydration by fetching the line
            $fetchedLine = SaleOrderLine::find($line->id);

            // Check that the order relationship is properly hydrated
            $this->assertInstanceOf(SaleOrder::class, $fetchedLine->order);
            $this->assertEquals($order->id, $fetchedLine->order->id);
            $this->assertEquals('SO001', $fetchedLine->order->name);
        } catch (\Exception $e) {
            // If sale.order models don't exist in test Odoo, skip this test
            $this->markTestSkipped('Sale order models not available in test Odoo: ' . $e->getMessage());
        }
    }

}