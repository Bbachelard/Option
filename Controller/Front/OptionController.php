<?php

namespace Option\Controller\Front;

use Exception;
use OpenApi\Service\OpenApiService;
use OpenApi\Controller\Front\BaseFrontOpenApiController;
use OpenApi\Model\Api\ModelFactory;
use Option\Model\OptionProduct;
use Option\Service\CartItemCustomizationOptionHandler;
use Option\Service\OptionService;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;
use Thelia\Model\CartItemQuery;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Model\ProductQuery;
use Option\Model\OptionProductQuery;

use Option\Model\ProductAvailableOptionQuery;
use OpenApi\Annotations as OA;

#[Route(path: '/open_api/option', name: 'option')]
class OptionController extends BaseFrontOpenApiController
{

    /**
     * @OA\GET(
     *     path="/option/get/{pseId}",
     *     tags={"Option"},
     *     summary="List available options by pse id",
     *     @OA\Parameter(
     *           name="pseId",
     *           in="path",
     *           required=true,
     *           @OA\Schema(
     *               type="integer"
     *           )
     *      ),
     *     @OA\Response(
     *          response="200",
     *          description="Success",
     *          @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                 property="option",
     *                 ref="#/components/schemas/Option"
     *              )
     *           )
     *     ),
     *     @OA\Response(
     *          response="400",
     *          description="Bad request",
     *          @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    #[Route(path: '/get/{pseId}', name: '_get_option_available', methods: 'GET')]
    public function getOptionsAvailable(
        OptionService $optionService,
        Request       $request,
        ModelFactory  $modelFactory,
        int           $pseId
    ): JsonResponse
    {
        $product = ProductQuery::create()
            ->useProductSaleElementsQuery()
            ->filterById($pseId)
            ->endUse()
            ->findOne();


        if (!$product) {
            return new JsonResponse([]);
        }

        $locale = $request->getSession()->getLang()->getLocale();

        $optionsData = [];

        $options = $optionService->getProductAvailableOptions($product);


        /** @var OptionProduct $option */
        foreach ($options as $option) {
            $productName = $this->getProductNameForOption($option);
            $optionsData[] = $modelFactory->buildModel(
                'Option',
                [
                    'title' => $option->getProduct()->setLocale($locale)->getTitle(),
                    'id' => $option->getId(),
                    'code' => $option->getProduct()?->getRef(),
                    'productName' => $productName,
                    'price' => round($optionService->getOptionTaxedPrice($option->getProduct()), 2),
                    'untaxedPrice' => round($optionService->getOptionUnTaxedPrice($option->getProduct()), 2)
                ]
            );
        }


        return OpenApiService::jsonResponse($optionsData);


    }
    private function getProductNameForOption(OptionProduct $option): ?string
    {
        $product = $option->getProduct();
        return $product ? $product->setLocale($this->getLocale())->getTitle() : null;
    }

    /**
     * @OA\Post(
     *     path="/option/add/{cartItemId}",
     *     tags={"Option"},
     *     summary="Add options to a cart item. If an option has customizations,
     *                  transmit user data with the optionCodes parameter array.",
     *
     *     @OA\Parameter(
     *           name="cartItemId",
     *           in="path",
     *           required=true,
     *           @OA\Schema(
     *               type="integer"
     *           )
     *      ),
     *     @OA\RequestBody(
     *          @OA\MediaType(
     *              mediaType="application/json",
     *              @OA\Schema(
     *                  @OA\Property(
     *                         property="id",
     *                         type="integer"
     *                  ),
     *                  @OA\Property(
     *                      property="option",
     *                      type="array",
     *                      @OA\Items(
     *                          type="object",
     *                          @OA\Property(
     *                              property="id",
     *                              type="string"
     *                          )
     *                      )
     *                  ),
     *                  example={
     *                    "options": {
     *                          "OPTION_REF": {
     *                              "id": 1,
     *                              "FORM_FIELD": "My love <3"
     *                          }
     *                     }
     *                  }
     *              )
     *          )
     *      ),
     *     @OA\Response(
     *           response="200",
     *           description="Success",
     *           @OA\JsonContent(
     *              type="object",
     *              @OA\Property(
     *                 property="cart",
     *                 ref="#/components/schemas/Cart"
     *              )
     *           )
     *      ),
     *     @OA\Response(
     *          response="400",
     *          description="Bad request",
     *          @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    #[Route(path: '/add/{cartItemId}', name: '_add_cart_item_option', methods: ['POST'])]
    public function addCartItemOption(
        CartItemCustomizationOptionHandler $optionFormHandler,
        OpenApiService                     $openApiService,
        int                                $cartItemId
    ): JsonResponse
    {
        if (null === $cartItem = CartItemQuery::create()->findPk($cartItemId)) {
            throw new Exception(Translator::getInstance()?->trans("Error, missing cart item parameter"));
        }

        $optionFormHandler->updateCustomizationOptionOnCartItem($cartItem);

        return OpenApiService::jsonResponse([
            'cart' => $openApiService->getCurrentOpenApiCart()
        ]);
    }


    /**
     * @OA\GET(
     *     path="/option/product",
     *     tags={"Option"},
     *     summary="List available products with options",
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer"
     *                 ),
     *                 @OA\Property(
     *                     property="ref",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="option_ids",
     *                     type="array",
     *                     @OA\Items(type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */

    #[Route(path: '/product', name: '_get_product', methods: 'GET')]
    public function getProduct(): JsonResponse
    {
        $products = ProductQuery::create()
            ->useProductAvailableOptionQuery()
            ->endUse()
            ->find();
        $optionProducts = OptionProductQuery::create()
            ->select(['id', 'product_id'])
            ->find();
        $optionProductMap = [];
        foreach ($optionProducts as $optionProduct) {
            $optionProductId = $optionProduct['id'] ?? null;
            $optionProductProductId = $optionProduct['product_id'] ?? null;
            if ($optionProductId !== null) {
                $optionProductMap[$optionProductId] = $optionProductProductId;
            }
        }
        $formattedData = [];
        $processedProducts = [];
        foreach ($products as $product) {
            $productId = $product->getId();
            if (!isset($processedProducts[$productId])) {
                $optionIds = [];
                $productAvailableOptions = $product->getProductAvailableOptions();
                foreach ($productAvailableOptions as $productAvailableOption) {
                    $optionId = $productAvailableOption->getOptionId();
                    $optionIds[] = $optionProductMap[$optionId];
                }
                $formattedData[] = [
                    'id' => $productId,
                    'title' => $product->getTitle(),
                    'ref'=> $product->getRef(),
                    'option_ids' => $optionIds
                ];
                $processedProducts[$productId] = true;
            }
        }
        return OpenApiService::jsonResponse($formattedData);
    }



}
