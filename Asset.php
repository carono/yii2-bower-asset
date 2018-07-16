<?php


namespace carono\yii2bower;

use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\StringHelper;
use yii\helpers\Inflector;
use yii\helpers\VarDumper;
use yii\web\AssetBundle;

class Asset extends AssetBundle
{
    public $packageNamespace = 'app\runtime\bower';
    public $packages = [];
    public $alias = '@bower';
    public $config = 'bower.json';
    protected static $_installedPackages = [];

    public function init()
    {
        $timestamp = $this->getFileTimestamp(static::getClassFile(get_class($this)));
        static::loadPackages($this->alias, $this->config);
        foreach ($this->packages as $idx => $value) {
            $package = is_numeric($idx) ? $value : $idx;
            $files = is_numeric($idx) ? [] : $value;
            if (!static::packageInstalled($package, $this->alias) && !static::packageAsFolder($package, $this->alias)) {
                continue;
            }
            if (!$this->assetExists($package) || $this->needRewrite($package)) {
                if ($classFile = static::createAssetClass($package, $this->packageNamespace, $this->alias, $files)) {
                    touch($classFile, $timestamp);
                }
            };
            $this->depends[] = static::getPackageClass($package, $this->packageNamespace);
        }
        parent::init();
    }

    /**
     * @param $package
     * @param $namespace
     * @return string
     */
    public static function getPackageClass($package, $namespace)
    {
        return $namespace . '\\' . static::formClassNameFromPackage($package);
    }

    /**
     * @param $package
     * @return string
     */
    public static function formClassNameFromPackage($package)
    {
        return Inflector::camelize($package);
    }

    /**
     * @param $package
     * @param $namespace
     * @return string
     */
    protected static function getPackageFile($package, $namespace)
    {
        return static::getClassFile(static::getPackageClass($package, $namespace));
    }

    public static function packageAsFolder($package, $alias)
    {
        return is_dir(\Yii::getAlias("$alias/$package"));
    }

    public static function packageInstalled($package, $alias, $config = 'bower.json')
    {
        if (!static::$_installedPackages) {
            static::loadPackages($alias, $config);
        }
        return isset(static::$_installedPackages[$package]);
    }

    public static function loadPackages($alias, $config)
    {
        static::$_installedPackages = [];
        foreach (glob(\Yii::getAlias("$alias/**/$config"), GLOB_BRACE) as $file) {
            $json = json_decode(file_get_contents($file), true);
            if (($name = ArrayHelper::getValue($json, 'name'))) {
                static::$_installedPackages[$name] = $file;
            }
        }
    }

    public static function getPackageJson($package, $alias)
    {
        if (isset(static::$_installedPackages[$package])) {
            $file = static::$_installedPackages[$package];
            return json_decode(file_get_contents($file), true);
        }

        if (static::packageAsFolder($package, $alias)) {
            return [];
        }

        return null;
    }

    public static function getPackageDir($package, $alias)
    {
        if (isset(static::$_installedPackages[$package])) {
            $file = static::$_installedPackages[$package];
            return basename(dirname($file));
        }

        if (static::packageAsFolder($package, $alias)) {
            return $package;
        }

        return null;
    }

    /**
     * @param $package
     * @param $namespace
     * @param $alias
     * @param array $files
     * @return mixed|string
     */
    public static function createAssetClass($package, $namespace, $alias, $files = [])
    {
        $bowerJson = static::getPackageJson($package, $alias);

        if (is_null($bowerJson)) {
            \Yii::warning("Bower $package package not found");
            return false;
        }

        $main = ArrayHelper::getValue($bowerJson, 'main');

        $dir = static::getPackageDir($package, $alias);
        $js = [];
        $css = [];
        $main = $files ? $files : (is_array($main) ? $main : [$main]);
        foreach ($main as $file) {
            if (StringHelper::endsWith($file, '.css')) {
                $css[] = ltrim($file, "./\\");
            }
            if (StringHelper::endsWith($file, '.js')) {
                $js[] = ltrim($file, "./\\");
            }
        }
        $class = static::formClassNameFromPackage($package);
        $js = VarDumper::export($js);
        $css = VarDumper::export($css);
        $template = <<<PHP
<?php

namespace $namespace;


use yii\web\AssetBundle;
use yii\web\View;

class $class extends AssetBundle
{
    public \$sourcePath = '$alias/$dir';
    public \$baseUrl = '@web';
    public \$jsOptions = ['position' => View::POS_END];
    public \$js
        = $js;
    public \$css
        = $css;
}
PHP;
        $dir = \Yii::getAlias('@' . str_replace('\\', '/', ltrim($namespace, '\\')));
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
        }
        $file = $dir . DIRECTORY_SEPARATOR . $class . '.php';
        file_put_contents($file, $template);
        return $file;
    }

    /**
     * @param $package
     * @return bool
     */
    protected function assetExists($package)
    {
        return class_exists(static::getPackageClass($package, $this->packageNamespace));
    }

    /**
     * @param $package
     * @return bool
     */
    protected function needRewrite($package)
    {
        $timestamp = $this->getFileTimestamp(static::getPackageFile($package, $this->packageNamespace));
        if ($timestamp) {
            $assetTimestamp = $this->getFileTimestamp(static::getClassFile(get_class($this)));
            return $timestamp != $assetTimestamp;
        }
        return false;
    }

    /**
     * @param $class
     * @return string
     */
    protected static function getClassFile($class)
    {
        return \Yii::getAlias('@' . ltrim(str_replace('\\', '/', $class), '/')) . '.php';
    }

    /**
     * @param $file
     * @return bool|int
     */
    protected function getFileTimestamp($file)
    {
        if ($timestamp = @filemtime($file)) {
            $timestamp = (int)$timestamp / 100 * 100;
        }
        return $timestamp;
    }
}