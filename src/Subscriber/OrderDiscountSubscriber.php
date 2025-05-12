<?php

declare(strict_types=1);

namespace AlengoCustomerDiscount\Subscriber;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderDiscountSubscriber implements EventSubscriberInterface
{
    private EntityRepository $customerRepository;

    public function __construct(EntityRepository $customerRepository)
    {
        $this->customerRepository = $customerRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();
        $context = $event->getContext();

        $customer = $order->getOrderCustomer();
        $customerId = $customer?->getCustomerId();

        if (!$customerId) {
            return;
        }

        $customerDiscountAmount = $customer->getCustomFields()['alengoCustomerDiscount_amount'] ?? null;

        // discover discounts
        $discountTotal = 0.0;
        foreach ($order->getLineItems() as $lineItem) {
            if ('special_discount' === $lineItem->getType() && $lineItem->getPrice()) {
                $discountTotal += abs($lineItem->getPrice()->getTotalPrice());
            }
        }

        $newDiscountAmount = $customerDiscountAmount - $discountTotal;

        // update amount in custom fields
        $this->customerRepository->update([
            [
                'id' => $customerId,
                'customFields' => [
                    'alengoCustomerDiscount_amount' => max(0, $newDiscountAmount),
                ],
            ],
        ], $context);
    }
}
