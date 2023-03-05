<?php

declare(strict_types=1);

/**
 * Contains the ShippingFeeCalculationTest class.
 *
 * @copyright   Copyright (c) 2023 Vanilo UG
 * @author      Attila Fulop
 * @license     MIT
 * @since       2023-03-05
 *
 */

namespace Vanilo\Foundation\Tests;

use Vanilo\Adjustments\Contracts\AdjustmentCollection;
use Vanilo\Adjustments\Models\AdjustmentType;
use Vanilo\Cart\Facades\Cart;
use Vanilo\Checkout\Facades\Checkout;
use Vanilo\Foundation\Models\Product;
use Vanilo\Foundation\Shipping\FlatFeeCalculator;
use Vanilo\Shipment\Models\ShippingMethod;

class ShippingFeeCalculationTest extends TestCase
{
    /** @test */
    public function no_adjustment_gets_created_if_the_shipping_method_doesnt_have_a_calculator()
    {
        $product = factory(Product::class)->create();
        $shippingMethod = ShippingMethod::create(['name' => 'Free Delivery']);

        Cart::addItem($product);
        Checkout::setCart(Cart::getFacadeRoot());
        Checkout::setShippingMethodId($shippingMethod->id);

        $this->assertCount(0, Cart::adjustments()->byType(AdjustmentType::SHIPPING()));
        $this->assertEquals(Cart::itemsTotal(), Cart::total());
    }

    /** @test */
    public function it_creates_a_shipping_adjustment_when_setting_the_shipping_method_with_a_flat_fee_calculator()
    {
        $product = factory(Product::class)->create(['price' => 12.79]);
        $shippingMethod = ShippingMethod::create([
            'name' => 'Flat Fee',
            'calculator' => FlatFeeCalculator::ID,
            'configuration' => ['cost' => 4.99],
        ]);

        Cart::addItem($product);
        Checkout::setCart(Cart::getFacadeRoot());
        Checkout::setShippingMethodId($shippingMethod->id);

        /** @var AdjustmentCollection $shippingAdjustments */
        $shippingAdjustments = Cart::adjustments()->byType(AdjustmentType::SHIPPING());
        $this->assertCount(1, $shippingAdjustments);
        $shippingAdjustment = $shippingAdjustments->first();
        $this->assertEquals(4.99, $shippingAdjustment->getAmount());
        $this->assertTrue($shippingAdjustment->isCharge());
        $this->assertFalse($shippingAdjustment->isIncluded());
        $this->assertEquals(12.79, Cart::itemsTotal());
        $this->assertEquals(12.79 + 4.99, Cart::total());
    }

    /** @test */
    public function a_normal_shipping_fee_gets_calculated_when_the_free_shipping_threshold_is_not_exceeded()
    {
        $product = factory(Product::class)->create(['price' => 20]);
        $shippingMethod = ShippingMethod::create([
            'name' => 'Flat Fee with Free Threshold',
            'calculator' => FlatFeeCalculator::ID,
            'configuration' => ['cost' => 3.99, 'free_threshold' => 27.99],
        ]);

        Cart::addItem($product);
        Checkout::setCart(Cart::getFacadeRoot());
        Checkout::setShippingMethodId($shippingMethod->id);

        /** @var AdjustmentCollection $shippingAdjustments */
        $shippingAdjustments = Cart::adjustments()->byType(AdjustmentType::SHIPPING());
        $this->assertCount(1, $shippingAdjustments);
        $shippingAdjustment = $shippingAdjustments->first();
        $this->assertEquals(3.99, $shippingAdjustment->getAmount());
        $this->assertEquals(20, Cart::itemsTotal());
        $this->assertEquals(20 + 3.99, Cart::total());
    }

    /** @test */
    public function it_creates_a_shipping_adjustment_having_a_zero_sum_when_the_free_shipping_threshold_is_exceeded()
    {
        $product = factory(Product::class)->create(['price' => 30]);
        $shippingMethod = ShippingMethod::create([
            'name' => 'Flat Fee Free',
            'calculator' => FlatFeeCalculator::ID,
            'configuration' => ['cost' => 4.99, 'free_threshold' => 29.99],
        ]);

        Cart::addItem($product);
        Checkout::setCart(Cart::getFacadeRoot());
        Checkout::setShippingMethodId($shippingMethod->id);

        /** @var AdjustmentCollection $shippingAdjustments */
        $shippingAdjustments = Cart::adjustments()->byType(AdjustmentType::SHIPPING());
        $this->assertCount(1, $shippingAdjustments);
        $shippingAdjustment = $shippingAdjustments->first();
        $this->assertEquals(0, $shippingAdjustment->getAmount());
        $this->assertEquals(30, Cart::itemsTotal());
        $this->assertEquals(30, Cart::total());
    }
}
