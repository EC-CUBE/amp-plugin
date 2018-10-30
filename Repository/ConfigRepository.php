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

namespace Plugin\Amp4\Repository;

use Eccube\Repository\AbstractRepository;
use Plugin\Amp4\Entity\Config;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Class ConfigRepository
 */
class ConfigRepository extends AbstractRepository
{

    protected $config;

    /**
     * ConfigRepository constructor.
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
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
        }

        return $config;
    }
}
