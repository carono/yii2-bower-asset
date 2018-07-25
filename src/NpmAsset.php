<?php


namespace carono\yii2bower;


class NpmAsset extends Asset
{
    public $config = 'package.json';
    public $alias = '@npm';
}