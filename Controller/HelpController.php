<?php

/*
 * This file is part of EC-CUBE
 *
 * copyright (c) EC-CUBE CO.,LTD. all rights reserved.
 *
 * https://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\Amp4\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class HelpController
 * @package Plugin\Amp4\Controller
 */
class HelpController extends \Eccube\Controller\HelpController
{

    /**
     * 特定商取引法.
     *
     * @Route("/amp/help/tradelaw", name="amp_help_tradelaw")
     * @Template("Help/tradelaw.twig")
     */
    public function tradelaw()
    {
        return parent::tradelaw();
    }

    /**
     * ご利用ガイド.
     *
     * @Route("/amp/guide", name="amp_help_guide")
     * @Template("Help/guide.twig")
     */
    public function guide()
    {
        return parent::guide();
    }

    /**
     * 当サイトについて.
     *
     * @Route("/amp/help/about", name="amp_help_about")
     * @Template("Help/about.twig")
     */
    public function about()
    {
        return parent::about();
    }

    /**
     * プライバシーポリシー.
     *
     * @Route("/amp/help/privacy", name="amp_help_privacy")
     * @Template("Help/privacy.twig")
     */
    public function privacy()
    {
        return parent::privacy();
    }

    /**
     * 利用規約.
     *
     * @Route("/amp/help/agreement", name="amp_help_agreement")
     * @Template("Help/agreement.twig")
     */
    public function agreement()
    {
        return parent::agreement();
    }
}
