# mangatranslation
PHP manga automatic translator (Works with Google Cloud Platform)

## Warning
I am not a developper, so this project code might be really ugly. :)

## Example
```php
<?php
require_once __DIR__ . '/vendor/autoload.php'; // Autoload files using Composer autoload

use mangatranslation\MangaImage;
use mangatranslation\TextBlock;

$test = new MangaImage($argv[1]);
$test->load();
//$test->dump();
```
## Usage
```sh
$ export GOOGLE_APPLICATION_CREDENTIALS=PATH_TO_GOOGLE_PROJECT.json
$ php example.php image.jpeg
```
