<?php


namespace Plugin\Amp4\Controller\Admin;

use Eccube\Controller\AbstractController;
use Eccube\Util\StringUtil;
use Plugin\Amp4\Form\Type\Admin\ConfigType;
use Plugin\Amp4\Repository\ConfigRepository;
use Plugin\Amp4\Service\HttpSend;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\Request;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * Class ConfigController
 */
class ConfigController extends AbstractController
{

    /**
     * @var ConfigRepository
     */
    protected $configRepository;

    /**
     * @var HttpSend
     */
    protected $httpSend;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    public function __construct(
        ConfigRepository $configRepository,
        HttpSend $httpSend,
        Filesystem $filesystem
    )
    {
        $this->configRepository = $configRepository;
        $this->httpSend = $httpSend;
        $this->filesystem = $filesystem;
    }

    /**
     * @param Request $request
     * @Route("/%eccube_admin_route%/amp4/amp_to_optimize", name="amp4_to_optimize", methods={"POST"})
     * @return mixed
     * @throws \Plugin\Amp4\Service\HttpSendException
     */
    public function ampToOptimize(Request $request)
    {
        $data = json_decode($request->getContent(), 1);

        $templateData = [];
        $keyData = [];
        foreach ($data as $path) {
            list($templateData[], $keyData[]) = $this->httpSend->toEncodeTwig(file_get_contents($path));
        }

        $reData = $this->httpSend->sendData($templateData);

        $dataKey = array_keys($data);
        foreach ($reData as $key => $code) {
            $savePath = $dataKey[$key];
            $code = StringUtil::convertLineFeed($this->httpSend->toDecodeTiwg($code, $keyData[$key]));

            $err = $this->httpSend->checkTiwg($code);
            if ($err) {
                return $this->json(['status' => 'error', 'mess' => $err], 500);
            }

            $this->filesystem->dumpFile($savePath, $code);
        }

        return $this->json(['status' => 'ok'], 200);
    }

    /**
     * @param Request $request
     * @Route("/%eccube_admin_route%/amp4/amp_change_config_optimize", name="amp_change_config_optimize", methods={"POST"})
     * @return mixed
     */
    public function changeConfigOptimize(Request $request)
    {
        $status = $request->get('status');

        $config = $this->configRepository->get();

        if ($status == 'ok') {

            $config->setOptimize(true);
            $this->addSuccess('amp4.admin.save.success', 'admin');
        } else {
            $config->setOptimize(false);
            $this->addError('amp4.admin.save.optimize.error', 'admin');
        }

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        return $this->json(['status' => 'ok'], 200);
    }

    /**
     * @Route("/%eccube_admin_route%/amp4/config", name="amp4_admin_config")
     * @Template("@Amp4/admin/config.twig")
     */
    public function index(Request $request)
    {
        $Config = $this->configRepository->get();
        $oldIsOptimize = $Config->isOptimize();

        $form = $this->createForm(ConfigType::class, $Config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            /** @var $Config \Plugin\Amp4\Entity\Config */
            $Config = $form->getData();

            $Config->setOptimize($Config->isOptimize());
            $Config->setCanonical($Config->isCanonical());

            $isOptimize = false;

            if ($Config->isOptimize() && !$oldIsOptimize) {
                $Config->setOptimize(false);
                $isOptimize = true;
            }

            $this->entityManager->persist($Config);
            $this->entityManager->flush($Config);

            if ($isOptimize) {
                return $this->redirectToRoute('amp4_admin_config', ['optimize' => 'run']);
            } else {
                $this->addSuccess('amp4.admin.save.success', 'admin');
                return $this->redirectToRoute('amp4_admin_config');
            }
        }

        $files = [];
        if ($request->get('optimize')) {

            $existsFiles = [];

            $toDir = sprintf("%s/amp-optimizer/",
                $this->eccubeConfig->get('eccube_theme_app_dir'));

            $dir = sprintf("%s/amp/",
                $this->eccubeConfig->get('eccube_theme_app_dir'));
            if (is_dir($dir)) {
                $finder = new Finder();
                $finder->files()->in($dir);

                foreach ($finder as $file) {
                    $existsFiles[] = $file->getRelativePathname();
                    $files[$toDir . $file->getRelativePathname()] = $file->getRealPath();
                }
            }


            $finder = new Finder();
            $finder->files()->in(sprintf("%s/../../Resource/template/amp/",
                __DIR__));

            foreach ($finder as $file) {

                if (in_array($file->getRelativePathname(), $existsFiles)) {
                    continue;
                }
                $existsFiles[] = $file->getRelativePathname();
                $files[$toDir . $file->getRelativePathname()] = $file->getRealPath();
            }

            $dir = sprintf("%s/amp/",
                $this->eccubeConfig->get('eccube_theme_user_data_dir'));

            if (is_dir($dir)) {
                $finder = new Finder();
                $finder->files()->in($dir);

                $toDir = sprintf("%s/amp-optimizer/",
                    $this->eccubeConfig->get('eccube_theme_user_data_dir'));

                foreach ($finder as $file) {
                    $existsFiles[] = $file->getRelativePathname();
                    $files[$toDir . $file->getRelativePathname()] = $file->getRealPath();
                }
            }
        }

        if (count($files)) {
            $tmp = $files;
            $files = [];
            $i = 0;
            foreach (array_keys($tmp) as $key => $file) {
                if ($key != 0 && $key % 3 == 0) {
                    $i++;
                }
                $files[$i][$file] = $tmp[$file];
            }
        }

        return [
            'form' => $form->createView(),
            'optimize' => $request->get('optimize') ? true : false,
            'files' => $files,
        ];
    }

    /**
     * @param $fileName
     * @param $files
     */
    protected function addToFile($fileName, &$files)
    {
        $ampTemplatePath = sprintf("%s/amp/%s.twig",
            $this->eccubeConfig->get('eccube_theme_app_dir'),
            $fileName);
        $toAmpTemplatePath = sprintf('%s/amp-optimizer/%s.twig',
            $this->eccubeConfig->get('eccube_theme_app_dir'),
            $fileName);
        if (file_exists($ampTemplatePath)) {
            $files[$toAmpTemplatePath] = $ampTemplatePath;
        } else {
            $ampTemplatePath = sprintf('%s/../../Resource/template/amp/%s.twig',
                __DIR__,
                $fileName);
            if (file_exists($ampTemplatePath)) {
                $files[$toAmpTemplatePath] = $ampTemplatePath;
            }
        }
    }

}
