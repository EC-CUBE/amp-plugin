<?php

namespace Plugin\Amp4\Service;

use Plugin\Amp4\Repository\ConfigRepository;
use GuzzleHttp\Client;
use Twig\Loader\ArrayLoader;
use Twig\Source;
use Twig\Error\Error;

class HttpSend
{
    /**
     * @var Client
     */
    protected static $client;

    /**
     * @var \Twig_Environment
     */
    protected $twig;

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * HttpSend constructor.
     * @param ConfigRepository $configRepository
     * @param \Twig_Environment $twig
     */
    public function __construct(ConfigRepository $configRepository,
                                \Twig_Environment $twig)
    {
        $this->configRepository = $configRepository;
        $this->twig = $twig;
    }

    /**
     * @return Client
     */
    public function GetClient()
    {
        if (self::$client) {
            return self::$client;
        }

        self::$client = new Client(
            [
                'base_uri' => $this->configRepository->get()->getAmpTwigApiUrl(),
                'headers' => [ 'Content-Type' => 'application/json' ],
                'timeout' => '360',
            ]
        );
        return self::$client;
    }

    /**
     * @param array $templateData
     * @return mixed
     * @throws HttpSendException
     */
    public function sendData(array $templateData)
    {
        $response = self::GetClient()->post('', ['body' => json_encode(["twig" => $templateData])]);

        if ($response->getStatusCode() == 200) {

            if ($response->getBody()) {
                return json_decode($response->getBody()->getContents(), 1);
            } else {
                $mess = 'not response body';
                log_info($mess);
                throw new HttpSendException($mess);
            }

            return json_decode($data, 1);
        } else {
            $mess = sprintf('[http error]http code:%d', $response->getStatusCode());
            log_error(sprintf('[http error]http code:%d', $response->getStatusCode()));
            throw new HttpSendException($mess);
        }
    }

    /**
     * @param $html
     * @return array
     */
    public function toEncodeTwig($html)
    {
        $p = '/<\!--amp_tag_start-->{%.*%}<\!--amp_tag_end-->|<\!--amp_tag_start-->{{.*}}<\!--amp_tag_end-->/iU';

        preg_match_all($p, $html, $matches);

        $reKey = [];
        $limit = 1;

        if (count($matches)) {

            foreach ($matches[0] as $k => $value) {
                $newKey = '<meta name="twig_tag" content="' . md5($k . '-' . $value) . '">';
                $reKey[$newKey] = $value;
                $html = str_replace($value, $newKey, $html, $limit);
            }
        }


        $p = '/<\?php .*\?>|{#.*#}|{%.*%}|{{[\w\s]*".*".*}}|{{[\w\s]*\'.*\'.*}}|{{.*}}/iU';

        preg_match_all($p, $html, $matches);

        if (count($matches)) {

            foreach ($matches[0] as $k => $value) {
                $newKey = '!--amp--' . md5($k . '-' . $value) . '--amp--';
                $reKey[$newKey] = $value;
                $html = str_replace($value, $newKey, $html, $limit);
            }
        }

        $p = '/<.* on="(.*)".*>|<.* on=\'(.*)\'.*>|<.* \[[\w]*\]="(.*)".*>|<.* \[[\w]*\]=\'(.*)\'.*>/iU';

        preg_match_all($p, $html, $matches);

        if (count($matches)) {

            foreach ($matches as $k => $value) {
                if ($k == 0) {
                    continue;
                }
                foreach ($value as $key => $data) {
                    if (!$data) {
                        continue;
                    }
                    $newKey = '!--amp--' . md5($k . '-' . $key . '-' . $data) . '--amp--';
                    $reKey[$newKey] = $data;
                    $html = str_replace($data, $newKey, $html, $limit);
                }

            }
        }

        $reKey = array_reverse($reKey);

        return [$html, $reKey];
    }

    /**
     * @param $html
     * @param array $reKey
     * @return mixed
     */
    public function toDecodeTiwg($html, array $reKey)
    {
        $limit = 1;
        foreach ($reKey as $key => $value) {
            $html = str_replace($key, $value, $html, $limit);
        }

        return $html;
    }

    public function checkTiwg($html)
    {
        try {
            $temporaryLoader = new ArrayLoader(['' => $html]);
            $this->twig->setLoader($temporaryLoader);
            $nodeTree = $this->twig->parse($this->twig->tokenize(new Source($html, '')));
            $this->twig->compile($nodeTree);
        } catch (Error $e) {
            return 'amp optimizer twig error: ' . $e->getMessage();
        }

        return false;
    }
}