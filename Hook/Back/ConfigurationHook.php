<?php

namespace Option\Hook\Back;

use Exception;
use Option\Service\OptionService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Assets\AssetResolverInterface;
use TheliaSmarty\Template\SmartyParser;

class ConfigurationHook extends BaseHook
{
    public function __construct(
        SmartyParser             $parser,
        AssetResolverInterface   $resolver,
        EventDispatcherInterface $eventDispatcher,
        protected OptionService  $optionService,
    )
    {
        parent::__construct($parser, $resolver, $eventDispatcher);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            "module.configuration" => [
                [
                    "type" => "back",
                    "method" => "onModuleConfiguration"
                ]
            ],
            "module.config-js" => [
                [
                    "type" => "back",
                    "method" => "onModuleConfigurationJs"
                ]
            ],
            "main.in-top-menu-items" => [
                [
                    "type" => "back",
                    "method" => "onMainTopMenuTools"
                ]
            ]
        ];
    }

    /**
     * @throws Exception
     */
    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $optionCategory = $this->optionService->getOptionCategory();
        $this->optionService->listAction();
        $event->add(
            $this->render('option-configuration.html', [
                'category_id' => $optionCategory->getId(),
                'columnsDefinition' => $this->optionService->defineColumnsDefinition(),
            ])
        );
    }


    public function onModuleConfigurationJs(HookRenderEvent $event): void
    {
        $event->add(
            $this->render('option-configuration.js.html')
        );
    }

    public function onMainTopMenuTools(HookRenderEvent $event)
    {
        $event->add($this->render("hook/menu-hook.html", $event->getArguments()));
    }
}