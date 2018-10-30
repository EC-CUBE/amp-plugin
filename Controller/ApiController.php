<?php

namespace Plugin\Amp4\Controller;

use Eccube\Common\Constant;
use Eccube\Controller\AbstractController;
use Eccube\Entity\Master\ProductStatus;
use Eccube\Entity\Product;
use Eccube\Form\Type\AddCartType;
use Eccube\Form\Type\SearchProductType;
use Eccube\Repository\BaseInfoRepository;
use Eccube\Repository\CategoryRepository;
use Eccube\Repository\CustomerFavoriteProductRepository;
use Eccube\Repository\Master\ProductListMaxRepository;
use Eccube\Repository\Master\ProductListOrderByRepository;
use Eccube\Repository\NewsRepository;
use Eccube\Repository\ProductRepository;
use Eccube\Service\CartService;
use Eccube\Twig\Extension\EccubeExtension;
use Eccube\Twig\Extension\IntlExtension;
use Eccube\Util\StringUtil;
use Knp\Bundle\PaginatorBundle\Pagination\SlidingPagination;
use Knp\Component\Pager\Paginator;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class ApiController
 * @package Plugin\Amp4\Controller
 */
class ApiController extends AbstractController
{
    /**
     * @var \Eccube\Entity\BaseInfo
     */
    protected $BaseInfo;

    public function __construct(BaseInfoRepository $baseInfoRepository)
    {
        $this->BaseInfo = $baseInfoRepository->get();
    }

    /**
     * @Route("/amp-api/search.json", name="amp_api_search")
     * @param Request $request
     * @param CategoryRepository $categoryRepository
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function search(Request $request, CategoryRepository $categoryRepository)
    {
        $reData = [
            'items' => [
                [
                    'search' => [
                        [
                            'default' => [],
                            'category' => [],
                        ],
                    ],
                ]
            ],
        ];

        $defaultData = [];
        if ($request->get('name')) {
            $defaultData['keyword'] = $request->get('name');
        }

        if ($request->get('category_id')) {
            $defaultData['category_id'] = $request->get('category_id');
        }

        if (count($defaultData)) {
            $reData['items'][0]['search'][0]['default'][] = $defaultData;
        }

        $categprys = $categoryRepository->getList(null, true);

        foreach ($categprys as $category) {
            $reData['items'][0]['search'][0]['category'][] = ['id' => $category->getId(), 'name' => $category->getNameWithLevel() . $category->getName()];
        }


        return $this->json($reData, 200, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * @Route("/amp-api/news.json", name="amp_api_news")
     * @param Request $request
     * @param NewsRepository $newsRepository
     * @param IntlExtension $intlExtension
     * @param Environment $environment
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function newsJson(Request $request, NewsRepository $newsRepository, IntlExtension $intlExtension, Environment $environment)
    {
        $reData = [
            'items' => [
                [
                    'news' => [],
                ]
            ],
        ];

        $newsList = $newsRepository->getList();
        foreach ($newsList as $news) {
            $data = [
                'date' => $intlExtension->date_day($environment, $news->getPublishDate()),
                'title' => $news->getTitle(),
            ];

            if ($news->getDescription()) {
                $data['description'] = $news->getDescription();
            }

            if ($news->isLinkMethod()) {
                $data['link_method'] = true;

                if ($news->getUrl()) {
                    $data['url'] = $news->getUrl();
                } else {
                    $data['link_method'] = false;
                }
            }

            $reData['items'][0]['news'][] = $data;
        }

        return $this->json($reData, 200, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * @Route("/amp-api/products/list/class_categories.json", name="amp_api_products_list_class_categories")
     *
     * @param Request $request
     * @param Paginator $paginator
     * @param ProductRepository $productRepository
     * @param ProductListMaxRepository $productListMaxRepository
     * @param EccubeExtension $eccubeExtension
     * @param CsrfTokenManagerInterface $tokenManager
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function productsClassCategories(Request $request, Paginator $paginator,
                                            ProductRepository $productRepository,
                                            ProductListMaxRepository $productListMaxRepository,
                                            EccubeExtension $eccubeExtension,
                                            CsrfTokenManagerInterface $tokenManager)
    {
        // Doctrine SQLFilter
        if ($this->BaseInfo->isOptionNostockHidden()) {
            $this->entityManager->getFilters()->enable('option_nostock_hidden');
        }

        // handleRequestは空のqueryの場合は無視するため
        if ($request->getMethod() === 'GET') {
            $request->query->set('pageno', $request->query->get('pageno', ''));
        }

        // searchForm
        /* @var $builder \Symfony\Component\Form\FormBuilderInterface */
        $builder = $this->formFactory->createNamedBuilder('', SearchProductType::class);

        if ($request->getMethod() === 'GET') {
            $builder->setMethod('GET');
        }

        /* @var $searchForm \Symfony\Component\Form\FormInterface */
        $searchForm = $builder->getForm();

        $searchForm->handleRequest($request);

        // paginator
        $searchData = $searchForm->getData();

        $qb = $productRepository->getQueryBuilderBySearchData($searchData);

        $query = $qb->getQuery()
            ->useResultCache(true, $this->eccubeConfig['eccube_result_cache_lifetime_short']);

        /** @var SlidingPagination $pagination */
        $pagination = $paginator->paginate(
            $query,
            !empty($searchData['pageno']) ? $searchData['pageno'] : 1,
            !empty($searchData['disp_number']) ? $searchData['disp_number']->getId() : $productListMaxRepository->findOneBy([], ['sort_no' => 'ASC'])->getId()
        );

        $ids = [];
        foreach ($pagination as $Product) {
            $ids[] = $Product->getId();
        }

        $ProductsAndClassCategories = $productRepository->findProductsWithSortedClassCategories($ids, 'p.id');

        $reData = ['items' => [
            [
                'products' => [],
            ],
        ]];

        if (count($ProductsAndClassCategories)) {

            foreach ($ids as $id) {
                /** @var $ProductsAndClassCategorie \Eccube\Entity\Product */
                foreach ($ProductsAndClassCategories as $ProductsAndClassCategorie) {

                    if ($ProductsAndClassCategorie->getId() == $id) {
                        $classData = $this->createClassCategoriesData($ProductsAndClassCategorie, $tokenManager->getToken(AddCartType::class)->getValue());

                        $data = [
                            'product_id' => $ProductsAndClassCategorie->getId(),
                            'product_url' => $this->generateUrl('product_detail', ['id' => $ProductsAndClassCategorie->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                            'add_card_url' => $this->generateUrl('product_add_cart', ['id' => $ProductsAndClassCategorie->getId(), 'type' => 'amp'], UrlGeneratorInterface::ABSOLUTE_URL),
                            'product_name' => $ProductsAndClassCategorie->getName(),
                            'product_image' => $request->getSchemeAndHttpHost() . $this->get('assets.packages')->getUrl($eccubeExtension->getNoImageProduct($ProductsAndClassCategorie->getMainListImage()), 'save_image'),
                        ];

                        if ($ProductsAndClassCategorie->hasProductClass()) {
                            $data['default_price02'] = sprintf('￥%s', number_format($ProductsAndClassCategorie->getProductClasses()[0]->getPrice02IncTax()));
                        } else if ($ProductsAndClassCategorie->getPrice02Min() == $ProductsAndClassCategorie->getPrice02Max()) {
                            $data['default_price02'] = sprintf('￥%s', number_format($ProductsAndClassCategorie->getPrice02IncTaxMin()));
                        } else {
                            $data['default_price02'] = sprintf('￥%s ～ ￥%s',
                                number_format($ProductsAndClassCategorie->getPrice02IncTaxMin()),
                                number_format($ProductsAndClassCategorie->getPrice02IncTaxMax()));
                        }

                        $reData['items'][0]['products'][] = array_merge($classData, $data);

                        continue;
                    }
                }
            }
        }

        return $this->json($reData, 200, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * @Route("/amp-api/products/list.json", name="amp_api_products_list")
     *
     * @param Request $request
     * @param Paginator $paginator
     * @param ProductRepository $productRepository
     * @param ProductListMaxRepository $productListMaxRepository
     * @param ProductListOrderByRepository $productListOrderByRepository
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function productList(Request $request, Paginator $paginator,
                                ProductRepository $productRepository,
                                ProductListMaxRepository $productListMaxRepository,
                                ProductListOrderByRepository $productListOrderByRepository)
    {
        // Doctrine SQLFilter
        if ($this->BaseInfo->isOptionNostockHidden()) {
            $this->entityManager->getFilters()->enable('option_nostock_hidden');
        }

        $request->query->set('pageno', $request->query->get('pageno', ''));

        // searchForm
        /* @var $builder \Symfony\Component\Form\FormBuilderInterface */
        $builder = $this->formFactory->createNamedBuilder('', SearchProductType::class);
        $builder->setMethod('GET');

        /* @var $searchForm \Symfony\Component\Form\FormInterface */
        $searchForm = $builder->getForm();

        $searchForm->handleRequest($request);

        // paginator

        $searchData = $searchForm->getData();
        $qb = $productRepository->getQueryBuilderBySearchData($searchData);

        $query = $qb->getQuery()
            ->useResultCache(true, $this->eccubeConfig['eccube_result_cache_lifetime_short']);

        /** @var SlidingPagination $pagination */
        $pagination = $paginator->paginate(
            $query,
            !empty($searchData['pageno']) ? $searchData['pageno'] : 1,
            !empty($searchData['disp_number']) ? $searchData['disp_number']->getId() : $productListMaxRepository->findOneBy([], ['sort_no' => 'ASC'])->getId()
        );
        
        $reData = [
            "items" => [[
                "default_category_id" => "",
                "default_name" => isset($searchData['name']) && StringUtil::isNotBlank($searchData['name']) ? $searchData['name'] : "",
                "default_pageno" => !empty($searchData['pageno']) ? $searchData['pageno'] : 1,
                "default_disp_number" => !empty($searchData['disp_number']) ? $request->get('disp_number', 0) : 0,
                "default_orderby" => !empty($searchData['orderby']) ? $searchData['orderby']->getId() : 0,
                "products_number" => $pagination->getTotalItemCount(),
                "disp_number" => [],
                "pager" => [],
                "searchCategory" => []
            ],],
        ];

        if (!empty($searchData['category_id']) && $searchData['category_id']) {
            $Categories = $searchData['category_id']->getSelfAndDescendants();
            if ($Categories) {
                $reData['items'][0]['default_category_id'] = $Categories[0]->getId();
            }
        }

        /** @var $productListMax \Eccube\Entity\Master\ProductListMax */
        foreach ($productListMaxRepository->findBy([], ['sort_no' => 'ASC']) as $key => $productListMax) {
            $reData['items'][0]['disp_number'][] = [
                'id' => $key,
                'name' => $productListMax->getName(),
            ];
        }

        /** @var $productListOrderBy \Eccube\Entity\Master\ProductListOrderBy */
        foreach ($productListOrderByRepository->findBy([], ['sort_no' => 'ASC']) as $productListOrderBy) {
            $reData['items'][0]['orderby'][] = [
                'id' => $productListOrderBy->getId(),
                'name' => $productListOrderBy->getName(),
            ];
        }

        if ($pagination->getPageCount() > 1) {
            $pageData = [
                'page' => [],
                'navi_prev' => [],
                'navi_next' => [],
            ];

            $pageRange = $pagination->getPaginationData()['pagesInRange'];
            foreach ($pageRange as $p) {
                $pageData['page'][] = [
                    'number' => $p,
                    'text' => $p,
                ];
            }

            if (array_key_exists('previous', $pagination->getPaginationData())) {
                $pageData['navi_prev'][] = [
                    'number' => $pagination->getPaginationData()['previous'],
                    'text' => trans('前へ'),
                ];
            }

            if (array_key_exists('next', $pagination->getPaginationData())) {
                $pageData['navi_next'][] = [
                    'number' => $pagination->getPaginationData()['next'],
                    'text' => trans('次へ'),
                ];
            }

            $reData['items'][0]['pager'][] = $pageData;
        }

        /** @var $Category \Eccube\Entity\Category */
        $Category = $searchForm->get('category_id')->getData();
        if ($Category) {
            foreach ($Category->getPath() as $path) {
                $reData['items'][0]['searchCategory'][] = [
                    'id' => $path->getId(),
                    'name' => $path->getName(),
                ];
            }
        }

        return $this->json($reData, 200, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * @param Product $Product
     * @return array
     */
    protected function createClassCategoriesData(Product $Product, $token = '')
    {
        $data = [];

        if ($Product->hasProductClass()) {

            $isClass2 = false;

            /**@var $ProductClass \Eccube\Entity\ProductClass*/
            foreach ($Product->getProductClasses() as $ProductClass) {
                $ClassCategory2 = $ProductClass->getClassCategory2();
                if ($ClassCategory2 && $ClassCategory2->isVisible()) {
                    $isClass2 = true;
                }
                break;
            }


            if ($isClass2) {//2規格商品
                $data['classcategory_1'] = [];

                $ProductClasses = $Product->getProductClasses();
                foreach ($ProductClasses as $ProductClass) {

                    $ClassCategory2 = $ProductClass->getClassCategory2();
                    if ($ClassCategory2 && !$ClassCategory2->isVisible()) {
                        continue;
                    }

                    $data['classcategory_1'][$ProductClass->getClassCategory1()->getId()] = [
                        'id' => $ProductClass->getClassCategory1()->getId(),
                        'name' => $ProductClass->getClassCategory1()->getName(),
                        'classcategory_2' => [],
                    ];
                }

                foreach ($ProductClasses as $ProductClass) {

                    $ClassCategory2 = $ProductClass->getClassCategory2();
                    if ($ClassCategory2 && !$ClassCategory2->isVisible()) {
                        continue;
                    }

                    if (array_key_exists($ProductClass->getClassCategory1()->getId(), $data['classcategory_1'])) {
                        $data['classcategory_1'][$ProductClass->getClassCategory1()->getId()]['classcategory_2'][] = [
                            "id" => $ProductClass->getClassCategory2()->getId(),
                            "name" => $ProductClass->getClassCategory2()->getName(). ($ProductClass->getStockFind() ? '' : trans('front.product.out_of_stock_label')),
                            "stock_find" => $ProductClass->getStockFind(),
                            "price01" => $ProductClass->getPrice01() ? $ProductClass->getPrice01() : '',
                            "price02" => $ProductClass->getPrice02(),
                            "price01_inc_tax" => $ProductClass->getPrice01() ? sprintf("￥%s", number_format($ProductClass->getPrice01IncTax())) : '',
                            "price02_inc_tax" => sprintf("￥%s", number_format($ProductClass->getPrice02IncTax())),
                            "product_class_id" => $ProductClass->getId(),
                            Constant::TOKEN_NAME => $token,
                            "product_code" => $ProductClass->getCode() ? $ProductClass->getCode() : '',
                            "sale_type" => $ProductClass->getSaleType() ? $ProductClass->getSaleType()->getId() : '',
                        ];
                    }
                }

                $data['classcategory_1'] = array_values($data['classcategory_1']);

            } else {//1規格商品
                $data['classcategory_1'] = [];

                foreach ($Product->getProductClasses() as $ProductClass) {

                    if ($ProductClass->getClassCategory1() && !$ProductClass->getClassCategory1()->isVisible()) {
                        continue;
                    }

                    $data['classcategory_1'][] = [
                        "id" => $ProductClass->getClassCategory1()->getId(),
                        "name" => $ProductClass->getClassCategory1()->getName(). ($ProductClass->getStockFind() ? '' : trans('front.product.out_of_stock_label')),
                        "stock_find" => $ProductClass->getStockFind(),
                        "price01" => $ProductClass->getPrice01() ? $ProductClass->getPrice01() : '',
                        "price02" => $ProductClass->getPrice02(),
                        "price01_inc_tax" => $ProductClass->getPrice01() ? sprintf("￥%s", number_format($ProductClass->getPrice01IncTax())) : '',
                        "price02_inc_tax" => sprintf("￥%s", number_format($ProductClass->getPrice02IncTax())),
                        "product_class_id" => $ProductClass->getId(),
                        Constant::TOKEN_NAME => $token,
                        "product_code" => $ProductClass->getCode() ? $ProductClass->getCode() : '',
                        "sale_type" => $ProductClass->getSaleType() ? $ProductClass->getSaleType()->getId() : '',
                    ];
                }
            }

            if ($Product->getPrice01Min()) {
                if ($Product->getPrice01Min() == $Product->getPrice02Max()) {
                    $data['default_price01'] = sprintf("￥%s", number_format($Product->getPrice01IncTaxMin()));
                } else {
                    $data['default_price01'] = sprintf("￥%s～ ￥%s", number_format($Product->getPrice01IncTaxMin()), number_format($Product->getPrice01IncTaxMax()));
                }
            }

            if ($Product->getPrice02Min() == $Product->getPrice02Max()) {
                $data['default_price02'] = sprintf("￥%s", number_format($Product->getPrice02IncTaxMin()));
            } else {
                $data['default_price02'] = sprintf("￥%s～ ￥%s", number_format($Product->getPrice02IncTaxMin()), number_format($Product->getPrice02IncTaxMax()));
            }

            if ($Product->getCodeMin()) {
                if ($Product->getCodeMin() == $Product->getCodeMax()) {
                    $data['default_code'] = $Product->getCodeMin();
                } else {
                    $data['default_code'] = sprintf("%s ～ %s", $Product->getCodeMin(), $Product->getCodeMax());
                }
            }

        } else {//規格なし商品
            $data = [
                "default_price02" => sprintf("￥%s", number_format($Product->getPrice02IncTaxMin())),
                "stock_find" => $Product->getProductClasses()[0]->getStockFind(),
                "price02" => $Product->getPrice02Min(),
                "price02_inc_tax" => sprintf("￥%s", number_format($Product->getPrice02IncTaxMin())),
                "product_class_id" => $Product->getProductClasses()[0]->getId(),
                Constant::TOKEN_NAME => $token,
                "product_code" => $Product->getCodeMin() ? $Product->getCodeMin() : '',
                "sale_type" => $Product->getProductClasses()[0]->getSaleType() ? $Product->getProductClasses()[0]->getSaleType()->getId() : '',
            ];

            if ($Product->getPrice01Min()) {
                $data['default_price01'] = sprintf("￥%s", number_format($Product->getPrice01IncTaxMin()));
            }

            if ($Product->getCodeMin()) {
                $data['default_code'] = $Product->getCodeMin();
            }

            if ($Product->getPrice01Min()) {
                $data['price01'] = $Product->getPrice01Min();
            }

            if ($Product->getPrice01Min()) {
                $data['price01_inc_tax'] = sprintf("￥%s", number_format($Product->getPrice01IncTaxMin()));
            }
        }

        return $data;
    }

    /**
     * @Route("/amp-api/products/detail/{id}/class_categories_json.json", name="amp_api_class_categories", methods={"GET"}, requirements={"id" = "\d+"})
     * @ParamConverter("Product", options={"repository_method" = "findWithSortedClassCategories"})
     *
     * @param Request $request
     * @param Product $Product
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function classCategoriesJson(Request $request, Product $Product, CsrfTokenManagerInterface $tokenManager)
    {
        if (!$this->checkVisibility($Product)) {
            throw new NotFoundHttpException();
        }
        
        $reData = [
            'items' => [],
        ];

        $reData['items'][] = $this->createClassCategoriesData($Product, $tokenManager->getToken(AddCartType::class)->getValue());

        return $this->json($reData, 200, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * @Route("/amp-api/products/detail/{id}/favorite_show.json", name="amp_api_product_favorite_show", methods={"GET"}, requirements={"id" = "\d+"})
     */
    public function productFavoriteProductShow(Request $request, Product $Product, CustomerFavoriteProductRepository $customerFavoriteProductRepository)
    {
        $reData = [
            'items' => [],
        ];

        if ($this->BaseInfo->isOptionFavoriteProduct()) {
            $reData['items'][] = [
                'favorite' => true,
            ];

            if ($this->isGranted('ROLE_USER')) {
                $Customer = $this->getUser();
                if ($customerFavoriteProductRepository->isFavorite($Customer, $Product)) {
                    $reData['items'][0]['favorite'] = false;
                }
            }
        }

        return $this->json($reData, 200, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * @Route("/amp-api/cart/list.json", name="amp_api_cart_list", methods={"GET"})
     */
    public function cardListJson(Request $request, CartService $cartService, EccubeExtension $eccubeExtension)
    {
        $Carts = $cartService->getCarts();

        $totalQuantity = array_reduce($Carts, function ($total, $Cart) {
            /* @var Cart $Cart */
            $total += $Cart->getTotalQuantity();

            return $total;
        }, 0);
        $totalPrice = array_reduce($Carts, function ($total, $Cart) {
            /* @var Cart $Cart */
            $total += $Cart->getTotalPrice();

            return $total;
        }, 0);


        $reData = [
            'items' => [
                [
                    'total_quantity' => number_format($totalQuantity),
                    'total_price' => sprintf("￥%s", number_format($totalPrice)),
                    'cards' => [],
                ],
            ],
        ];

        if ($totalQuantity > 0) {
            foreach ($Carts as $cart) {
                foreach ($cart->getCartItems() as $item) {
                    $product = $item->getProductClass()->getProduct();

                    $productClassName = '';
                    if ($item->getProductClass()->getClassCategory1() && $item->getProductClass()->getClassCategory1()->getId()) {
                        $productClassName = $item->getProductClass()->getClassCategory1()->getClassName()->getName() .
                            ": " .
                            $item->getProductClass()->getClassCategory1()->getName();
                    }

                    if ($item->getProductClass()->getClassCategory2() && $item->getProductClass()->getClassCategory2()->getId()) {
                        $productClassName .= "\n" . $item->getProductClass()->getClassCategory2()->getClassName()->getName() .
                            ": " .
                            $item->getProductClass()->getClassCategory2()->getName();
                    }

                    $reData['items'][0]['carts'][0]['items'][] = [
                        'product_name' => $product->getName(),
                        'product_image' =>  $request->getSchemeAndHttpHost() .
                            $this->get('assets.packages')->getUrl($eccubeExtension->getNoImageProduct($product->getMainListImage()), 'save_image'),
                        'product_class_name' => $productClassName,
                        'product_price' => sprintf("￥%s", number_format($item->getPriceIncTax())),
                        'product_quantity' => number_format($item->getQuantity()),
                    ];
                }
            }
        }

        return $this->json($reData, 200, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * @Route("/amp-api/nav/item.json", name="amp_api_nav_item", methods={"GET"})
     */
    public function navItem()
    {

        $reData = [
            'items' => [
                [
                    'mypage_entry' => [],
                    'favorite' => [],
                    'login_logout' => [],
                ],
            ],
        ];

        if ($this->isGranted('ROLE_USER')) {
            $reData['items'][0]['mypage_entry'][] = [
                'url' => $this->generateUrl('mypage', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'text' => trans('front.block.login.mypage'),
            ];

            $reData['items'][0]['login_logout'][] = [
                'url' => $this->generateUrl('logout', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'text' => trans('front.block.login.logout'),
            ];
        } else {
            $reData['items'][0]['mypage_entry'][] = [
                'url' => $this->generateUrl('entry', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'text' => trans('front.block.login.customer_registration'),
            ];

            $reData['items'][0]['login_logout'][] = [
                'url' => $this->generateUrl('mypage_login', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'text' => trans('front.block.login.login'),
            ];
        }

        if ($this->BaseInfo->isOptionFavoriteProduct()) {
            $reData['items'][0]['favorite'][] = [
                'url' => $this->generateUrl('mypage_favorite', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'text' => trans('front.block.login.favorite'),
            ];
        }

        return $this->json($reData, 200, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * 閲覧可能な商品かどうかを判定
     *
     * @param Product $Product
     *
     * @return boolean 閲覧可能な場合はtrue
     */
    private function checkVisibility(Product $Product)
    {
        $is_admin = $this->session->has('_security_admin');

        // 管理ユーザの場合はステータスやオプションにかかわらず閲覧可能.
        if (!$is_admin) {
            // 在庫なし商品の非表示オプションが有効な場合.
            // if ($this->BaseInfo->isOptionNostockHidden()) {
            //     if (!$Product->getStockFind()) {
            //         return false;
            //     }
            // }
            // 公開ステータスでない商品は表示しない.
            if ($Product->getStatus()->getId() !== ProductStatus::DISPLAY_SHOW) {
                return false;
            }
        }

        return true;
    }

}