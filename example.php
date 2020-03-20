<?php
require_once __DIR__ . '/vendor/autoload.php'; // Autoload files using Composer autoload

use mangatranslation\MangaImage;
use mangatranslation\TextBlock;

$test = new MangaImage($argv[1]);
$test->load();
//$test->dump();
