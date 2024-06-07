<?php

namespace Option\Controller\Back;

use Exception;
use Option\Option;
use Option\Service\OptionService as OptionService;
use Option\Service\OptionProvider;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Event\Image\ImageEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
use Thelia\Form\Exception\FormValidationException;
use Thelia\Form\ProductCreationForm;
use Thelia\Form\ProductModificationForm;
use Thelia\Model\Country;
use Thelia\Model\Lang;
use Thelia\Model\Map\ProductI18nTableMap;
use Thelia\Model\Map\ProductSaleElementsTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\Product;
use Thelia\Model\ProductImageQuery;
use Thelia\Model\ProductQuery;
use Thelia\Model\ProductSaleElementsQuery;
use Thelia\Model\TaxRuleQuery;
use Thelia\TaxEngine\Calculator;
use Thelia\Tools\MoneyFormat;
use Thelia\Tools\TokenProvider;

/**
 * @Route("/admin/option", name="admin_option")
 */
class OptionController extends BaseAdminController
{



    /**
     * @Route("/create", name="_create_option", methods="POST")
     */
    public function createOption(
        OptionService $optionService,
        Translator    $translator
    ): Response
    {
        $creationForm = $this->createForm(ProductCreationForm::class);

        try {
            $optionService->createOption($this->validateForm($creationForm, 'POST'));

            return $this->generateSuccessRedirect($creationForm);

        } catch (FormValidationException $ex) {
            $errorMessage = $this->createStandardFormValidationErrorMessage($ex);
        } catch (Exception $ex) {
            $errorMessage = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $translator->trans('Option creation', [], Option::DOMAIN_NAME),
            $errorMessage,
            $creationForm,
            $ex
        );

        return $this->generateErrorRedirect($creationForm);
    }

    /**
     * @Route("/update", name="_update_option_view", methods="GET")
     * @throws PropelException
     */
    public function updateOptionView(
        Request        $request,
        OptionProvider $optionProvider,
        ParserContext  $parserContext
    ): Response
    {
        if (!$optionId = $request->get('option_id')) {
            return $this->pageNotFound();
        }

        if (!$product = ProductQuery::create()->findPk($optionId)) {
            return $this->pageNotFound();
        }

        $product->setLocale($this->getCurrentEditionLocale());

        $parserContext->addForm($optionProvider->hydrateDefaultPseForm($product, $this->getCurrentEditionCurrency()));

        return $this->render(
            'edit/option-update',
            [
                "option_id" => $optionId
            ]
        );
    }

    /** @Route("/update", name="_update_option_process", methods="POST") */
    public function updateOptionProcess(
        TranslatorInterface $translator,
        OptionService       $optionService,
    ): Response
    {
        $changeForm = $this->createForm(ProductModificationForm::class);

        try {
            $optionService->updateOption($this->validateForm($changeForm, 'POST'));

            return $this->generateSuccessRedirect($changeForm);
        } catch (FormValidationException $ex) {
            $errorMessage = $this->createStandardFormValidationErrorMessage($ex);
        } catch (Exception $ex) {
            $errorMessage = $ex->getMessage();
        }

        $this->setupFormErrorContext(
            $translator->trans('Option modification'),
            $errorMessage,
            $changeForm,
            $ex
        );

        return $this->generateErrorRedirect($changeForm);
    }

    /**
     * @Route("/delete", name="_delete_option", methods="POST")
     * @throws TokenAuthenticationException
     */
    public function deleteOption(
        Request       $request,
        TokenProvider $tokenProvider,
        OptionService $optionService
    ): RedirectResponse
    {
        $tokenProvider->checkToken(
            $request->query->get('_token')
        );

        $optionService->deleteOption((int)$request->get('product_id'));

        return $this->generateRedirect('/admin/module/Option');
    }

    /**
     * @Route("/calculate-raw-price", name="_calculate_raw_price_option", methods="GET")
     * @throws PropelException
     */
    public function calculatePrice(Request $request): JsonResponse
    {
        $price = (float)($request->query->get('price'));
        $tax_rule_id = (int)($request->query->get('tax_rule'));
        $action = $request->query->get('action');

        $taxRule = TaxRuleQuery::create()->findPk($tax_rule_id);

        if (!$price || !$taxRule) {
            return new JsonResponse(
                [
                    'result' => (float)number_format(0, 6, '.', '')
                ]
            );
        }

        $calculator = new Calculator();

        $calculator->loadTaxRuleWithoutProduct(
            $taxRule,
            Country::getShopLocation()
        );

        $return_price = $price;

        if ($action === 'to_tax') {
            $return_price = $calculator->getTaxedPrice($price);
        }

        if ($action === 'from_tax') {
            $return_price = $calculator->getUntaxedPrice($price);
        }

        return new JsonResponse(
            [
                'result' => (float)number_format($return_price, 6, '.', '')
            ]
        );
    }
}