<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * https://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Amp4;

use Doctrine\ORM\EntityManager;
use Eccube\Entity\BlockPosition;
use Eccube\Entity\Layout;
use Eccube\Entity\Master\DeviceType;
use Eccube\Entity\Page;
use Eccube\Entity\PageLayout;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Repository\BlockRepository;
use Eccube\Repository\LayoutRepository;
use Eccube\Repository\Master\DeviceTypeRepository;
use Eccube\Repository\PageLayoutRepository;
use Eccube\Repository\PageRepository;
use Plugin\Amp4\Entity\Master\DeviceTypeTrait;
use Plugin\Amp4\Repository\ConfigRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class PluginManager
 */
class PluginManager extends AbstractPluginManager
{

    /**
     * @param array $meta
     * @param ContainerInterface $container
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function enable(array $meta, ContainerInterface $container)
    {
        /** @var $entityManager EntityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        /** @var \Eccube\Repository\Master\DeviceTypeRepository $deviceTypeRepository */
        $deviceTypeRepository = $container->get(DeviceTypeRepository::class);
        /** @var \Eccube\Repository\BlockRepository $blockRepository */
        $blockRepository = $container->get(BlockRepository::class);

        $ampDevice = $deviceTypeRepository->find(DeviceTypeTrait::$DEVICE_TYPE_AMP);
        if (!$ampDevice) {

            $sortNoTop = $deviceTypeRepository->findOneBy([], ['sort_no' => 'DESC']);
            $sort_no = 0;
            if (!is_null($sortNoTop)) {
                $sort_no = $sortNoTop->getSortNo();
            }

            $addDevice = new DeviceType();
            $addDevice->setId(DeviceTypeTrait::$DEVICE_TYPE_AMP);
            $addDevice->setName('AMP');
            $addDevice->setSortNo($sort_no);
            $entityManager->persist($addDevice);

            $ampTopLayout = new Layout();
            $ampTopLayout->setDeviceType($addDevice);
            $ampTopLayout->setName("AMPトップページ用レイアウト");
            $entityManager->persist($ampTopLayout);


            $ampLayout = new Layout();
            $ampLayout->setDeviceType($addDevice);
            $ampLayout->setName("AMP下層ページ用レイアウト");
            $entityManager->persist($ampLayout);

            $entityManager->flush();

            $installData = [
                ["3","7","1","1","blockposition"],
                ["3","10","1","2","blockposition"],
                ["3","3","1","3","blockposition"],
                ["7","5","1","1","blockposition"],
                ["7","14","1","2","blockposition"],
                ["7","11","1","3","blockposition"],
                ["7","2","1","4","blockposition"],
                ["7","12","1","5","blockposition"],
                ["10","6","1","1","blockposition"],
                ["11","13","1","1","blockposition"],
                ["11","4","1","2","blockposition"],
                ["11","9","1","3","blockposition"],
                ["3","7","2","1","blockposition"],
                ["3","10","2","2","blockposition"],
                ["3","3","2","3","blockposition"],
                ["10","6","2","1","blockposition"],
                ["11","13","2","1","blockposition"],
                ["11","4","2","2","blockposition"],
                ["11","9","2","3","blockposition"],
            ];

            /** @var $block \Eccube\Entity\Block */
            foreach ($installData as $data) {
                $block = $blockRepository->find($data[1]);
                $blockPosition = new BlockPosition();

                if ($data[2] == 1) {
                    $layout = $ampTopLayout;
                } else {
                    $layout = $ampLayout;
                }

                $blockPosition->setLayout($layout);
                $blockPosition->setLayoutId($layout->getId());
                $blockPosition->setBlock($block);
                $blockPosition->setBlockId($block->getId());
                $blockPosition->setBlockRow($data[3]);
                $blockPosition->setSection($data[0]);
                $entityManager->persist($blockPosition);
            }

            /** @var $Config \Plugin\Amp4\Entity\Config */
            $Config = $container->get(ConfigRepository::class)->get();
            $Config->setAmpHeaderCss(file_get_contents(__DIR__ . '/Resource/amp_css/header.css'));
            $Config->setOptimize(false);
            $Config->setCanonical(false);
            $Config->setAmpTwigApiUrl("");

            $entityManager->persist($Config);

            $ampPages = $container->get(PageRepository::class)->findBy(['url' => ['homepage', 'product_list', 'product_detail']]);

            $ampPageLayout = $container->get(PageLayoutRepository::class)->findOneBy([], ['sort_no' => 'DESC']);
            $sortNo = $ampPageLayout ? $ampPageLayout->getSortNo() + 1 : 1;

            /** @var $ampPage Page */
            foreach ($ampPages as $ampPage) {

                $ampPageLayout = new PageLayout();
                $ampPageLayout->setPage($ampPage);
                $ampPageLayout->setPageId($ampPage->getId());

                if ($ampPage->getUrl() == 'homepage') {
                    $ampPageLayout->setLayout($ampTopLayout);
                    $ampPageLayout->setLayoutId($ampTopLayout->getId());
                } else {
                    $ampPageLayout->setLayout($ampLayout);
                    $ampPageLayout->setLayoutId($ampLayout->getId());
                }

                $ampPageLayout->setSortNo($sortNo);
                $ampPage->addPageLayout($ampPageLayout);
                $entityManager->persist($ampPage);

                $sortNo++;
            }

            //$entityManager->getConnection()->exec("update dtb_page set is_amp = 1 where url IN ('homepage', 'product_list', 'product_detail')");

            $stmt = $entityManager->getConnection()->prepare("update dtb_page set is_amp = :is_amp, amp_css = :amp_css where url = 'homepage'");
            $stmt->execute(['amp_css' => file_get_contents(__DIR__ . '/Resource/amp_css/top.css'), 'is_amp' => true]);

            $stmt = $entityManager->getConnection()->prepare("update dtb_page set is_amp = :is_amp, amp_css = :amp_css where url = 'product_list'");
            $stmt->execute(['amp_css' => file_get_contents(__DIR__ . '/Resource/amp_css/list.css'), 'is_amp' => true]);

            $stmt = $entityManager->getConnection()->prepare("update dtb_page set is_amp = :is_amp, amp_css = :amp_css where url = 'product_detail'");
            $stmt->execute(['amp_css' => file_get_contents(__DIR__ . '/Resource/amp_css/detail.css'), 'is_amp' => true]);

            $entityManager->flush();
        }
    }

    /**
     * @param array $meta
     * @param ContainerInterface $container
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function uninstall(array $meta, ContainerInterface $container)
    {
        /** @var $entityManager EntityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $ampDevice = $container->get(DeviceTypeRepository::class)->find(DeviceTypeTrait::$DEVICE_TYPE_AMP);

        if ($ampDevice) {

            $ampLayouts = $container->get(LayoutRepository::class)->findBy(['DeviceType' => $ampDevice]);
            $ampPageLayouts = $container->get(PageLayoutRepository::class)->findBy(['Layout' => $ampLayouts]);

            /** @var $ampPageLayout PageLayout */
            foreach ($ampPageLayouts as $ampPageLayout) {
                $entityManager->remove($ampPageLayout);
            }

            if (count($ampLayouts)) {
                foreach ($ampLayouts as $ampLayout) {
                    $entityManager->remove($ampLayout);
                }
            }

            $entityManager->remove($ampDevice);
            $entityManager->flush();
        }
    }

}
