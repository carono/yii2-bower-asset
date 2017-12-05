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

    protected static $_installedPackages = [];

    public function init()
    {
        $timestamp = $this->getFileTimestamp(self::getClassFile(get_class($this)));
        self::loadPackages();
        foreach ($this->packages as $idx => $value) {
            $package = is_numeric($idx) ? $value : $idx;
            $files = is_numeric($idx) ? [] : $value;
            if (!self::packageInstalled($package) && !self::packageAsFolder($package)) {
                continue;
            }
            if (!$this->assetExists($package) || $this->needRewrite($package)) {
                if ($classFile = self::createAssetClass($package, $this->packageNamespace, $files)) {
                    touch($classFile, $timestamp);
                }
            };
            $this->depends[] = self::getPackageClass($package, $this->packageNamespace);
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
        return $namespace . '\\' . self::formClassNameFromPackage($package);
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
        return self::getClassFile(self::getPackageClass($package, $namespace));
    }

    public static function packageAsFolder($package)
    {
        return is_dir(\Yii::getAlias("@bower/$package"));
    }

    public static function packageInstalled($package)
    {
        if (!self::$_installedPackages) {
            self::loadPackages();
        }
        return isset(self::$_installedPackages[$package]);
    }

    public static function loadPackages()
    {
        self::$_installedPackages = [];
        foreach (glob(\Yii::getAlias("@bower/**/bower.json"), GLOB_BRACE) as $file) {
            $json = json_decode(file_get_contents($file), true);
            if (($name = ArrayHelper::getValue($json, 'name'))) {
                self::$_installedPackages[$name] = $file;
            }
        }
    }

    public static function getPackageJson($package)
    {
        if (isset(self::$_installedPackages[$package])) {
            $file = self::$_installedPackages[$package];
            return json_decode(file_get_contents($file), true);
        } elseif (self::packageAsFolder($package)) {
            return [];
        } else {
            return null;
        }
    }

    public static function getPackageDir($package)
    {
        if (isset(self::$_installedPackages[$package])) {
            $file = self::$_installedPackages[$package];
            return basename(dirname($file));
        } elseif (self::packageAsFolder($package)) {
            return $package;
        } else {
            return null;
        }
    }

    /**
     * @param $package
     * @param $namespace
     * @param array $files
     * @return mixed|string
     */
    public static function createAssetClass($package, $namespace, $files = [])
    {
        $bowerJson = self::getPackageJson($package);

        if (is_null($bowerJson)) {
            \Yii::warning("Bower $package package not found");
            return false;
        }

        $main = ArrayHelper::getValue($bowerJson, 'main');

        $dir = self::getPackageDir($package);
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
        $class = self::formClassNameFromPackage($package);
        $js = VarDumper::export($js);
        $css = VarDumper::export($css);
        $template = <<<PHP
<?php

namespace $namespace;


use yii\web\AssetBundle;
use yii\web\View;

class $class extends AssetBundle
{
    public \$sourcePath = '@bower/$dir';
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
            mkdir($dir, 0777, true);
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
        return class_exists(self::getPackageClass($package, $this->packageNamespace));
    }

    /**
     * @param $package
     * @return bool
     */
    protected function needRewrite($package)
    {
        $timestamp = $this->getFileTimestamp(self::getPackageFile($package, $this->packageNamespace));
        if ($timestamp) {
            $assetTimestamp = $this->getFileTimestamp(self::getClassFile(get_class($this)));
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
            $timestamp = intval($timestamp / 100) * 100;
        }
        return $timestamp;
    }
}