<?php

namespace Option\Service;

use JsonException;
use Option\Model\CategoryAvailableOptionQuery;
use Option\Model\OptionProductQuery;
use Option\Model\ProductAvailableOptionQuery;
use Option\Model\TemplateAvailableOptionQuery;
use Propel\Runtime\Exception\PropelException;
use Thelia\Model\Category;
use Thelia\Model\CategoryQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\Template;


class OptionProductService
{
    public const ADDED_BY_PRODUCT = 1;
    public const ADDED_BY_CATEGORY = 2;
    public const ADDED_BY_TEMPLATE = 3;

    /**
     * Sets an option on a product.
     *
     * @param int $productId
     * @param int $optionId
     * @param int $addedBy origin of the new added option
     * @return void
     * @throws PropelException|JsonException
     */
    public function setOptionOnProduct(int $productId, int $optionId, int $addedBy = 1): void
    {
        $product = ProductQuery::create()->findPk($productId);
        $option = OptionProductQuery::create()->findPk($optionId);
        $curentProductAvailableOption = ProductAvailableOptionQuery::create()->filterByProductId($productId)
            ->filterByOptionId($optionId)->find();

        $newAddedBy = [];
        $curentAddedBy = $curentProductAvailableOption?->getColumnValues('OptionAddedBy');
        if ($curentAddedBy) {
            foreach ($curentAddedBy[0] as $item) {
                if ($item !== $addedBy) {
                    $newAddedBy[] = $item;
                }
            }
        }
        $newAddedBy[] = $addedBy;

        ProductAvailableOptionQuery::create()
            ->filterByProductId($product->getId())
            ->filterByOptionId($option->getId())
            ->findOneOrCreate()
            ->setOptionAddedBy(json_encode($newAddedBy, JSON_THROW_ON_ERROR))
            ->save();
    }

    /**
     * Sets an option on Category's products.
     *
     * @param Category $category
     * @param int $optionId
     * @return void
     * @throws PropelException|JsonException
     */
    public function setOptionOnCategoryProducts(Category $category, int $optionId): void
    {
        $products = [];
        $this->setCategoryTree($category, $optionId, $products);

        foreach ($products as $product) {
            $this->setOptionOnProduct($product->getId(), $optionId, self::ADDED_BY_CATEGORY);
        }
    }

    /**
     * Sets an option on Template's products.
     *
     * @param Template $template
     * @param int $optionId
     * @return void
     * @throws PropelException|JsonException
     */
    public function setOptionOnTemplateProducts(Template $template, int $optionId): void
    {
        TemplateAvailableOptionQuery::create()
            ->filterByTemplateId($template->getId())
            ->filterByOptionId($optionId)
            ->findOneOrCreate()
            ->save();

        foreach ($template->getProducts() as $product) {
            $this->setOptionOnProduct($product->getId(), $optionId, self::ADDED_BY_TEMPLATE);
        }
    }

    /**
     * Removes an option according to its origin.
     *
     * Only options with a single origin or removed on the product (OptionAddedBy column) are completely deleted.
     * If an option has been added to the product by a category and a template, only the origin of the option that is
     * being deleted is removed.
     *
     * If you want to completely remove the option from the product, even if it has multiple origins, just pass the
     * optional parameter $force=true)
     *
     * @param int $optionId
     * @param int $productId
     * @param int $deletedBy
     * @param bool $force if TRUE, removes option totaly.
     * @return void
     * @throws PropelException
     */
    public function deleteOptionOnProduct(int $optionId, int $productId, int $deletedBy = 1, bool $force = false): void
    {
        $productAvailableOption = ProductAvailableOptionQuery::create()
            ->filterByOptionId($optionId)
            ->filterByProductId($productId)
            ->findOne();

        if (null !== $productAvailableOption) {
            $addedBy = $productAvailableOption->getOptionAddedBy();
            if (!$force && (count($addedBy) > 1)) {
                unset($addedBy[array_search($deletedBy, $addedBy, true)]);
                $productAvailableOption->setOptionAddedBy($addedBy)->save();
            } else {
                $productAvailableOption->delete();
            }
        }
    }

    /**
     *
     * @throws PropelException
     */
    public function deleteOptionOnCategoryTree(Category $category, int $optionId, bool $deleteAll): void
    {
        if ($deleteAll) {
            $categoryChildren = CategoryQuery::create()->filterByParent($category->getId())->find();

            if ($categoryChildren) {
                foreach ($categoryChildren as $categoryChild) {
                    CategoryAvailableOptionQuery::create()
                        ->filterByCategoryId($categoryChild->getId())
                        ->filterByOptionId($optionId)
                        ->delete();
                }
            }

            foreach ($category->getProducts() as $product) {
                $this->deleteOptionOnProduct($optionId, $product->getId(), self::ADDED_BY_CATEGORY);
            }
        }

        CategoryAvailableOptionQuery::create()
            ->filterByCategoryId($category->getId())
            ->filterByOptionId($optionId)
            ->delete();

    }

    /**
     * @throws PropelException
     */
    public function deleteOptionOnTemplateProducts(Template $template, int $optionId): void
    {
        TemplateAvailableOptionQuery::create()
            ->filterByOptionId($optionId)
            ->filterByTemplateId($template->getId())
            ->delete();

        foreach ($template->getProducts() as $product) {
            $this->deleteOptionOnProduct($optionId, $product->getId(), self::ADDED_BY_TEMPLATE);
        }
    }

    protected function setCategoryTree(Category $category, int $optionId, array &$products): void
    {
        $childrenCategories = CategoryQuery::create()->filterByParent($category->getId())->find();

        CategoryAvailableOptionQuery::create()
            ->filterByCategoryId($category->getId())
            ->filterByOptionId($optionId)
            ->findOneOrCreate()
            ->save();

        foreach ($childrenCategories as $childrenCategory) {
            if ($childrenCategory->getParent()) {
                $this->setCategoryTree($childrenCategory, $optionId, $products);
            }
        }

        $products = array_merge($category->getProducts()->getData(), $products);
    }

}