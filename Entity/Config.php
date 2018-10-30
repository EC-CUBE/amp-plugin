<?php

namespace Plugin\Amp4\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Config
 *
 * @ORM\Table(name="plg_amp4_config")
 * @ORM\Entity(repositoryClass="Plugin\Amp4\Repository\ConfigRepository")
 */
class Config extends \Eccube\Entity\AbstractEntity
{

    public $ampPageUrl = [
        'homepage',
        'product_list',
        'product_detail',
        'help_about',
        'help_tradelaw',
        'help_privacy',
        'help_guide'
    ];

    /**
     * @return array
     */
    public function getAmpPageUrl()
    {
        return $this->ampPageUrl;
    }

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer", options={"unsigned":true})
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * api通信先
     *
     * @var string
     *
     * @ORM\Column(name="amp_twig_api_url", type="string", length=1024, nullable=true)
     */
    private $amp_twig_api_url;

    /**
     * canonical amp判断
     *
     * @var boolean
     *
     * @ORM\Column(name="canonical_amp", type="boolean", options={"default":false})
     */
    private $canonical;

    /**
     * 最適化 amp判断
     *
     * @var boolean
     *
     * @ORM\Column(name="optimize_amp", type="boolean", options={"default":false})
     */
    private $optimize;

    /**
     * @var string
     *
     * @ORM\Column(name="amp_header_css", type="text", nullable=true)
     */
    private $amp_header_css;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getAmpTwigApiUrl()
    {
        return $this->amp_twig_api_url;
    }

    /**
     * @param string $amp_twig_api_url
     * @return Config
     */
    public function setAmpTwigApiUrl($amp_twig_api_url): Config
    {
        $this->amp_twig_api_url = $amp_twig_api_url;
        return $this;
    }

    /**
     * @return bool
     */
    public function isCanonical(): bool
    {
        return $this->canonical === true;
    }

    /**
     * @param bool $canonical
     * @return Config
     */
    public function setCanonical($canonical): Config
    {
        $this->canonical = $canonical;
        return $this;
    }

    /**
     * @return bool
     */
    public function getCanonical()
    {
        return $this->isCanonical();
    }

    /**
     * @return bool
     */
    public function isOptimize(): bool
    {
        return $this->optimize === true;
    }

    /**
     * @param bool $optimize
     * @return Config
     */
    public function setOptimize($optimize): Config
    {
        $this->optimize = $optimize;
        return $this;
    }

    /**
     * @return bool
     */
    public function getOptimize()
    {
        return $this->isOptimize();
    }

    /**
     * @return string
     */
    public function getAmpHeaderCss()
    {
        return $this->amp_header_css;
    }

    /**
     * @param string $amp_header_css
     * @return Config
     */
    public function setAmpHeaderCss($amp_header_css): Config
    {
        $this->amp_header_css = $amp_header_css;
        return $this;
    }

}