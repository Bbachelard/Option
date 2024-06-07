<?php

namespace Option\Service;

use Exception;
use LogicException;
use Option\Event\CheckOptionEvent;
use Option\Model\OptionProductQuery;
use Option\Model\ProductAvailableOptionQuery;
use Option\Option as OptionModule;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Product\ProductDeleteEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductPrice;
use Thelia\Model\ProductQuery;
use Thelia\TaxEngine\TaxEngine;
use Thelia\Tools\URL;

/**
 *
 * One option is identical to the Thelia product model.
 * There is a link table that identifies a product as an option
 *
 * OptionProductCreateEvent extend ProductCreateEvent, it uses to identify an option creation.
 *
 */
class OptionService
{
    public function __construct(
        protected EventDispatcherInterface $dispatcher,
        protected OptionProvider           $optionProvider,
        protected TaxEngine                $taxEngine,
        protected RequestStack             $requestStack,
    )
    {
    }

    public function createOption(Form $form): void
    {
        $createEvent = $this->optionProvider->getCreationEvent($form->getData());
        $createEvent->bindForm($form);

        $this->dispatcher->dispatch($createEvent, TheliaEvents::PRODUCT_CREATE);
    }

    public function updateOption(Form $form): void
    {
        $data = $form->getData();
        $changeEvent = $this->optionProvider->getUpdateEvent($data);

        $changeEvent->bindForm($form);

        $this->dispatcher->dispatch($changeEvent, TheliaEvents::PRODUCT_UPDATE);

        if (!$changeEvent->hasProduct()) {
            throw new LogicException(
                Translator::getInstance()->trans('No Option was updated.')
            );
        }
    }

    public function deleteOption(int $productId): void
    {
        $this->dispatcher->dispatch(new ProductDeleteEvent($productId), TheliaEvents::PRODUCT_DELETE);
    }

    /**
     * @throws Exception
     */
    public function getOptionCategory($locale = 'en_US'): Category
    {
        if ($optionCategoryId = OptionModule::getConfigValue(OptionModule::OPTION_CATEGORY_ID)) {
            return CategoryQuery::create()->findPk($optionCategoryId);
        }

        $optionCategory = CategoryQuery::create()
            ->useCategoryI18nQuery()
            ->filterByTitle(OptionModule::OPTION_CATEGORY_TITLE)
            ->filterByLocale($locale)
            ->endUse()
            ->findOne();

        return $optionCategory ?? $this->createOptionCategory(OptionModule::OPTION_CATEGORY_TITLE);
    }

    /**
     * @throws Exception
     */
    public function createOptionCategory($title, $locale = 'en_US', $parent = 0): Category
    {
        try {
            $optionCategory = (new Category())
                ->setLocale($locale)
                ->setParent($parent)
                ->setVisible(0)
                ->setTitle($title);

            $optionCategory->save();

            OptionModule::setConfigValue(OptionModule::OPTION_CATEGORY_ID, $optionCategory->getId());
            return $optionCategory;

        } catch (Exception $ex) {
            throw new Exception(sprintf("Error during option category creation %s", $ex->getMessage()));
        }
    }

    /**
     * Retrieves and returns the list of products (which are options) attached to the product passed in parameter.
     * If the option id is specified, returns only the corresponding product in the product table.
     *
     * @param Product $product
     * @param null $optionProduct
     * @return array|null
     */
    public function getProductAvailableOptions(Product $product, $optionProduct = null): ?array
    {
        $productAvailableOptions = ProductAvailableOptionQuery::create()
            ->filterByProductId($product->getId());

        if ($optionProduct) {
            $productAvailableOptions->filterByOptionId($optionProduct->getId());
        }

        $options = array_map(static function ($productAvailableOption) {
            return $productAvailableOption->getOptionProduct();
        }, iterator_to_array($productAvailableOptions->find()));

        $event = new CheckOptionEvent();
        $event
            ->setIsValid(true)
            ->setOptions($options)
            ->setProduct($product);

        $this->dispatcher->dispatch($event, CheckOptionEvent::OPTION_CHECK_IS_VALID);

        return false === $event->isValid() ? [] : $event->getOptions();
    }

    public function getOptionPrice(Product $option, bool $isPromo = false, $isTaxed = true): float|int
    {
        $taxCountry = $this->taxEngine->getDeliveryCountry();
        $taxState = $this->taxEngine->getDeliveryState();
        $optionPse = $option->getDefaultSaleElements();

        /** @var ProductPrice $optionPseProductPrice */
        $optionPseProductPrice = $optionPse->getProductPrices()->getFirst();

        $optionPrice = $optionPseProductPrice->getPrice();
        if ($isPromo) {
            $optionPrice = $optionPseProductPrice->getPromoPrice();
        }

        if (!$isTaxed) {
            return $optionPrice;
        }

        return $option->getTaxedPrice($taxCountry, $optionPrice, $taxState);
    }

    public function getOptionTaxedPrice(Product $option, bool $isPromo = false): float|int
    {
        return $this->getOptionPrice($option, $isPromo);
    }


    public function getOptionUnTaxedPrice(Product $option, bool $isPromo = false): float|int
    {
        return $this->getOptionPrice($option, $isPromo, false);
    }


    /**
     * @param bool $withPrivateData
     * @return array
     */
    public function defineColumnsDefinition($withPrivateData = false)
    {
        $i = -1;
        $definitions = [
            [
                'name' => 'id',
                'targets' => ++$i,
                'orm' => ProductTableMap::ID,
                'title' => 'Id',
                'orderable' => true,
                'searchable' => false
            ],
            [
                'name' => 'images',
                'targets' => ++$i,
                'title' => 'Image',
                'orderable' => false,
                'searchable' => false
            ],
            [
                'name' => 'ref',
                'targets' => ++$i,
                'orm' => ProductTableMap::REF,
                'title' => 'Référence',
                'orderable' => true,
                'searchable' => true
            ],
            [
                'name' => 'title',
                'targets' => ++$i,
                'orm' => 'product_i18n_TITLE',
                'title' => 'Titre',
                'orderable' => true,
                'searchable' => true
            ],
            [
                'name' => 'price',
                'targets' => ++$i,
                'orm' => 'price',
                'title' => 'Prix',
                'orderable' => true,
                'searchable' => false
            ],
            [
                'name' => 'Online',
                'targets' => ++$i,
                'title' => 'Online',
                'orderable' => true,
                'searchable' => false
            ],
            [
                'name' => 'action',
                'targets' => ++$i,
                'title' => 'Action',
                'orderable' => true,
                'searchable' => false
            ]
        ];

        if (!$withPrivateData) {
            foreach ($definitions as &$definition) {
                unset($definition['orm']);
            }
        }

        return $definitions;
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function getOrderColumnName(Request $request)
    {

        $columnDefinition = $this->defineColumnsDefinition(true)[(int)$request->get('order')[0]['column']];

        return $columnDefinition['orm'];
    }

    protected function getOrderDir(Request $request)
    {
        return (string)$request->get('order')[0]['dir'] === 'asc' ? Criteria::ASC : Criteria::DESC;
    }

    protected function applyOrder(Request $request, OptionProductQuery $query)
    {
        $query->orderBy(
            $this->getOrderColumnName($request),
            $this->getOrderDir($request)
        );
    }

    public function listAction()
    {
        $request = $this->requestStack->getCurrentRequest();

        $query = OptionProductQuery::create();
        $query->useProductQuery()
            ->endUse()
            ->find();
        $query->useProductQuery()->endUse();
        //$this->applyOrder($request, $query);

        $query->offset($this->getOffset($request));
        $campaigns = $query->limit($this->getLength($request))->find();
        $json = [
            "data" => [],
            "campaigns" => count($campaigns->getData()),
        ];
        foreach ($campaigns as $campaign) {
            $json['data'][] = [
                [
                    'product_ids' => $campaign->getProductId(),
                    'href' => URL::getInstance()->absoluteUrl('/admin/module/option/' . $campaign->getId())
                ],
                $campaign->getProduct()->getTitle(),
                $campaign->getProduct()->getRef(),
                [
                    "url" => URL::getInstance()->absoluteUrl('admin/module/option/campaign/' . $campaign->getId())
                ]
            ];
        }

        //dd($json);
        return new JsonResponse(array('optionFilter' => $json));
    }


    /**
     * @param Request $request
     * @return int
     */
    protected function getLength(Request $request)
    {
        return (int) $request->get('length');
    }
    /**
     * @param Request $request
     * @return int
     */
    protected function getOffset(Request $request)
    {
        return (int) $request->get('start');
    }

}