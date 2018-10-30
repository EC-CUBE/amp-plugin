<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Amp4\Controller;

use Eccube\Repository\BaseInfoRepository;
use Plugin\Amp4\Repository\ConfigRepository;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class TopController
 * @package Plugin\Amp4\Controller
 */
class TopController extends \Eccube\Controller\TopController
{

    /**
     * @var \Eccube\Entity\BaseInfo
     */
    protected $baseInfo;

    /**
     * @var \Plugin\Amp4\Entity\Config
     */
    protected $config;

    public function __construct(BaseInfoRepository $baseInfoRepository, ConfigRepository $configRepository)
    {
        $this->baseInfo = $baseInfoRepository->get();
        $this->config = $configRepository->get();
    }

    /**
     * @Route("/amp/", name="amp_homepage")
     * @Template("index.twig")
     */
    public function index()
    {
        return parent::index();
    }

    /**
     * @Route("/amp-sw", name="amp_sw")
     */
    public function sw(Request $request)
    {
        return new Response('<!DOCTYPE html>
<html>
<head>
<title>installing Service Woker</title>
<script>
	if("serviceWorker" in navigator) {
		navigator.serviceWorker.register("' . $this->get('assets.packages')->getUrl('Amp4/assets/js/sw.js', 'plugin') . '")
			.then(function(reg){
			console.log(\'SW scope: \', reg.scope);
		}) .catch(function(err) {
			console.log(\'SW registration failed: \', err);
		});
	};
</script>
</head>
<body>
</body>
</html>', 200);
    }

    /**
     * @Route("/amp-sw-js", name="amp_sw_js")
     */
    public function swJs(Request $request)
    {
        return new Response('/**
 * Welcome to your Workbox-powered service worker!
 *
 * You\'ll need to register this file in your web app and you should
 * disable HTTP caching for this file too.
 * See https://goo.gl/nhQhGp
 *
 * The rest of the code is auto-generated. Please don\'t update this file
 * directly; instead, make changes to your Workbox build configuration
 * and re-run your build process.
 * See https://goo.gl/2aRDsh
 */

importScripts("https://storage.googleapis.com/workbox-cdn/releases/3.6.2/workbox-sw.js");

workbox.core.setCacheNameDetails({prefix: "ec-cube"});

workbox.skipWaiting();
workbox.clientsClaim();

/**
 * The workboxSW.precacheAndRoute() method efficiently caches and responds to
 * requests for URLs in the manifest.
 * See https://goo.gl/S9QRab
 */
self.__precacheManifest = [
  {
    "url": "html/template/default/assets/css/style.css",
    "revision": "9c813f9a5e44337e241778ac74d4ae2f"
  },
  {
    "url": "html/template/default/assets/css/style.min.css",
    "revision": "5ae7f1dbbc6272007e08ff2c5ed8bd7f"
  },
  {
    "url": "html/template/default/assets/scss/style.css",
    "revision": "0635c24719298a2747dd2802e8fb3418"
  },
  {
    "url": "html/template/default/assets/js/eccube.js",
    "revision": "58ca82ca64f73b1512f24f245cd3c9e2"
  },
  {
    "url": "html/template/default/assets/js/function.js",
    "revision": "2d886315aade052f69ae768a5880123e"
  },
  {
    "url": "html/template/default/assets/icon/angle-down-white.svg",
    "revision": "adb6a07f473fc4fe3a261c9be5ed722e"
  },
  {
    "url": "html/template/default/assets/icon/angle-down.svg",
    "revision": "5eddbf86fb9b0e8cf9e38756c79f44ee"
  },
  {
    "url": "html/template/default/assets/icon/angle-right-white.svg",
    "revision": "b675356b6f8c05c02c7150137569914b"
  },
  {
    "url": "html/template/default/assets/icon/angle-right.svg",
    "revision": "debee2d53108e354f3cece5f27fa468b"
  },
  {
    "url": "html/template/default/assets/icon/cart-dark.svg",
    "revision": "2c159b61f6180520ce971c461eea327c"
  },
  {
    "url": "html/template/default/assets/icon/cart.svg",
    "revision": "6ccfb19a6354934ad617871e901664ca"
  },
  {
    "url": "html/template/default/assets/icon/cross-dark.svg",
    "revision": "bdc38ec332265ab976aa31b91b688fea"
  },
  {
    "url": "html/template/default/assets/icon/cross-white.svg",
    "revision": "e1bcdfd8d8a258d6db44f5c1ec4ff774"
  },
  {
    "url": "html/template/default/assets/icon/cross.svg",
    "revision": "9425754dd340de3b7c05f68ad58e076c"
  },
  {
    "url": "html/template/default/assets/icon/dotted.svg",
    "revision": "2285d39480d2fa1d88b9f3f13ff63f6e"
  },
  {
    "url": "html/template/default/assets/icon/exclamation-pale.svg",
    "revision": "044e0dbe807378f47af60e1028afc4a7"
  },
  {
    "url": "html/template/default/assets/icon/exclamation-white.svg",
    "revision": "61c3190fb73af8205ab466f9761284de"
  },
  {
    "url": "html/template/default/assets/icon/exclamation.svg",
    "revision": "1de22822b5ca31fe47b6b5552bc6ca77"
  },
  {
    "url": "html/template/default/assets/icon/eye.svg",
    "revision": "c5135141181857cdfa277b7589cdc9b4"
  },
  {
    "url": "html/template/default/assets/icon/favorite.svg",
    "revision": "3cac40470529e4530131a21e9adf7685"
  },
  {
    "url": "html/template/default/assets/icon/heart.svg",
    "revision": "090d882bb90f6363a6212226b80b87c9"
  },
  {
    "url": "html/template/default/assets/icon/login.svg",
    "revision": "72ef214c87ea71bd75dbca637f7e6f2f"
  },
  {
    "url": "html/template/default/assets/icon/minus-dark.svg",
    "revision": "cfc7c6e66bcd65fa6b2fb0076cc3de20"
  },
  {
    "url": "html/template/default/assets/icon/minus.svg",
    "revision": "72d7f08fa5f071a67adf6f37f7fab942"
  },
  {
    "url": "html/template/default/assets/icon/plus-dark.svg",
    "revision": "d32bca8f5bc2de2cd26fbf2af954f07d"
  },
  {
    "url": "html/template/default/assets/icon/plus.svg",
    "revision": "59e19f867ac2fec9df0013ce94a622d1"
  },
  {
    "url": "html/template/default/assets/icon/question-white.svg",
    "revision": "d78019f370fc19b95ec13d2cf336bf98"
  },
  {
    "url": "html/template/default/assets/icon/question.svg",
    "revision": "acfa6744977f1e5b475419ca123f3cbb"
  },
  {
    "url": "html/template/default/assets/icon/search-dark.svg",
    "revision": "e78a5cb219fd30f3f186e1806851e598"
  },
  {
    "url": "html/template/default/assets/icon/search.svg",
    "revision": "4ae358a2b2ff1c4de1907229229c20fb"
  },
  {
    "url": "html/template/default/assets/icon/user.svg",
    "revision": "31b343a8a4de3497e6f1853eafebc1d1"
  },
  {
    "url": "html/template/default/assets/img/top/fpo_355x150.png",
    "revision": "c44c8bc2f2a5daacad5ec7ea1ca7ecfc"
  },
  {
    "url": "html/template/default/assets/img/top/img_about.jpg",
    "revision": "7ea0e14abc562cea562d342e007227a8"
  },
  {
    "url": "html/template/default/assets/img/top/img_bnr01.jpg",
    "revision": "c33f138c5a55dda8dd3b119e368b2e07"
  },
  {
    "url": "html/template/default/assets/img/top/img_bnr02.jpg",
    "revision": "956990892341cb7cf6575d5c4b51bde7"
  },
  {
    "url": "html/template/default/assets/img/top/img_hero_pc01.jpg",
    "revision": "423cabdd468b710e3ddd656807d1330d"
  },
  {
    "url": "html/template/default/assets/img/top/img_hero_pc02.jpg",
    "revision": "b5ba431453da3f7374cca3de4ef5d357"
  },
  {
    "url": "html/template/default/assets/img/top/img_hero_pc03.jpg",
    "revision": "281cce35a8b82f9913829b251345ae82"
  },
  {
    "url": "html/template/default/assets/img/top/img_hero_sp01.jpg",
    "revision": "781df194a0794ad4d3bbea43fddbd61a"
  },
  {
    "url": "html/template/default/assets/img/top/img_hero_sp02.jpg",
    "revision": "7db89b09d2f964f60aecd47c8d075b28"
  },
  {
    "url": "html/template/default/assets/img/top/img_hero_sp03.jpg",
    "revision": "a004b314020b093ebfaf0af94aef1edd"
  },
  {
    "url": "html/template/default/assets/img/top/img_item01_01.jpg",
    "revision": "6f42cbbf19d1ca9a3bb4cc171f12f342"
  },
  {
    "url": "html/template/default/assets/img/top/img_item01_02.jpg",
    "revision": "162561724b53fde98217976b5e059354"
  },
  {
    "url": "html/template/default/assets/img/top/img_item01_03.jpg",
    "revision": "4cb7a0f43d89b97b78e6fff1c6c3a154"
  },
  {
    "url": "html/template/default/assets/img/top/img_item02_01.jpg",
    "revision": "82e59c68e79aef651647c0be36a391f9"
  },
  {
    "url": "html/template/default/assets/img/top/img_item02_02.jpg",
    "revision": "9007447cc0be353bda4d96b5b602978a"
  },
  {
    "url": "html/template/default/assets/img/top/img_item02_03.jpg",
    "revision": "25e093bc51836ea42f2cd282ab7489fb"
  }
].concat(self.__precacheManifest || []);
workbox.precaching.suppressWarnings();
workbox.precaching.precacheAndRoute(self.__precacheManifest, {});

workbox.routing.registerRoute(/.jpg|.jpeg|.png|.gif|.svg/, workbox.strategies.cacheFirst({ "cacheName":"cache-api", plugins: [new workbox.expiration.Plugin({"maxEntries":100,"maxAgeSeconds":259200,"purgeOnQuotaError":false}), new workbox.cacheableResponse.Plugin({"statuses":[0,200]})] }), \'GET\');
workbox.routing.registerRoute(/^(?!.*(.css|.js|.jpg|.jpeg|.png|.gif|.svg|.woff2)).*$/, workbox.strategies.networkFirst({ "cacheName":"root", plugins: [new workbox.expiration.Plugin({"maxEntries":1,"maxAgeSeconds":864000,"purgeOnQuotaError":false}), new workbox.cacheableResponse.Plugin({"statuses":[0,200]})] }), \'GET\');
', 200, ['Content-Type' => 'application/javascript']);
    }

    /**
     * @Route("/amp-manifest/manifest.json", name="amp_manifest")
     */
    public function manifestJson(Request $request)
    {

        $homePageName = 'amp_homepage';
        if ($this->config->isCanonical()) {
            $homePageName = 'homepage';
        }

        $assets = $this->get('assets.packages');

        $reData = [
            'name' => $this->baseInfo->getShopName(),
            'short_name' => $this->baseInfo->getShopName(),
            'theme_color' => '#ffffff',
            'background_color' => '#ffffff',
            'display' => 'standalone',
            'start_url' => $this->generateUrl($homePageName, [], UrlGeneratorInterface::ABSOLUTE_URL),
            'icons' => [
                [
                    'src' => $assets->getUrl('Amp4/assets/icon/icons/icon-72x72.png', 'plugin'),
                    'sizes' => '72x72',
                    'type' => 'image/png',
                ],
                [
                    'src' => $assets->getUrl('Amp4/assets/icon/icons/icon-96x96.png', 'plugin'),
                    'sizes' => '96x96',
                    'type' => 'image/png',
                ],
                [
                    'src' => $assets->getUrl('Amp4/assets/icon/icons/icon-128x128.png', 'plugin'),
                    'sizes' => '128x128',
                    'type' => 'image/png',
                ],
                [
                    'src' => $assets->getUrl('Amp4/assets/icon/icons/icon-144x144.png', 'plugin'),
                    'sizes' => '144x144',
                    'type' => 'image/png',
                ],
                [
                    'src' => $assets->getUrl('Amp4/assets/icon/icons/icon-152x152.png', 'plugin'),
                    'sizes' => '152x152',
                    'type' => 'image/png',
                ],
                [
                    'src' => $assets->getUrl('Amp4/assets/icon/icons/icon-384x384.png', 'plugin'),
                    'sizes' => '384x384',
                    'type' => 'image/png',
                ],
                [
                    'src' => $assets->getUrl('Amp4/assets/icon/icons/icon-384x384.png', 'plugin'),
                    'sizes' => '384x384',
                    'type' => 'image/png',
                ],
                [
                    'src' => $assets->getUrl('Amp4/assets/icon/icons/icon-512x512.png', 'plugin'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                ],
            ],
        ];

        return $this->json($reData, 200);
    }
}
