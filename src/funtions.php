<?php
function imagecreatefromany($filepath) {
    $type = exif_imagetype($filepath); // [] if you don't have exif you could use getImageSize()
    $allowedTypes = array(
        1,  // [] gif
        2,  // [] jpg
        3,  // [] png
        6   // [] bmp
    );
    if (!in_array($type, $allowedTypes)) {
        return false;
    }
    switch ($type) {
        case 1 :
            $im = imageCreateFromGif($filepath);
        break;
        case 2 :
            $im = imageCreateFromJpeg($filepath);
        break;
        case 3 :
            $im = imageCreateFromPng($filepath);
        break;
        case 6 :
            $im = imageCreateFromBmp($filepath);
        break;
    }   
    return $im; 
}



function imagewrite($image,$filepath) {
    $tmp=explode('.',$filepath);
    $extension=end($tmp);

    switch ($extension) {
        case 'jpg' :
            imagejpeg($image,$filepath);
        break;
        case 'gif' :
            imagegif($image,$filepath);
        break;
        case 'png' :
            imagepng($image,$filepath);
        break;
        case 'bmp' :
            imagebmp($image,$filepath);
        break;
    }
}

function cloneImg($img){
    //get dimensions
    $w = imagesx($img);
    $h = imagesy($img);
     //copy process
    $copy = imagecreatetruecolor($w, $h);
    imagecopy($copy, $img, 0, 0, 0, 0, $w, $h);

    return $copy;

  }
?>