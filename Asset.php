<?php


namespace carono\yii2bower;

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
        foreach ($this->packages as $idx => $value) {
            $package = is_numeric($idx) ? $value : $idx;
            $files = is_numeric($idx) ? [] : $value;
            if (!$this->assetExists($package)) {
                self::createAssetClass($package, $this->packageNamespace, $files);
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
     * @param array $files
     */
    public static function createAssetClass($package, $namespace, $files = [])
    {
        $bowerJson = \Yii::getAlias('@bower') . '/' . $package . '/bower.json';
        $json = json_decode(file_get_contents($bowerJson), true);
        $js = [];
        $css = [];
        $main = $files ? $files : (is_array($json['main']) ? $json['main'] : [$json['main']]);
        foreach ($main as $file) {
            if (StringHelper::endsWith($file, '.css')) {
                $css[] = $file;
            }
            if (StringHelper::endsWith($file, '.js')) {
                $js[] = $file;
            }
        }
        $class = self::formClassNameFromPackage($package);
        $js = VarDumper::dumpAsString($js);
        $css = VarDumper::dumpAsString($css);
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
}