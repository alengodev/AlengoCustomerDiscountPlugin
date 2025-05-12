<?php declare(strict_types=1);

namespace AlengoCustomerDiscount\Core\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CustomerDiscountProcessor implements CartDataCollectorInterface, CartProcessorInterface
{
    private AbsolutePriceCalculator $absoluteCalculator;

    public function __construct(
        AbsolutePriceCalculator $absoluteCalculator,
        private readonly DeliveryProcessor $deliveryProcessor,
    )
    {
        $this->absoluteCalculator = $absoluteCalculator;
    }

    /**
     * @throws \Exception
     */
    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior,
    ): void {
        $products = $this->findProducts($toCalculate);

        // no products found => no discount
        if ($products->count() === 0) {
            return;
        }

        // get delivery from CartDataCollection ($data)
        /** @var DeliveryCollection $deliveries */
        $deliveries = $data->get('deliveries');
        $shippingCosts = 0;
        if ($deliveries instanceof DeliveryCollection) {
            $calculatedShipping = $deliveries->getShippingCosts()->first();
            $shippingCosts = $calculatedShipping?->getTotalPrice() ?? 0;
            $shippingTax = $calculatedShipping?->getCalculatedTaxes()->first() ?? 0;

            if ($shippingTax) {
                $shippingCosts += $shippingTax->getTax();
            }
        }

        $customer = $context->getCustomer();
        if ($customer === null) {
            return;
        }
        $customerDiscountName = $customer->getCustomFields()['alengoCustomerDiscount_name'] ?? null;
        $customerDiscountAmount = $customer->getCustomFields()['alengoCustomerDiscount_amount'] ?? null;
        $customerDiscountExpirationDate = $customer->getCustomFields()['alengoCustomerDiscount_expirationDate'] ?? null;

        if (!$customerDiscountName || !$customerDiscountAmount) {
            return; // no discount found
        }

        // compare current date with expiration date
        $currentDate = new \DateTime();
        $expirationDate = $customerDiscountExpirationDate === null ?
            (new \DateTime())->modify('+1 day') :
            (new \DateTime($customerDiscountExpirationDate))->setTime(23, 59, 59);
        if ($currentDate > $expirationDate) {
            return; // discount expired
        }

        // Check if the discount already exists in the cart
        $existingDiscount = $toCalculate->getLineItems()->filter(function (LineItem $item) use ($customerDiscountName) {
            return $item->getType() === 'special_discount' && $item->getLabel() === $customerDiscountName;
        });

        if ($existingDiscount->count() > 0) {
            return; // Discount already applied, no need to add it again
        }

        // Get the total cart value including tax + shipping costs
        $cartTotal = $toCalculate->getPrice()->getTotalPrice() + $shippingCosts;

        // Get the net cart value (without tax)
        $cartNetTotal = $toCalculate->getPrice()->getNetPrice();
        // get tax status & tax rate
        $taxStatus = $context->getTaxState();
        $taxRate = $toCalculate->getPrice()->getCalculatedTaxes()->first()->getTaxRate();
        $taxAmount = $toCalculate->getPrice()->getCalculatedTaxes()->first()->getTax();

        // Adjust the discount amount if it exceeds the cart total
        $adjustedDiscountAmount = min((float) $customerDiscountAmount, (float) $cartTotal);

        // set discount amount
        $priceValue = -1 * $adjustedDiscountAmount; // adjusted discount amount, add shipping costs AT

        $definition = new AbsolutePriceDefinition(
            $priceValue,
            $context->getCurrency()->getCountryRoundings()
        );

        // create an empty PriceCollection
        $priceCollection = new PriceCollection();

        // calculate the price
        $calculatedPrice = $this->absoluteCalculator->calculate($priceValue, $priceCollection, $context);

        // news line item for discount
        $discountLineItem = $this->createDiscount($customerDiscountName, $expirationDate);
        $discountLineItem->setPriceDefinition($definition);
        $discountLineItem->setPrice($calculatedPrice);

        // Ensure the discount line item is unique and does not interfere with other items
        $discountLineItem->setId(uniqid(uniqid($customerDiscountName . '_'), true));

        // add discount to cart
        $toCalculate->add($discountLineItem);
    }

    public function collect(
        CartDataCollection $data,
        Cart $original,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        // collection of deliveries
        $this->deliveryProcessor->collect($data, $original, $context, $behavior);

        // set deliveries to cart data collection
        $data->set('deliveries', $original->getDeliveries());
    }

    private function findProducts(Cart $cart): LineItemCollection
    {
        return $cart->getLineItems()->filter(function (LineItem $item) {
            return $item->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE;
        });
    }

    private function createDiscount(string $name, $expirationDate): LineItem
    {
        $discountLineItem = new LineItem(uniqid($name . '_'), 'special_discount', null, 1);

        $discountLineItem->setLabel($name);
        $discountLineItem->setDescription('Rabatt gültig bis ' . $expirationDate->format('d.m.Y'));
        $discountLineItem->setGood(false);       // kein kaufbares Gut
        $discountLineItem->setStackable(false);  // nicht mehrfach anwendbar
        $discountLineItem->setRemovable(false);  // nicht im Frontend löschbar

        return $discountLineItem;
    }
}

