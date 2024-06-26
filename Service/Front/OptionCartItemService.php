<?php

namespace Option\Service\Front;

use Option\Event\OptionUpdatePriceEvent;
use Option\Event\RemoveOptionUpdatePriceEvent;
use Option\Model\OptionCartItemOrderProduct;
use Option\Model\OptionCartItemOrderProductQuery;
use Option\Model\OptionProduct;
use Option\Model\ProductAvailableOption;
use Option\Model\ProductAvailableOptionQuery;
use Option\Service\OptionService;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\CartItem;
use Thelia\TaxEngine\Calculator;
use Thelia\TaxEngine\TaxEngine;

class OptionCartItemService
{
    protected ?Request $request;

    public function __construct(
        protected EventDispatcherInterface $dispatcher,
        protected OptionService $optionService,
        protected TaxEngine $taxEngine,
        RequestStack $request
    )
    {
        $this->request = $request->getCurrentRequest();
    }

    /**
     * @param CartItem $cartItem
     * @throws PropelException
     */
    public function handleCartItemOptionPrice(CartItem $cartItem,array $options): void
    {
        $totalCustoms = $this->calculateTotalCustomPrice($cartItem, $options);

        $event = new OptionUpdatePriceEvent();
        $event
            ->setCartItem($cartItem)
            ->setTotalCustoms($totalCustoms);

        $this->dispatcher->dispatch($event, OptionUpdatePriceEvent::OPTION_UPDATE_PRICE);

        $cartItem
            ->setPrice((float)$cartItem->getPrice() + $totalCustoms['totalCustomizationPrice'])
            ->setPromoPrice((float)$cartItem->getPromoPrice() + $totalCustoms['totalCustomizationPrice'])
            ->save()
        ;
    }

    /**
     * @param CartItem $cartItem
     * @throws PropelException
     */
    public function removeCartItemOptionPrice(CartItem $cartItem): void
    {
        $totalCustoms = $this->calculateTotalCustomPrice($cartItem);

        $event = new RemoveOptionUpdatePriceEvent();
        $event
            ->setCartItem($cartItem)
            ->setTotalCustoms($totalCustoms);

        $this->dispatcher->dispatch($event, RemoveOptionUpdatePriceEvent::REMOVE_OPTION_UPDATE_PRICE);

        $cartItem
            ->setPrice((float)$cartItem->getPrice() - $totalCustoms['totalCustomizationPrice'])
            ->setPromoPrice((float)$cartItem->getPromoPrice() - $totalCustoms['totalCustomizationPromoPrice'])
            ->save();
    }

    /**
     * @throws PropelException
     */
    public function calculateTotalCustomPrice(Cartitem $cartItem, array $options = []): array
    {
        if(!$options){
            $options = $this->getOptionsByCartItem($cartItem);
        }

        // Calculate option HT price with cartItem tax rule.
        $taxCalculator = $this->getTaxCalculator($cartItem);

        $totalCustomizationPrice = 0;
        $totalCustomizationPromoPrice = 0;

        /** @var OptionProduct $option */
        foreach ($options as $option) {
            $totalCustomizationPrice += $taxCalculator->getUntaxedPrice($this->optionService->getOptionTaxedPrice($option->getProduct()));
            $totalCustomizationPromoPrice += $taxCalculator->getUntaxedPrice($this->optionService->getOptionTaxedPrice($option->getProduct(), true));
        }

        return [
            'totalCustomizationPrice' => $totalCustomizationPrice,
            'totalCustomizationPromoPrice' => $totalCustomizationPromoPrice,
        ];
    }

    public function getOptionsByCartItem(CartItem $cartItem): array
    {
        $options = [];
        $productAvailableOptions = ProductAvailableOptionQuery::create()
            ->filterByProductId($cartItem->getProduct()->getId());

        /** @var ProductAvailableOption $productAvailableOption */
        foreach ($productAvailableOptions->find() as $productAvailableOption) {
            $options[] = $productAvailableOption->getOptionProduct();
        }

        return $options;
    }

    /**
     * @throws PropelException
     */
    public function persistCartItemCustomizationData(CartItem $cartItem, OptionProduct $optionProduct, array $formData): void
    {
        $productAvailableOption = ProductAvailableOptionQuery::create()
            ->filterByProductId($cartItem->getProductId())
            ->filterByOptionId($optionProduct->getId())
            ->findOne();

        if (null === $productAvailableOption) {
            return;
        }

        $optionCartItem = OptionCartItemOrderProductQuery::create()
            ->filterByProductAvailableOptionId($productAvailableOption->getId())
            ->filterByCartItemOptionId($cartItem->getId())->findOne();

        if (null === $optionCartItem) {
            $optionCartItem = new OptionCartItemOrderProduct();
        }

        $optionCartItem
            ->setCartItemOptionId($cartItem->getId())
            ->setProductAvailableOptionId($productAvailableOption->getId());

        $fields = ['optionId', 'optionCode', 'error_message', 'success_url', 'error_url'];

        $customization = array_filter($formData, function ($key) use ($fields) {
            return !in_array($key, $fields);
        }, ARRAY_FILTER_USE_KEY);

        $taxCalculator = $this->getTaxCalculator($cartItem);
        $price = $this->optionService->getOptionTaxedPrice($optionProduct->getProduct());
        $untaxedPrice = $taxCalculator->getUntaxedPrice($price);

        $optionCartItem
            ->setPrice($untaxedPrice)
            ->setTaxedPrice($price)
            ->setCustomizationData(json_encode($customization))
            ->setQuantity($cartItem->getQuantity())
            ->save();
    }

    /**
     * @param CartItem $cartItem
     * @return Calculator
     * @throws PropelException
     */
    private function getTaxCalculator(CartItem $cartItem): Calculator
    {
        $taxCalculator = new Calculator();
        $taxCalculator->load($cartItem->getProduct(), $this->taxEngine->getDeliveryCountry(), $this->taxEngine->getDeliveryState());
        return $taxCalculator;
    }
}