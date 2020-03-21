# mangatranslation
PHP manga automatic translator (Works with Google Cloud Platform)

CPU Only, no cuda, no opencv, no machine learning

## Warning
I am not a developper, so this project code might be really ugly. :)

## Installation

create a composer.json file in your project, and add:

```json
{
    "minimum-stability": "dev",
    "require": {
         "hcharbonnier/mangatranslation": ">=0.0.1"
    }
}
```

then install depedencies:
```sh
composer install
```
## Requirement
* php-7.4 (not tested with php<7.4 but could work)
* php-gd
* bcmath
* A google cloud platform project configured
 (https://cloud.google.com/dataproc/docs/guides/setup-project)
## Example
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use mangatranslation\MangaImage;
use mangatranslation\TextBlock;

$test = new MangaImage($argv[1]);
$test->load();
```
Then run:
```sh
$ export GOOGLE_APPLICATION_CREDENTIALS=PATH_TO_GOOGLE_PROJECT.json
$ php example.php image.jpeg
```
