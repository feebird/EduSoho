<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Topxia\Service\Common\ServiceKernel;

class AppKernel extends Kernel
{
    protected $plugins;

    public function registerBundles ()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            new Symfony\Bundle\MonologBundle\MonologBundle(),
            new Symfony\Bundle\SwiftmailerBundle\SwiftmailerBundle(),
            new Symfony\Bundle\AsseticBundle\AsseticBundle(),
            new Sensio\Bundle\FrameworkExtraBundle\SensioFrameworkExtraBundle(),
            new Doctrine\Bundle\DoctrineBundle\DoctrineBundle(),
            new Topxia\WebBundle\TopxiaWebBundle(),
            new Topxia\AdminBundle\TopxiaAdminBundle(),
            new Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle(),
        );

        $pluginMetaFilepath = $this->getRootDir() . '/data/plugin_installed.php';
        $pluginRootDir = $this->getRootDir() . '/../plugins';

        if (file_exists($pluginMetaFilepath)) {
            $pluginMeta = include_once($pluginMetaFilepath);
            $this->plugins = $pluginMeta['installed'];

            if (is_array($pluginMeta)) {
                foreach ($pluginMeta['installed'] as $code) {
                    $code = ucfirst($code);
                    $bundleName = "{$code}\\{$code}Bundle\\{$code}Bundle";
                    $bundles[] = new $bundleName();
                }
            }
        }

        $bundles[] = new Custom\WebBundle\CustomWebBundle();
        $bundles[] = new Custom\AdminBundle\CustomAdminBundle();
            
        if (in_array($this->getEnvironment(), array('dev' , 'test'))) {
            $bundles[] = new Symfony\Bundle\WebProfilerBundle\WebProfilerBundle();
            $bundles[] = new Sensio\Bundle\DistributionBundle\SensioDistributionBundle();
            $bundles[] = new Sensio\Bundle\GeneratorBundle\SensioGeneratorBundle();
        }

        return $bundles;
    }

    public function init ()
    {
        date_default_timezone_set('Asia/Shanghai');
        parent::init();
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load(__DIR__.'/config/config_'.$this->getEnvironment().'.yml');
    }

    public function getPlugins()
    {
        return $this->plugins;
    }
}
