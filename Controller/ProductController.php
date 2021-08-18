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

use Eccube\Controller\AbstractController;
use Eccube\Entity\Product;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use Knp\Component\Pager\Paginator;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

/**
 * Class ProductController
 * @package Plugin\Amp4\Controller
 */
class ProductController extends \Eccube\Controller\ProductController
{
    /**
     * 商品一覧画面.
     *
     * @Route("/amp/products/list", name="amp_product_list")
     * @Template("Product/list.twig")
     *
     * @param Request $request
     * @param Paginator $paginator
     * @return array|void
     */
    public function index(Request $request, PaginatorInterface $paginator)
    {
        $reData = parent::index($request, $paginator);

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
        $reData['strApiJsonParame'] = $strApiJsonParame;

        return $reData;
    }

    /**
     * 商品詳細画面.
     *
     * @Route("/amp/products/detail/{id}", name="amp_product_detail", methods={"GET"}, requirements={"id" = "\d+"})
     * @Template("Product/detail.twig")
     * @ParamConverter("Product", options={"repository_method" = "findWithSortedClassCategories"})
     *
     * @param Request $request
     * @param Product $Product
     *
     * @return array
     */
    public function detail(Request $request, Product $Product)
    {
        return parent::detail($request, $Product);
    }
}
