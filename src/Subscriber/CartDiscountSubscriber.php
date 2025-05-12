<?php declare(strict_types=1);

namespace AlengoCustomerDiscount\Subscriber;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService; // Korrigierter Namespace

class CartDiscountSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly CartService $cartService // CartService hinzugefügt
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Return the events to listen to as array like this:  <event to listen to> => <method to execute>
        return [
            CartChangedEvent::class => 'onCartChanged'
        ];
    }

    /**
     * @throws \Exception
     */
    public function onCartChanged(CartChangedEvent $event): void
    {
        $cart = $event->getCart();
        $salesChannelContext = $event->getContext();

        $context = $salesChannelContext->getContext(); // Context dynamisch aus SalesChannelContext holen
        $customer = $salesChannelContext->getCustomer(); // Customer aus SalesChannelContext holen

        if (!$customer) {
            return; // Kein Kunde vorhanden, keine Verarbeitung
        }

        $customerDiscountAmount = $customer->getCustomFields()['alengoCustomerDiscount_amount'] ?? null;
        $customerDiscountExpirationDate = $customer->getCustomFields()['alengoCustomerDiscount_expirationDate'] ?? null;

        if (!$customerDiscountAmount || !$customerDiscountExpirationDate) {
            return; // Keine Rabattdaten vorhanden, keine Verarbeitung
        }

        // compare current date with expiration date
        $currentDate = new \DateTime();
        $expirationDate = new \DateTime($customerDiscountExpirationDate);

        if ($currentDate <= $expirationDate) {
            $customerPromotionName = \strtoupper($customer->getCustomerNumber() . '-' . $customer->getLastName() . '-' . $customer->getFirstName() . '-' . $expirationDate->format('Y-m-d'));

            // search for existing promotion
            $isActivePromotion = $this->repository->search(
                (new Criteria())->addFilter(new EqualsFilter('name', $customerPromotionName)),
                $context
            )->first();

            if (!$isActivePromotion) {
                // create new promotion
                $promotionData = [
                    'id' => md5($customerPromotionName),
                    'name' => $customerPromotionName,
                    'active' => true,
                    'validFrom' => null,
                    'validUntil' => $expirationDate->format('Y-m-d H:i:s'),
                    'maxRedemptionsPerCustomer' => 1,
                    'maxRedemptionsGlobal' => 1,
                    'useCodes' => true,
                    'code' => $customerPromotionName,
                    'discounts' => [
                        [
                            'type' => 'absolute',
                            'value' => $customerDiscountAmount,
                            'scope' => 'cart',
                            'considerAdvancedRules' => false
                        ]
                    ],
                    'salesChannels' => [ // Verknüpfung mit dem Verkaufskanal
                        [
                            'salesChannelId' => $salesChannelContext->getSalesChannel()->getId(),
                            'priority' => 1
                        ]
                    ]
                ];

                try {
                    $this->repository->create([$promotionData], $context);
                } catch (\Exception $e) {
                    // Debugging: show errors
                    dump('Error creating promotion:', $e->getMessage());
                }

                $this->cartService->add(
                    $cart,
                    [new AddPromotionCommand($customerPromotionName)],
                    $salesChannelContext
                );
                // add voucher to cart using CartService
                $cart->setCampaignCode($customerPromotionName);
            }
        }
    }
}

