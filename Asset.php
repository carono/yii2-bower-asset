<?php


namespace carono\yii2bower;

use yii\helpers\ArrayHelper;
use yii\helpers\StringHelper;
use yii\helpers\Inflector;
use yii\helpers\VarDumper;
use yii\web\AssetBundle;

class Asset extends AssetBundle
{
    public $packageNamespace = 'app\runtime\bower';

    public $packages = [];

    public function init()
    {
        $timestamp = $this->getFileTimestamp(self::getClassFile(get_class($this)));
        foreach ($this->packages as $idx => $value) {
            $package = is_numeric($idx) ? $value : $idx;
            $files = is_numeric($idx) ? [] : $value;
            if (!$this->assetExists($package) || $this->needRewrite($package)) {
                self::createAssetClass($package, $this->packageNamespace, $files);
                touch(self::getPackageFile($package, $this->packageNamespace), $timestamp);
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

    /**
     * @param $package
     * @param $namespace
     * @param array $files
     */
    public static function createAssetClass($package, $namespace, $files = [])
    {
        $dir = \Yii::getAlias("@bower/$package");
        $bowerJson = "$dir/bower.json";
        if (file_exists($bowerJson)) {
            $json = json_decode(file_get_contents($bowerJson), true);
            $main = ArrayHelper::getValue($json, 'main', []);
        } else {
            $main = [];
        }
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
    public \$sourcePath = '@vendor/bower/$package';
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
        $file = $dir . '\\' . $class . '.php';
        file_put_contents($file, $template);
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