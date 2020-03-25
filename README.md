# mangatranslation
PHP manga automatic translator (Works with Google Cloud Platform)

CPU Only, no cuda, no opencv, no machine learning

## Warning
I am not a developper, so this project code might be really ugly. :)

## Translation examples

** Source **

![Source](images/0004.jpg "Source")

** Translated **

![Translated](images/004-translated.jpg "Translated")


## Workflow summary
* Open Jpeg image
* Detect textboxes
* Detect font size
* OCR Text in textboxes
* Remove old text from textboxes
* Use Google API to translate
* Adapt translation font size to fit in textboxes
write translation in the corresponding textboxes

## Specials features
* Support external denoiser (ie: Waifu2x) to improve OCR performance
* Support tilted textboxes

## Known Limitations
* Only work with jpeg files for now.
* Support for tilted textboxes can be improved
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

## I use php in WSL, so there is a mix Windows and Linux path here, so don't be surprised :D
$test->external_denoiser (
    'cmd.exe /mnt/c/Users/Hugues/Downloads/waifu2x/waifu2x-converter-cpp.exe --force-OpenCL --model-dir \'C:\Users\Hugues\Downloads\waifu2x\models_rgb\' --scale-ratio 2 --noise-level 3 -m noise-scale -i _DENOISERINPUTFILE_ -o _DENOISEROUTPUTFILE_'
);

$test->load();
```
Then run:
```sh
$ export GOOGLE_APPLICATION_CREDENTIALS=PATH_TO_GOOGLE_PROJECT.json
$ php example.php image.jpeg
```
