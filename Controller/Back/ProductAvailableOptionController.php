<?php

namespace Option\Controller\Back;

use Exception;
use Option\Form\ProductAvailableOptionForm;
use Option\Model\OptionProductQuery;
use Option\Service\OptionProductService;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Log\Tlog;
use Thelia\Model\ProductQuery;
use Thelia\Tools\URL;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Core\HttpFoundation\JsonResponse;
use Option\Model\ProductAvailableOptionQuery;
/**
 * @Route("/admin/option/product", name="admin_option_product")
 */
class ProductAvailableOptionController extends BaseAdminController
{

    /** @Route("/show/{productId}", name="_option_product_show", methods="GET") */
    public function showOptionsProduct(int $productId): Response
    {
        $productOptions = ProductAvailableOptionQuery::create()
            ->filterByProductId($productId)
            ->find();

        $options = [];
        foreach ($productOptions as $productOption) {
            $optionId= $productOption->getOptionId();
            $options[] = [
                'option_id' =>$optionId,
                'price' => $productOption->getPrice(),
                'promo'=>$productOption->getPromoPrice(),
                'isPromo'=>$productOption->getIsPromo()
            ];
        }
        return $this->render(
            'product/product-option-tab',
            [
                'product_id' => $productId,
                'options' => $options
            ]
        );
    }





    /**
     * @Route("/set", name="_option_product_set", methods="POST")
     */
    public function setOptionProduct(OptionProductService $optionProductService): Response
    {
        $form = $this->createForm(ProductAvailableOptionForm::class);

        try {
            $viewForm = $this->validateForm($form);
            $data = $viewForm->getData();

            $optionProductService->setOptionOnProduct($data['product_id'], $data['option_id'], $optionProductService::ADDED_BY_PRODUCT);

            return $this->generateSuccessRedirect($form);
        } catch (Exception $ex) {
            $errorMessage = $ex->getMessage();

            Tlog::getInstance()->error("Failed to validate product option form: $errorMessage");
        }

        $this->setupFormErrorContext(
            'Failed to process product option tab form data',
            $errorMessage,
            $form
        );

        return $this->generateErrorRedirect($form);
    }

    /**
     * @Route("/delete", name="_option_product_delete", methods="GET")
     */
    public function deleteOptionProduct(
        Request       $request,
        OptionProductService $optionProductService
    ): Response
    {
        try {
            $optionProductId = $request->get('option_product_id');
            $productId = $request->get('product_id');
            $force = $request->get('force');

            if (!$optionProductId || !$productId || $force === null) {
                return $this->pageNotFound();
            }

            $optionProductService->deleteOptionOnProduct($optionProductId, $productId,
                OptionProductService::ADDED_BY_PRODUCT, $force);

        } catch (Exception $ex) {
            Tlog::getInstance()->addError($ex->getMessage());
        }

        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/products/update', [
            "current_tab" => "product_option_tab",
            "product_id" => $productId ?? null,
        ]));
    }

    /**
     * @Route("/updatePriceAction", name="_option_product_modify", methods="PUT")
     */
    public function updatePriceAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        $productId = $data['productId'];
        $optionId = $data['optionId'];
        $price = $data['price'];
        $promoPrice = $data['promoPrice'];

        try {
            $productOption = ProductAvailableOptionQuery::create()
                ->filterByProductId($productId)
                ->filterByOptionId($optionId)
                ->findOne();

            if ($productOption) {
                $productOption->setPrice($price);
                $productOption->setPromoPrice($promoPrice);
                if($promoPrice){
                    $productOption->setIsPromo(true);
                }else
                    $productOption->setIsPromo(false);
                $productOption->save();

                return new JsonResponse(['success' => true, 'message' => 'Prices updated successfully.']);
            } else {
                return new JsonResponse(['success' => false, 'message' => 'Product option not found.']);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }
}