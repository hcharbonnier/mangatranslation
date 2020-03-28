# mangatranslation
PHP manga automatic translator (Works with Google Cloud Platform)

CPU Only, no cuda, no opencv, no machine learning

## Warning
I am not a developper, so this project code might be really ugly. :)

## Translation examples

### Source
![Source](images/0004.jpg "Source")

### Translated
![Translated](images/0004-translated.jpg "Translated")

## Workflow summary
* Open image (jpeg, png, gif or bmp)
* Detect textboxes
* Detect font size
* OCR Text in textboxes
* Remove old text from textboxes
* Use Google API to translate
* Adapt translation font size to fit in textboxes
* write translation in the corresponding textboxes

## Specials features
* Support external denoiser (ie: Waifu2x) to improve OCR performance
* Support tilted textboxes

## Known Issues
* Let me know

## Installation

create a composer.json file in your project, and add:

```json
{
    "minimum-stability": "dev",
    "require": {
         "hcharbonnier/mangatranslation": ">=0.2.0"
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
require_once __DIR__ . '/vendor/autoload.php'; // Autoload files using Composer autoload

use mangatranslation\MangaImage;
use mangatranslation\TextBlock;

$test = new MangaImage($argv[1],$argv[2] );

//Optional
$test->external_denoiser (
    'cmd.exe /mnt/c/Users/Hugues/Downloads/waifu2x/waifu2x-converter-cpp.exe --force-OpenCL --model-dir \'C:\Users\Hugues\Downloads\waifu2x\models_rgb\' --scale-ratio 2 --noise-level 1 -m noise-scale -i _DENOISERINPUTFILE_ -o _DENOISEROUTPUTFILE_'
);
$test->load();

```
Then run:
```sh
$ export GOOGLE_APPLICATION_CREDENTIALS=PATH_TO_GOOGLE_PROJECT.json
$ php example.php image.jpg translated.jpg
```
