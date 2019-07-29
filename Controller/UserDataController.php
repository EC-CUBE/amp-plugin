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

namespace Plugin\Amp4\Controller;

use Eccube\Entity\Page;
use Eccube\Repository\Master\DeviceTypeRepository;
use Eccube\Repository\PageRepository;
use Plugin\Amp4\Repository\ConfigRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

/**
 * Class UserDataController
 * @package Plugin\Amp4\Controller
 */
class UserDataController extends \Eccube\Controller\UserDataController
{

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * UserDataController constructor.
     * @param PageRepository $pageRepository
     * @param DeviceTypeRepository $deviceTypeRepository
     * @param ConfigRepository $configRepository
     */
    public function __construct(PageRepository $pageRepository,
                                DeviceTypeRepository $deviceTypeRepository,
                                ConfigRepository $configRepository)
    {
        parent::__construct($pageRepository, $deviceTypeRepository);
        $this->configRepository = $configRepository;
    }

    /**
     * @Route("/amp/%eccube_user_data_route%/{route}", name="amp_user_data", requirements={"route": "([0-9a-zA-Z_\-]+\/?)+(?<!\/)"})
     *
     * @param Request $request
     * @param $route
     * @return mixed
     */
    public function index(Request $request, $route)
    {
        $Page = $this->pageRepository->findOneBy(
            [
                'url' => $route,
                'edit_type' => Page::EDIT_TYPE_USER,
            ]
        );

        if (null === $Page) {
            throw new NotFoundHttpException();
        }

        if ($this->configRepository->get()->isOptimize() && $Page->isAmp()) {
            $file = sprintf('@user_data/amp-optimizer/%s.twig', $Page->getFileName());
        } else {
            $file = sprintf('@user_data/amp/%s.twig', $Page->getFileName());
        }

        return $this->render($file);
    }
}
