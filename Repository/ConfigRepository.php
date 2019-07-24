<?php

/*
 * This file is part of EC-CUBE
 *
 * copyright (c) EC-CUBE CO.,LTD. all rights reserved.
 *
 * http://www.lockon.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Amp4\Repository;

use Eccube\Repository\AbstractRepository;
use Eccube\Repository\BaseInfoRepository;
use Plugin\Amp4\Entity\Config;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class ConfigRepository
 */
class ConfigRepository extends AbstractRepository
{

    protected $config;

    /**
     * @var \Eccube\Entity\BaseInfo
     */
    protected $baseInfo;

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * ConfigRepository constructor.
     * @param RegistryInterface $registry
     * @param BaseInfoRepository $baseInfoRepository
     * @param ContainerInterface $container
     * @throws \Exception
     */
    public function __construct(RegistryInterface $registry, BaseInfoRepository $baseInfoRepository, ContainerInterface $container)
    {
        $this->baseInfo = $baseInfoRepository->get();
        $this->container = $container;
        parent::__construct($registry, Config::class);
    }

    /**
     * @param int $id
     *
     * @return Config
     */
    public function get($id = 1)
    {

        if ($this->config) {
            return $this->config;
        }

        $config = $this->find($id);

        if ($config) {
            $this->config = $config;
        } else {
            $config = new Config();

            $start_url = $this->container->get('router')->generate('homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);
            //$start_url = $this->container->get('router')->generate('amp_homepage', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $start_url .= "amp/";


            $assets = $this->container->get('assets.packages');

            $reData = [
                'name' => $this->baseInfo->getShopName(),
                'short_name' => $this->baseInfo->getShopName(),
                'theme_color' => '#ffffff',
                'background_color' => '#ffffff',
                'display' => 'standalone',
                'start_url' => $start_url,
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
            $config->setAmpManifest(str_replace('\/','/',json_encode($reData)));
        }

        return $config;
    }
}
