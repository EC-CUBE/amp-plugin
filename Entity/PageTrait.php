<?php

namespace Plugin\Amp4\Entity;

use Eccube\Annotation\EntityExtension;
use Doctrine\ORM\Mapping as ORM;


/**
 * @EntityExtension("Eccube\Entity\Page")
 */
trait PageTrait
{
    /**
     * @var boolean
     *
     * @ORM\Column(name="is_amp", type="boolean", options={"unsigned":true,"default":false})
     */
    private $is_amp = false;

    /**
     * @var string
     *
     * @ORM\Column(name="amp_css", type="text", nullable=true)
     */
    private $amp_css;

    /**
     * @param bool $is_amp
     * @return $this
     */
    public function setIsAmp($is_amp)
    {
        $this->is_amp = $is_amp;
        return $this;
    }

    /**
     * @return bool
     */
    public function getIsAmp()
    {
        return $this->is_amp;
    }

    /**
     * @return bool
     */
    public function isAmp()
    {
        return $this->getIsAmp();
    }

    /**
     * @param $amp_css
     * @return $this
     */
    public function setAmpCss($amp_css)
    {
        $this->amp_css = $amp_css;
        return $this;
    }

    /**
     * @return string
     */
    public function getAmpCss()
    {
        return $this->amp_css;
    }
}