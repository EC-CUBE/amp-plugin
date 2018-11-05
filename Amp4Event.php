<?php

namespace Plugin\Amp4;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Eccube\Common\EccubeConfig;
use Eccube\Entity\Layout;
use Eccube\Entity\Page;
use Eccube\Entity\PageLayout;
use Eccube\Event\EccubeEvents;
use Eccube\Event\EventArgs;
use Eccube\Event\TemplateEvent;
use Eccube\Form\Validator\TwigLint;
use Eccube\Repository\LayoutRepository;
use Eccube\Repository\Master\DeviceTypeRepository;
use Eccube\Repository\Master\ProductListMaxRepository;
use Eccube\Repository\PageLayoutRepository;
use Eccube\Repository\PageRepository;
use Eccube\Util\StringUtil;
use Plugin\Amp4\Repository\ConfigRepository;
use Plugin\Amp4\Service\HttpSend;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormError;
use Twig\Environment;
use Plugin\Amp4\Entity\Master\DeviceTypeTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Amp4Event
 * @package Plugin\Amp4
 */
class Amp4Event implements EventSubscriberInterface
{

    /**
     * @var DeviceTypeRepository
     */
    protected $deviceTypeRepository;

    /**
     * @var PageLayoutRepository
     */
    protected $pageLayoutRepository;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var LayoutRepository
     */
    protected $layoutRepository;

    /**
     * @var PageRepository
     */
    protected $pageRepository;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * @var HttpSend
     */
    protected $httpSend;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var ProductListMaxRepository
     */
    protected $productListMaxRepository;

    /**
     * Amp4Event constructor.
     * @param DeviceTypeRepository $deviceTypeRepository
     * @param PageLayoutRepository $pageLayoutRepository
     * @param LayoutRepository $layoutRepository
     * @param ConfigRepository $configRepository
     * @param PageRepository $pageRepository
     * @param EntityManager $entityManager
     * @param EccubeConfig $eccubeConfig
     * @param Environment $twig
     * @param Filesystem $filesystem
     * @param HttpSend $httpSend
     * @param ContainerInterface $container
     * @param ProductListMaxRepository $productListMaxRepository
     */
    public function __construct(DeviceTypeRepository $deviceTypeRepository,
                                PageLayoutRepository $pageLayoutRepository,
                                LayoutRepository $layoutRepository,
                                ConfigRepository $configRepository,
                                PageRepository $pageRepository,
                                EntityManager $entityManager,
                                EccubeConfig $eccubeConfig,
                                Environment $twig,
                                Filesystem $filesystem,
                                HttpSend $httpSend,
                                ContainerInterface $container,
                                ProductListMaxRepository $productListMaxRepository)
    {
        $this->deviceTypeRepository = $deviceTypeRepository;
        $this->pageLayoutRepository = $pageLayoutRepository;
        $this->layoutRepository = $layoutRepository;
        $this->pageRepository = $pageRepository;
        $this->configRepository = $configRepository;
        $this->entityManager = $entityManager;
        $this->eccubeConfig = $eccubeConfig;
        $this->twig = $twig;
        $this->filesystem = $filesystem;
        $this->httpSend = $httpSend;
        $this->container = $container;
        $this->productListMaxRepository = $productListMaxRepository;
    }

    /**
     * @return string
     */
    public function getSizeofJs()
    {
        return "var sizeof = function(str, charset){
            var total = 0,
                charCode,
                i,
                len;
            charset = charset ? charset.toLowerCase() : '';
            if(charset === 'utf-16' || charset === 'utf16'){
                for(i = 0, len = str.length; i < len; i++){
                    charCode = str.charCodeAt(i);
                    if(charCode <= 0xffff){
                        total += 2;
                    }else{
                        total += 4;
                    }
                }
            }else{
                for(i = 0, len = str.length; i < len; i++){
                    charCode = str.charCodeAt(i);
                    if(charCode <= 0x007f) {
                        total += 1;
                    }else if(charCode <= 0x07ff){
                        total += 2;
                    }else if(charCode <= 0xffff){
                        total += 3;
                    }else{
                        total += 4;
                    }
                }
            }
            return Math.ceil(total / 1024 * 100) / 100;
        };";
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            '@admin/Content/page_edit.twig' => 'onAdminContentPageEdit',
            '@admin/Content/layout_list.twig' => 'onAdminContentLayoutList',
            '@admin/Content/block_edit.twig' => 'onAdminContentBlockEdit',
            '@admin/Content/page.twig' => 'onAdminContentPage',
            '@Amp4/admin/config.twig' => 'onAmp4AdminConfig',
            'Block/search_product.twig' => 'onSearchProduct',
            'Product/list.twig' => 'onProductList',
            EccubeEvents::ADMIN_CONTENT_BLOCK_EDIT_INITIALIZE => 'adminContentBlockEditInitialize',
            EccubeEvents::ADMIN_CONTENT_BLOCK_EDIT_COMPLETE => 'adminContentBlockEditComplete',
            EccubeEvents::ADMIN_CONTENT_PAGE_EDIT_INITIALIZE => 'adminContentPageEditInitialize',
            EccubeEvents::ADMIN_CONTENT_PAGE_EDIT_COMPLETE => 'adminContentPageEditComplete',
            EccubeEvents::ADMIN_CONTENT_PAGE_DELETE_COMPLETE => 'adminContentPageDeleteComplete',
            EccubeEvents::ADMIN_CONTENT_BLOCK_DELETE_COMPLETE => 'adminContentBlockDeleteComplete',
        ];
    }

    /**
     * @param TemplateEvent $event
     */
    public function onProductList(TemplateEvent $event)
    {

        /** @var $requestStack \Symfony\Component\HttpFoundation\RequestStack */
        $requestStack = $this->container->get('request_stack');
        $request = $requestStack->getMasterRequest();

        $strApiJsonParame = "";

        if ($request->getMethod() === 'GET') {
            $all = $request->query->all();
            if (array_key_exists('pageno', $all) && $all['pageno'] == 0) {
                $all['pageno'] = 1;
            }
            if (array_key_exists('disp_number', $all) && !$all['disp_number']) {
                $all['disp_number'] = $this->productListMaxRepository->findOneBy([], ['sort_no' => 'ASC'])->getId();
            }
            $strApiJsonParame = "?" . http_build_query($all);
        }

        $event->setParameter('strApiJsonParame', $strApiJsonParame);
    }

    /**
     * @param TemplateEvent $event
     */
    public function onSearchProduct(TemplateEvent $event)
    {
        /** @var $requestStack \Symfony\Component\HttpFoundation\RequestStack */
        $requestStack = $this->container->get('request_stack');

        $addParame = "";
        if ($requestStack->getMasterRequest()) {
            $data = [];

            if ($requestStack->getMasterRequest()->query->get('category_id')) {
                $data['category_id'] = $requestStack->getMasterRequest()->query->get('category_id');
            }

            if ($requestStack->getMasterRequest()->query->get('name')) {
                $data['name'] = $requestStack->getMasterRequest()->query->get('name');
            }

            if (count($data)) {
                $addParame = "?" . http_build_query($data);
            }
        }

        $event->setParameter('add_amp_api_parame', $addParame);
    }

    /**
     * @param TemplateEvent $event
     */
    public function onAdminContentPage(TemplateEvent $event)
    {
        $source = $event->getSource();

        $oldB = "<i class=\"fa {{ icon }} mr-2\"></i>";
        $reStr = "{% if Layout.device_type.id == " . DeviceTypeTrait::$DEVICE_TYPE_AMP . " %}
        <i class=\"fa mr-2\" style=\"
   width: 16px;
\"><svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 14 14\"><path d=\"M9.4 6.4l-2.9 4.9h-.6l.5-3.2H4.8c-.1 0-.3-.1-.3-.3 0-.1.1-.2.1-.2l2.9-4.9H8l-.5 3.2h1.6c.1 0 .3.1.3.3.1.1 0 .1 0 .2zM7 0C3.1 0 0 3.1 0 7s3.1 7 7 7 7-3.1 7-7-3.1-7-7-7z\" fill=\"#0379c4\"></path></svg></i>
        {% else %}<i class=\"fa fa-fw {{ icon }} fa-lg mr-2\"></i>{% endif %}";
        $limit = 1;
        $source = str_replace($oldB, $reStr, $source, $limit);

        $event->setSource($source);
    }

    /**
     * @param TemplateEvent $event
     */
    public function onAmp4AdminConfig(TemplateEvent $event)
    {
        $source = $event->getSource();

        $oldB = "ace.require('ace/ext/language_tools');";
        $reStr = $this->getSizeofJs() . "\nace.require('ace/ext/language_tools');";
        $limit = 1;
        $source = str_replace($oldB, $reStr, $source, $limit);

        $event->setSource($source);
    }

    /**
     * @param TemplateEvent $event
     */
    public function onAdminContentLayoutList(TemplateEvent $event)
    {
        //削除できないように修正
        //block amp対応

        $ampDeviceType = $this->deviceTypeRepository->find(DeviceTypeTrait::$DEVICE_TYPE_AMP);
        $ampLayouts = $this->layoutRepository->findBy(['DeviceType' => $ampDeviceType]);
        $layoutIds = [];
        foreach ($ampLayouts as $ampLayout) {
            $layoutIds[] = $ampLayout->getId();
        }


        $event->setParameter('layoutIds', array_flip($layoutIds));

        $source = $event->getSource();
        $oldB = "{% if Layout.isDefault() == false %}";
        $reStr = "{% if Layout.isDefault() == false and Layout.id in layoutIds %}";

        $limit = 1;
        $source = str_replace($oldB, $reStr, $source, $limit);

        $oldB = "<i class=\"fa fa-fw {{ icon }} fa-lg mr-2\"></i>";

        $reStr = "{% if Layout.DeviceType.id == " . DeviceTypeTrait::$DEVICE_TYPE_AMP . " %}
        <i class=\"fa mr-2\" style=\"
   width: 16px;
\"><svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 14 14\"><path d=\"M9.4 6.4l-2.9 4.9h-.6l.5-3.2H4.8c-.1 0-.3-.1-.3-.3 0-.1.1-.2.1-.2l2.9-4.9H8l-.5 3.2h1.6c.1 0 .3.1.3.3.1.1 0 .1 0 .2zM7 0C3.1 0 0 3.1 0 7s3.1 7 7 7 7-3.1 7-7-3.1-7-7-7z\" fill=\"#0379c4\"></path></svg></i>
        {% else %}<i class=\"fa fa-fw {{ icon }} fa-lg mr-2\"></i>{% endif %}";

        $source = str_replace($oldB, $reStr, $source, $limit);

        $event->setSource($source);
    }

    /**
     * @param TemplateEvent $event
     */
    public function onAdminContentBlockEdit(TemplateEvent $event)
    {
        $source = $event->getSource();

        $oldB = "{% for f in form if f.vars.eccube_form_options.auto_render %}";
        $reStr = "
         <div class=\"row mb-2\">
            <div class=\"col-2\">
                <div class=\"d-inline-block\" data-tooltip=\"true\" data-placement=\"top\" title=\"{{ 'tooltip.content.block_source_code'|trans }}\">
                    <span>{{ 'amp4.admin.content.block_source_code'|trans }}</span><i class=\"fa fa-question-circle fa-lg ml-1\"></i>
                </div>
            </div>
            <div class=\"col-10\">
                <div id=\"amp_editor\" style=\"height: 480px\" class=\"form-control{{ has_errors(form.amp_block_html) ? ' is-invalid' }}\"></div>
                <div class=\"d-none\">
                    {{ form_widget(form.amp_block_html) }}
                </div>
                {{ form_errors(form.amp_block_html) }}
            </div>
        </div>
        {% for f in form if f.vars.eccube_form_options.auto_render %}";

        $limit = 1;
        $source = str_replace($oldB, $reStr, $source, $limit);

        $oldB = "$('#content_block_form').on('submit', function(elem) {";
        $reStr = "
        var amp_editor = ace.edit('amp_editor');
        amp_editor.session.setMode('ace/mode/twig');
        amp_editor.setTheme('ace/theme/tomorrow');
        amp_editor.setValue('{{ form.amp_block_html.vars.value|escape('js') }}');
        amp_editor.setOptions({
            enableBasicAutocompletion: true,
            enableSnippets: true,
            enableLiveAutocompletion: true,
            showInvisibles: true
        });
        
        $('#content_block_form').on('submit', function(elem) {
            $('#block_amp_block_html').val(amp_editor.getValue());
        ";

        $source = str_replace($oldB, $reStr, $source, $limit);

        $event->setSource($source);
    }

    /**
     * @param TemplateEvent $event
     */
    public function onAdminContentPageEdit(TemplateEvent $event)
    {

        $isCheckAmp = true;

        if ($event->getParameter('is_user_data_page')) {
            $event->setParameter('amp_template_path',
                substr($this->eccubeConfig->get('eccube_theme_user_data_dir').'/amp',
                    strlen($this->eccubeConfig->get('kernel.project_dir'))));

        } else {

            $id = $event->getParameter('page_id');
            /** @var $Page \Eccube\Entity\Page */
            $Page = $this->pageRepository->find($id);
            $Config = $this->configRepository->get();

            if (!in_array($Page->getUrl(), $Config->getAmpPageUrl()) && !$Config->isCanonical()) {
                $isCheckAmp = false;
            }

            $event->setParameter('amp_template_path',
                substr($this->eccubeConfig->get('eccube_theme_app_dir').'/amp',
                    strlen($this->eccubeConfig->get('kernel.project_dir'))));
        }

        $event->setParameter('amp4_config', $this->configRepository->get());

        //--------------

        $source = $event->getSource();

        $oldB = "{{ 'admin.content.page_mobile'|trans }}";

        if ($isCheckAmp) {
            $reStr = "{{ 'amp4.admin.content.page_amp'|trans }}</span></div>
                    <div class=\"col-10\">
                        {{ form_widget(form.AmpLayout) }}
                        {{ form_errors(form.AmpLayout) }}
                    </div>
                </div><div class=\"row mb-2\">
                    <div class=\"col-2\"><span>{{ 'admin.content.page_mobile'|trans }}";
        } else {
            $reStr = "<div style='display: none;'>{{ form_widget(form.AmpLayout) }}</div>{{ 'admin.content.page_mobile'|trans }}";
        }

        $limit = 1;
        $source = str_replace($oldB, $reStr, $source, $limit);

        $oldB = "{% for f in form if f.vars.eccube_form_options.auto_render %}";

        if ($isCheckAmp) {
            $reStr = "
                <div class=\"row mb-2\">
                    <div class=\"col-2\">
                        <div class=\"d-inline-block\" data-tooltip=\"true\" data-placement=\"top\">
                            <span>{{ 'amp4.admin.page_edit.is_amp.title'|trans }}</span><i class=\"fa fa-question-circle fa-lg ml-1\"></i>
                        </div>
                    </div>
                    <div class=\"col-10\">
                        {{ form_widget(form.is_amp) }}
                        {{ form_errors(form.is_amp) }}
                    </div>
                </div>
        
                <!-- ファイル名 -->
                <div class=\"row mb-2\">
                    <div class=\"col-2\">
                        <div class=\"d-inline-block\" data-tooltip=\"true\" data-placement=\"top\" title=\"{{ 'tooltip.content.page_file_name'|trans }}\">
                            <span>{{ 'admin.content.page_file_name'|trans }}</span><i class=\"fa fa-question-circle fa-lg ml-1\"></i>
                        </div>
                    </div>
                    <div class=\"col-10\">
                        <div class=\"form-row\">
                            {% if is_user_data_page %}
                                <div class=\"col-3 pr-0 align-middle\"><span class=\"align-middle\">{{ template_path }}/amp/</span></div>
                            {% else %}
                                <div class=\"col pr-0 align-middle\">
                                    <span class=\"align-middle\">{{ amp_template_path }}/{{ form.file_name.vars.value }}.twig</span>
                                </div>
                            {% endif %}
                            {{ form_errors(form.file_name) }}
                        </div>
                    </div>
                </div>
                
                <!-- コード -->
                <div class=\"row mb-2\">
                    <div class=\"col-2\">
                        <div class=\"d-inline-block\" data-tooltip=\"true\" data-placement=\"top\" title=\"{{ 'tooltip.content.page_source_code'|trans }}\">
                            <span>{{ 'amp4.admin.content.page_source_code'|trans }}</span><i class=\"fa fa-question-circle fa-lg ml-1\"></i>
                        </div>
                    </div>
                    <div class=\"col-10\">
                        <div id=\"amp_editor\" style=\"height: 480px\" class=\"form-control{{ has_errors(form.amp_tpl_data) ? ' is-invalid' }}\"></div>
                        <div style=\"display: none\">{{ form_widget(form.amp_tpl_data) }}</div>
                        {{ form_errors(form.amp_tpl_data) }}
                    </div>
                </div>
                
                <!-- コード -->
                <div class=\"row mb-2\">
                    <div class=\"col-2\">
                        <div class=\"d-inline-block\" data-tooltip=\"true\" data-placement=\"top\">
                            <span>{{ 'amp4.admin.content.page_css_source_code'|trans }}</span></i>
                        </div>
                    </div>
                    <div class=\"col-10\">
                        <div id=\"editor_css_size\">サイズ:<span></span>KB</div>
                        <div id=\"amp_css_editor\" style=\"height: 480px\" class=\"form-control{{ has_errors(form.amp_css) ? ' is-invalid' }}\"></div>
                        <div style=\"display: none\">{{ form_widget(form.amp_css) }}</div>
                        {{ form_errors(form.amp_css) }}
                    </div>
                </div>
                <div style='display: none;'><input id='hfCss' value='{{ amp4_config.amp_header_css }}' /></div>
                {% for f in form if f.vars.eccube_form_options.auto_render %}";
        } else {
            $reStr = "<div style='display: none;'>{{ form_widget(form.is_amp) }}{{ form_widget(form.amp_tpl_data) }}{{ form_widget(form.amp_css) }}</div>
                {% for f in form if f.vars.eccube_form_options.auto_render %}";
        }

        $source = str_replace($oldB, $reStr, $source, $limit);

        if ($isCheckAmp) {

            $oldB = "ace.require('ace/ext/language_tools');";
            $reStr = $this->getSizeofJs() . "
            ace.require('ace/ext/language_tools');
            ";
            $source = str_replace($oldB, $reStr, $source, $limit);

            $oldB = "$('#content_page_form').on('submit', function(elem) {";
            $reStr = "
                var amp_editor = ace.edit('amp_editor');
                amp_editor.session.setMode('ace/mode/twig');
                amp_editor.setTheme('ace/theme/tomorrow');
                amp_editor.setValue('{{ form.amp_tpl_data.vars.value|escape('js') }}');
                amp_editor.setOptions({
                    enableBasicAutocompletion: true,
                    enableSnippets: true,
                    enableLiveAutocompletion: true,
                    showInvisibles: true
                });
                
                var amp_css_editor = ace.edit('amp_css_editor');
                amp_css_editor.session.setMode('ace/mode/css');
                amp_css_editor.setTheme('ace/theme/tomorrow');
                amp_css_editor.setValue('{{ form.amp_css.vars.value|escape('js') }}');
                amp_css_editor.setOptions({
                    enableBasicAutocompletion: true,
                    enableSnippets: true,
                    enableLiveAutocompletion: true,
                    showInvisibles: true
                });
                
                function setCssSize() {
                    var allSize = sizeof($('#hfCss').val() + amp_css_editor.getValue());
            
                    if (allSize > 50) {
                        $('#editor_css_size span').css('color', '#c04949');
                    } else {
                        $('#editor_css_size span').css('color', '');
                    }
            
                    $('#editor_css_size span').text(allSize);
                }
            
                setCssSize();
                
                $(document).keydown(function(){
                    setCssSize();
                }).keyup(function() {
                    setCssSize();
                });
                
                $('#content_page_form').on('submit', function(elem) {
                    $('#main_edit_amp_tpl_data').val(amp_editor.getValue());
                    $('#main_edit_amp_css').val(amp_css_editor.getValue());
                ";

            $source = str_replace($oldB, $reStr, $source, $limit);
        }

        $event->setSource($source);
    }

    /**
     * @param EventArgs $eventArgs
     */
    public function adminContentBlockEditInitialize(EventArgs $eventArgs)
    {
        /** @var $builder \Symfony\Component\Form\FormBuilder */
        $builder = $eventArgs->getArgument('builder');

        $builder->remove('block_html');

        $builder
            ->add('block_html', TextareaType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new TwigLint(),
                ]
            ])
            ->add('amp_block_html', TextareaType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new TwigLint(),
                ]
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, function ($event) {
                $form = $event->getForm();

                if ($this->configRepository->get()->isCanonical()) {
                    if (!$form['amp_block_html']->getData()) {
                        $message = trans('amp4.admin.page_edit.not_blank');
                        $form['amp_block_html']->addError(new FormError($message));
                    }
                } else {
                    if (!$form['block_html']->getData()) {
                        $message = trans('amp4.admin.page_edit.not_blank');
                        $form['block_html']->addError(new FormError($message));
                    }
                }
            })
            ->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) {
                $form = $event->getForm();
                $Block = $event->getData();
                if ($Block->getId()) {
                    $paths = [
                        __DIR__ . "/Resource/template/amp",
                    ];

                    if (is_dir($this->eccubeConfig->get('eccube_theme_app_dir').'/amp')) {
                        $paths = array_merge([$this->eccubeConfig->get('eccube_theme_app_dir').'/amp'], $paths);
                    }

                    try {
                        $loader = new \Twig_Loader_Chain([
                            new \Twig_Loader_Filesystem($paths),
                        ]);

                        $source = $loader->getSourceContext('Block/'.$Block->getFileName().'.twig')
                            ->getCode();
                    } catch (\Twig_Error_Loader $e) {
                        $source = "";
                    }

                    $form->get('amp_block_html')->setData($source);
                }
            })
        ;
    }

    /**
     * @param EventArgs $eventArgs
     * @throws Service\HttpSendException
     */
    public function adminContentBlockEditComplete(EventArgs $eventArgs)
    {
        /** @var $Block \Eccube\Entity\Block */
        $Block = $eventArgs->getArgument('Block');
        $form = $eventArgs->getArgument('form');

        $dir = sprintf('%s/app/template/%s/Block',
            $this->eccubeConfig->get('kernel.project_dir'),
            'amp');
        $opDir = sprintf('%s/app/template/%s/Block',
            $this->eccubeConfig->get('kernel.project_dir'),
            'amp-optimizer');

        $file = $dir . '/' . $Block->getFileName() . '.twig';
        $opFile = $opDir . '/' . $Block->getFileName() . '.twig';

        $source = $form->get('amp_block_html')->getData();

        if ($this->configRepository->get()->isOptimize()) {

            list($html, $keyData) = $this->httpSend->toEncodeTwig($source);

            $opSource = $this->httpSend->sendData([$html]);
            $opSource = StringUtil::convertLineFeed($this->httpSend->toDecodeTiwg($opSource[0], $keyData));

            $err = $this->httpSend->checkTiwg($opSource);
            if ($err) {
                echo $err;
                exit;
            }

            $this->filesystem->dumpFile($opFile, $opSource);
        }

        $source = StringUtil::convertLineFeed($source);
        $this->filesystem->dumpFile($file, $source);
    }

    /**
     * @param EventArgs $eventArgs
     */
    public function adminContentPageEditInitialize(EventArgs $eventArgs)
    {
        $request = $eventArgs->getRequest();
        /** @var $builder \Symfony\Component\Form\FormBuilder */
        $builder = $eventArgs->getArgument('builder');
        /** @var $Page \Eccube\Entity\Page */

        $builder->remove('tpl_data');

        $builder
            ->add('AmpLayout', EntityType::class, [
                'mapped' => false,
                'placeholder' => '---',
                'required' => false,
                'class' => Layout::class,
                'query_builder' => function (EntityRepository $er) {
                    $DeviceType = $this->deviceTypeRepository->find(DeviceTypeTrait::$DEVICE_TYPE_AMP);

                    return $er->createQueryBuilder('l')
                        ->where('l.id != :DefaultLayoutPreviewPage')
                        ->andWhere('l.DeviceType = :DeviceType')
                        ->setParameter('DeviceType', $DeviceType)
                        ->setParameter('DefaultLayoutPreviewPage', Layout::DEFAULT_LAYOUT_PREVIEW_PAGE)
                        ->orderBy('l.id', 'DESC');
                },
            ])
            ->add('tpl_data', TextareaType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new TwigLint(),
                ],
            ])
            ->add('amp_tpl_data', TextareaType::class, [
                'label' => false,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new TwigLint(),
                ],
            ])
            ->add('is_amp', CheckboxType::class, [
                'label' => trans('amp4.admin.page_edit.is_amp.name'),
                'required' => false,
            ])
            ->add('amp_css', TextareaType::class, [
                'label' => false,
                'required' => false,
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, function ($event) {
                $form = $event->getForm();

                $isAmp = $form['is_amp']->getData();
                if ($isAmp) {
                    if (!$form['amp_tpl_data']->getData()) {
                        $message = trans('amp4.admin.page_edit.not_blank');
                        $form['amp_tpl_data']->addError(new FormError($message));
                    }
                }

                if (!$this->configRepository->get()->isCanonical()) {
                    if (!$form['amp_tpl_data']->getData()) {
                        $message = trans('amp4.admin.page_edit.not_blank');
                        $form['amp_tpl_data']->addError(new FormError($message));
                    }
                }
            })
            ->addEventListener(FormEvents::POST_SET_DATA, function (FormEvent $event) use($request) {
                $Page = $event->getData();
                $form = $event->getForm();

                $fileName = null;
                $namespace = '@user_data/amp/';
                if ($Page->getId()) {
                    // 編集不可ページはURL、ページ名、ファイル名を保持
                    if ($Page->getEditType() == Page::EDIT_TYPE_DEFAULT) {
                        $namespace = '';

                        $paths = [
                            __DIR__ . "/Resource/template/amp",
                        ];

                        if (is_dir($this->eccubeConfig->get('eccube_theme_app_dir').'/amp')) {
                            $paths = array_merge([$this->eccubeConfig->get('eccube_theme_app_dir').'/amp'], $paths);
                        }

                        try {
                            $loader = new \Twig_Loader_Chain([
                                new \Twig_Loader_Filesystem($paths),
                            ]);

                            $source = $loader->getSourceContext($namespace.$Page->getFileName().'.twig')
                                ->getCode();
                        } catch (\Twig_Error_Loader $e) {
                            $source = "";
                            //log("[amp4 error]" . $e->getMessage());
                        }

                    } else {
                        try {
                            // テンプレートファイルの取得
                            $source = $this->twig->getLoader()
                                ->getSourceContext($namespace . $Page->getFileName() . '.twig')
                                ->getCode();
                        } catch (\Twig_Error_Loader $e) {
                            $source = "";
                        }
                    }

                    $form['amp_tpl_data']->setData($source);
                } elseif ($request->getMethod() === 'GET' && !$form->isSubmitted()) {
                    $source = $this->twig->getLoader()
                        ->getSourceContext('@admin/empty_page.twig')
                        ->getCode();
                    $form['amp_tpl_data']->setData($source);
                }

                if (is_null($Page->getId())) {
                    return;
                }

                $Layouts = $Page->getLayouts();
                foreach ($Layouts as $Layout) {
                    if ($Layout->getDeviceType()->getId() == DeviceTypeTrait::$DEVICE_TYPE_AMP) {
                        $form['AmpLayout']->setData($Layout);
                    }
                }
            });
    }

    /**
     * @param EventArgs $eventArgs
     * @throws Service\HttpSendException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function adminContentPageEditComplete(EventArgs $eventArgs)
    {
        $form = $eventArgs->getArgument('form');
        /** @var $Page \Eccube\Entity\Page */
        $Page = $eventArgs->getArgument('Page');

        $Layout = $form['AmpLayout']->getData();

        $LastPageLayout = $this->pageLayoutRepository->findOneBy([], ['sort_no' => 'DESC']);
        $sortNo = $LastPageLayout->getSortNo() + 1;

        if ($Layout) {
            $PageLayout = new PageLayout();
            $PageLayout->setLayoutId($Layout->getId());
            $PageLayout->setLayout($Layout);
            $PageLayout->setPageId($Page->getId());
            $PageLayout->setSortNo($sortNo);
            $PageLayout->setPage($Page);

            $this->entityManager->persist($PageLayout);
            $this->entityManager->flush($PageLayout);
        }

        $isUserDataPage = true;

        if ($Page->getEditType() == Page::EDIT_TYPE_DEFAULT) {
            $isUserDataPage = false;
        }

        // ファイル生成・更新
        if ($isUserDataPage) {
            $templatePath = $this->eccubeConfig->get('eccube_theme_user_data_dir') . '/amp';
            $ampTemplatePath = $this->eccubeConfig->get('eccube_theme_user_data_dir') . '/amp-optimizer';
        } else {
            $templatePath = $this->eccubeConfig->get('eccube_theme_app_dir') . '/amp';
            $ampTemplatePath = $this->eccubeConfig->get('eccube_theme_app_dir') . '/amp-optimizer';
        }
        $filePath = $templatePath . '/' . $Page->getFileName() . '.twig';

        $pageData = $form->get('amp_tpl_data')->getData();

        if ($this->configRepository->get()->isOptimize() && $Page->isAmp()) {

            list($html, $keyData) = $this->httpSend->toEncodeTwig($pageData);

            $opData = $this->httpSend->sendData([$html]);
            $opData = StringUtil::convertLineFeed($this->httpSend->toDecodeTiwg($opData[0], $keyData));

            $err = $this->httpSend->checkTiwg($opData);
            if ($err) {
                echo $err;
                exit;
            }

            $this->filesystem->dumpFile($ampTemplatePath .'/'.$Page->getFileName().'.twig', $opData);
        }

        $pageData = StringUtil::convertLineFeed($pageData);
        $this->filesystem->dumpFile($filePath, $pageData);

    }

    /**
     * @param EventArgs $eventArgs
     */
    public function adminContentPageDeleteComplete(EventArgs $eventArgs)
    {
        /** @var $Page \Eccube\Entity\Page */
        $Page = $eventArgs->getArgument('Page');

        if ($Page->getEditType() == Page::EDIT_TYPE_USER) {
            $templatePath = $this->eccubeConfig->get('eccube_theme_app_dir') . '/amp';
            $ampTemplatePath = $this->eccubeConfig->get('eccube_theme_app_dir') . '/amp-optimizer';
            $files = [$templatePath . '/' . $Page->getFileName() . '.twig', $ampTemplatePath . '/' . $Page->getFileName() . '.twig'];

            foreach ($files as $file) {
                if ($this->filesystem->exists($file)) {
                    $this->filesystem->remove($file);
                }
            }
        }
    }

    /**
     * @param EventArgs $eventArgs
     */
    public function adminContentBlockDeleteComplete(EventArgs $eventArgs)
    {
        /** @var $Block \Eccube\Entity\Block */
        $Block = $eventArgs->getArgument('Block');

        $templatePath = $this->eccubeConfig->get('eccube_theme_app_dir') . '/amp/Block/';
        $ampTemplatePath = $this->eccubeConfig->get('eccube_theme_app_dir') . '/amp-optimizer/Block';

        $files = [$templatePath . '/' . $Block->getFileName() . '.twig', $ampTemplatePath . '/' . $Block->getFileName() . '.twig'];

        foreach ($files as $file) {
            if ($this->filesystem->exists($file)) {
                $this->filesystem->remove($file);
            }
        }
    }
}