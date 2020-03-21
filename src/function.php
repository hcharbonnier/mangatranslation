<?php
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Translate\TranslateClient;

//translate text
function translate ($text,$targetLanguage){
    
  $translate = new TranslateClient();
  $result = $translate->translate($text, [
      'target' => $targetLanguage,
  ]);

  return($result["text"]);
}
function calculateTextBoxtmp($font_size, $font_angle, $font_file, $text) {
  $im = imagecreatetruecolor(8000, 8000);
  $white = imagecolorallocate($im, 255, 255, 255);
  $black = imagecolorallocate($im, 0, 0, 0);
  imagefilledrectangle($im, 0, 0, 7999, 7999, $white);
  imagettftext($im, $font_size, $font_angle, 30, 770, $black, $font_file, $text);
  $im=imagecropauto($im,IMG_CROP_WHITE);
  $width=imagesx($im);
  $height=imagesy($im); 
  return array(
  "width"  => $width,
  "height" => $height );
}

//Calculate dimension of image generated from a string
function calculateTextBox($font_size, $font_angle, $font_file, $text) {
  $box   = imagettfbbox($font_size, $font_angle, $font_file, $text);
  if( !$box )
    return false;
  $min_x = min( array($box[0], $box[2], $box[4], $box[6]) );
  $max_x = max( array($box[0], $box[2], $box[4], $box[6]) );
  $min_y = min( array($box[1], $box[3], $box[5], $box[7]) );
  $max_y = max( array($box[1], $box[3], $box[5], $box[7]) );
  $width  = ( $max_x - $min_x );
  $height = ( $max_y - $min_y );
  $left   = abs( $min_x ) + $width;
  $top    = abs( $min_y ) + $height;
  // to calculate the exact bounding box i write the text in a large image
  $img     = @imagecreatetruecolor( $width << 2, $height << 2 );
  $white   =  imagecolorallocate( $img, 255, 255, 255 );
  $black   =  imagecolorallocate( $img, 0, 0, 0 );
  imagefilledrectangle($img, 0, 0, imagesx($img), imagesy($img), $black);
  // for sure the text is completely in the image!
  imagettftext( $img, $font_size,
                $font_angle, $left, $top,
                $white, $font_file, $text);
  // start scanning (0=> black => empty)
  $rleft  = $w4 = $width<<2;
  $rright = 0;
  $rbottom   = 0;
  $rtop = $h4 = $height<<2;
  for( $x = 0; $x < $w4; $x++ )
    for( $y = 0; $y < $h4; $y++ )
      if( imagecolorat( $img, $x, $y ) ){
        $rleft   = min( $rleft, $x );
        $rright  = max( $rright, $x );
        $rtop    = min( $rtop, $y );
        $rbottom = max( $rbottom, $y );
      }
  // destroy img and serve the result
  imagedestroy( $img );
  return array( "left"   => $left - $rleft,
                "top"    => $top  - $rtop,
                "width"  => $rright - $rleft + 1,
                "height" => $rbottom - $rtop + 1 );
}