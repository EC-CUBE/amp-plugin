<?php

namespace Plugin\Amp4;

use Eccube\Common\EccubeConfig;
use Eccube\Entity\Layout;
use Eccube\Entity\Master\DeviceType;
use Eccube\Entity\Page;
use Eccube\Repository\LayoutRepository;
use Eccube\Repository\PageRepository;
use Eccube\Request\Context;
use Plugin\Amp4\Controller\UserDataController;
use Plugin\Amp4\Entity\Master\DeviceTypeTrait;
use Plugin\Amp4\Repository\ConfigRepository;
use SunCat\MobileDetectBundle\DeviceDetector\MobileDetector;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;
use Psr\Container\ContainerInterface;

/**
 * Class TemplateLayoutListener
 * @package Plugin\Amp4
 */
class TemplateLayoutListener implements EventSubscriberInterface
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var MobileDetector
     */
    protected $detector;

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var EccubeConfig
     */
    protected $eccubeConfig;

    /**
     * @var LayoutRepository
     */
    protected $layoutRepository;

    /**
     * @var PageRepository
     */
    protected $pageRepository;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var string 
     */
    protected $ampTemplate;

    /**
     * @var string 
     */
    protected $optimizerTemplate;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * TemplateLayoutListener constructor.
     * @param Context $context
     * @param Environment $twig
     * @param MobileDetector $detector
     * @param EccubeConfig $eccubeConfig
     * @param RequestStack $requestStack
     * @param PageRepository $pageRepository
     * @param LayoutRepository $layoutRepository
     * @param ConfigRepository $configRepository
     * @param ContainerInterface $container
     */
    public function __construct(Context $context,
                                Environment $twig,
                                MobileDetector $detector,
                                EccubeConfig $eccubeConfig,
                                RequestStack $requestStack,
                                PageRepository $pageRepository,
                                LayoutRepository $layoutRepository,
                                ConfigRepository $configRepository,
                                ContainerInterface $container)
    {
        $this->context = $context;
        $this->twig = $twig;
        $this->detector = $detector;
        $this->eccubeConfig = $eccubeConfig;
        $this->requestStack = $requestStack;
        $this->pageRepository = $pageRepository;
        $this->layoutRepository = $layoutRepository;
        $this->configRepository = $configRepository;
        $this->container = $container;

        $this->ampTemplate = __DIR__ . "/Resource/template/amp";
    }

    /**
     * @param GetResponseEvent $event
     */
    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }
        // 管理画面の場合は実行しない.
        if ($this->context->isAdmin()) {
            return;
        }

        $isAddAmpHtml = false;
        $isAddCanonical = false;

        $request = $event->getRequest();

        /** @var \Symfony\Component\HttpFoundation\ParameterBag $attributes */
        /** @var Page $Page */
        $attributes = $request->attributes;

        $Config = $this->configRepository->get();

        $route = $attributes->get('_route');
        if ($route == "amp_manifest") {
            return;
        }

        $route = $attributes->get('_route');
        if ($route == "product_add_cart" && $request->get('type') == 'amp') {////ampからカード商品追加出力データ転換ためajaxにまねする
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
            return;
        }

        if ($this->isAmpRequest()) {

            $attributes->set('_route', substr($attributes->get('_route'), 4));

            $route = $attributes->get('_route');
            if ($route == 'user_data') {
                $routeParams = $attributes->get('_route_params', []);
                $route = isset($routeParams['route']) ? $routeParams['route'] : $attributes->get('route', '');
            }

            // URLからPageを取得
            $Page = $this->pageRepository->getPageByRoute($route);

            if ($Config->isCanonical()) { //Canonical amp場合
                throw new NotFoundHttpException();
            } if (!$Page->isAmp()) {// amp設定されていない場合
                throw new NotFoundHttpException();
            }

            $paths = [];

            if (is_dir($this->eccubeConfig->get('eccube_theme_app_dir').'/amp')) {
                $paths[] = $this->eccubeConfig->get('eccube_theme_app_dir').'/amp';
            }

            $paths[] = $this->ampTemplate;

            $loader = new \Twig_Loader_Chain([
                new \Twig_Loader_Filesystem($paths),
                $this->twig->getLoader(),
            ]);

            $this->twig->setLoader($loader);

            $isAddCanonical = true;

            //amp layout設定
            $this->setFrontAmpVariables($event, $Page);

        } else {

            $route = $attributes->get('_route');
            if ($route == 'user_data') {
                $routeParams = $attributes->get('_route_params', []);
                $route = isset($routeParams['route']) ? $routeParams['route'] : $attributes->get('route', '');
            }


            $Page = $this->pageRepository->getPageByRoute($route);

            if ($Page->isAmp()) {

                if ($Config->isCanonical()) {

                    $paths = [];


                    if ($Config->isOptimize() && is_dir($this->eccubeConfig->get('eccube_theme_app_dir').'/amp-optimizer')) {
                        $paths[] = $this->eccubeConfig->get('eccube_theme_app_dir').'/amp-optimizer';
                    }

                    if (is_dir($this->eccubeConfig->get('eccube_theme_app_dir').'/amp')) {
                        $paths[] = $this->eccubeConfig->get('eccube_theme_app_dir').'/amp';
                    }

                    $paths[] = $this->ampTemplate;

                    $loader = new \Twig_Loader_Chain([
                        new \Twig_Loader_Filesystem($paths),
                        $this->twig->getLoader(),
                    ]);

                    $this->twig->setLoader($loader);

                    $isAddCanonical = true;
                    //amp layout設定
                    $this->setFrontAmpVariables($event, $Page);

                    if ($Page->getEditType() != 2) {
                        $request->attributes->set('_controller', UserDataController::class . '::index');
                    }

                } else if (!$Config->isCanonical() && in_array($Page->getUrl(), $Config->getAmpPageUrl())) {
                    $isAddAmpHtml = true;
                }
            }
        }

        if ($isAddCanonical) {
            //普通ampモードで通常ページheadにタグ追加
            $tag = $Page->getMetaTags();

            $parameters = $request->attributes->get('_route_params');

            if ($Page->getEditType() == 2) {
                $url = $this->container->get('router')->generate($Page->getUrl(), $parameters);
            } else {
                $url = $this->container->get('router')->generate('user_data', $parameters);
            }

            if (count($request->query->all())) {
                $url .= '?' . http_build_query($request->query->all());
            }

            $tag .= '<link rel="canonical" href="' . $url . '">';

            $Page->setMetaTags($tag);
        }

        if ($isAddAmpHtml) {
            //普通ampモードで通常ページheadにタグ追加
            $tag = $Page->getMetaTags();

            $parameters = $request->attributes->get('_route_params');

            if ($Page->getEditType() == 2) {
                $url = $this->container->get('router')->generate('amp_' . $Page->getUrl(), $parameters);
            } else {
                $url = $this->container->get('router')->generate('user_data', $parameters);
            }

            if (count($request->query->all())) {
                $url .= '?' . http_build_query($request->query->all());
            }

            $tag .= '<link rel="amphtml" href="' . $url . '">';

            $Page->setMetaTags($tag);
        }

        $tag = $Page->getMetaTags();

        $tag .= "<style amp-custom>"
            . $this->configRepository->get()->getAmpHeaderCss()
            . $Page->getAmpCss()
            . "</style>";

        $Page->setMetaTags($tag);
    }

    /**
     * @param GetResponseEvent $event
     * @param Page $Page
     */
    public function setFrontAmpVariables(GetResponseEvent $event, Page $Page)
    {

        $request = $event->getRequest();

        $type = DeviceTypeTrait::$DEVICE_TYPE_AMP;

        /** @var PageLayout[] $PageLayouts */
        $PageLayouts = $Page->getPageLayouts();

        // Pageに紐づくLayoutからDeviceTypeが一致するLayoutを探す
        $Layout = null;
        foreach ($PageLayouts as $PageLayout) {
            if ($PageLayout->getDeviceTypeId() == $type) {
                $Layout = $PageLayout->getLayout();
                break;
            }
        }


        // Pageに紐づくLayoutにDeviceTypeが一致するLayoutがない場合はPCのレイアウトを探す
        if (!$Layout) {
            log_info('fallback to PC layout');
            foreach ($PageLayouts as $PageLayout) {
                if ($PageLayout->getDeviceTypeId() == DeviceType::DEVICE_TYPE_PC) {
                    $Layout = $PageLayout->getLayout();
                    break;
                }
            }
        }

        // 管理者ログインしている場合にページレイアウトのプレビューが可能
        if ($request->get('preview')) {
            $is_admin = $request->getSession()->has('_security_admin');
            if ($is_admin) {
                $Layout = $this->layoutRepository->get(Layout::DEFAULT_LAYOUT_PREVIEW_PAGE);

                $this->twig->addGlobal('Layout', $Layout);
                $this->twig->addGlobal('Page', $Page);
                $this->twig->addGlobal('title', $Page->getName());

                return;
            }
        }

        if ($Layout) {
            // lazy loadを制御するため, Layoutを取得しなおす.
            $Layout = $this->layoutRepository->get($Layout->getId());
        } else {
            // Layoutのデータがない場合は空のLayoutをセット
            $Layout = new Layout();
        }

        $this->twig->addGlobal('Layout', $Layout);
        $this->twig->addGlobal('Page', $Page);
        $this->twig->addGlobal('title', $Page->getName());
    }

    /**
     * AMPへのアクセスかどうか.
     *
     * @return bool
     */
    public function isAmpRequest()
    {
        $request = $this->requestStack->getMasterRequest();

        if (null === $request) {
            return false;
        }

        $pathInfo = \rawurldecode($request->getPathInfo());
        $ampPath = 'amp';
        $ampPath = '/'.\trim($ampPath, '/').'/';

        if ($pathInfo == '/amp') {
            return true;
        }

        return \strpos($pathInfo, $ampPath) === 0;
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $request = $event->getRequest();

        /** @var \Symfony\Component\HttpFoundation\ParameterBag $attributes */
        $attributes = $request->attributes;
        $attributes->get('');

        $route = $attributes->get('_route');

        if ($request->get('type') == 'amp') {
            if ($route == "product_add_cart") {//ampからカード商品追加出力データ転換
                $jsonResponse = $event->getResponse();
                if ($jsonResponse instanceof JsonResponse) {
                    $data = $jsonResponse->getContent();
                    $data = json_decode($data, 1);

                    if (array_key_exists('messages', $data)) {
                        $data['messages'] = join($data['messages'], "\n");
                    }

                    if ($data['done'] === true) {
                        $data['messages'] = trans('カートに追加しました。');
                    } else if (!$data['messages']) {
                        $data['messages'] = trans('カートに追加できませんでした。');
                    }

                    $jsonResponse->setData($data);
                    $jsonResponse->headers->set('AMP-Access-Control-Allow-Source-Origin', $this->toSourceOrigin($request));
                    $jsonResponse->setStatusCode(200);
                }

            } else if ($route == "product_add_favorite") {
                $redirectResponse = $event->getResponse();
                if ($redirectResponse instanceof RedirectResponse) {
                    $jsonResponse = new JsonResponse();
                    $jsonResponse->setData([]);
                    $jsonResponse->setStatusCode(200);
                    $jsonResponse->headers->set('AMP-Access-Control-Allow-Source-Origin', $this->toSourceOrigin($request));
                    $jsonResponse->headers->set('AMP-Redirect-To', $request->getSchemeAndHttpHost() . $redirectResponse->getTargetUrl());
                    $jsonResponse->headers->set('Access-Control-Expose-Headers', 'AMP-Redirect-To, AMP-Access-Control-Allow-Source-Origin,Access-Control-Allow-Origin');

                    $event->setResponse($jsonResponse);
                }

            }
        }

    }

    protected function toSourceOrigin(Request $request)
    {

        $url = '';

        if (array_key_exists('HTTP_REFERER', $_SERVER)) {
            $url = $_SERVER['HTTP_REFERER'];
        }

        if (!$url) {
            return $request->getSchemeAndHttpHost();
        }

        $data = parse_url($url);
        $scheme = $data['scheme'];
        $host = $data['host'];
        $port = $data['port'];

        if (('http' == $scheme && 80 == $port) || ('https' == $scheme && 443 == $port)) {
            return $scheme . "://" . $host;
        }

        return $scheme . "://" . $host.':'.$port;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 1],
            KernelEvents::RESPONSE => ['onKernelResponse', 1],
        ];
    }
}