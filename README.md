[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/carono/yii2-bower-asset/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/carono/yii2-bower-asset/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/carono/yii2-bower-asset/v/stable)](https://packagist.org/packages/carono/yii2-bower-asset)
[![Total Downloads](https://poser.pugx.org/carono/yii2-bower-asset/downloads)](https://packagist.org/packages/carono/yii2-bower-asset)
[![License](https://poser.pugx.org/carono/yii2-bower-asset/license)](https://packagist.org/packages/carono/yii2-bower-asset)

# Для чего
Данный пакет используется для быстрого подключения стилей и скриптов из bower пакетов. Файлы подключаются автоматически,
так же можно и указать вручную. 
# Как подключить
`composer require carono/yii2-bower-asset`

# Как использовать

Наследуем новый бандл от класса `carono\yii2bower\Asset`, в `$packages` перечисляем все подключенные в проекте бовер пакеты.

```
<?php


namespace app\assets;

use carono\yii2bower\Asset;

class BowerAsset extends Asset
{
    public $packages = [
        'jquery.inputmask', // Указываем имя пакета, скрипты подключаются автоматически
        'fontawesome' => [
            'sourcePath' => 'web-fonts-with-css', // Указываем папку внутри пакета
            'css/fontawesome-all.css' // Подключаем стиль вручную
        ],
    ];
}
```

Стили и скрипты автоматически подключаются из секции `main` в описании пакета (bower.json)

# Что происходит
При инициализации бандла, просматривается каждый указанный пакет.  
Из секции main пакету берутся ссылки на скрипты и стили.  
После этого формируется новый класс `app\runtime\bower\Package` и подключается как depends.

