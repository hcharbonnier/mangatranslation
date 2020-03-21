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
  $im = imagecreatetruecolor(800, 800);
  $white = imagecolorallocate($im, 255, 255, 255);
  $black = imagecolorallocate($im, 0, 0, 0);
  imagefilledrectangle($im, 0, 0, 799, 799, $white);
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

// Find parameters to fit text in image
function format_text($width, $height, $angle, $font, $font_size, $text,$border=0) 
{
    if (trim($text) == "" ) {
      $resultat['font']=$font;
      $resultat['text']=$text;
      $resultat['size']=$font_size;
      $resultat['width_px']=0;
      $resultat['height_px']=0;
      $resultat['top']=0;
      $resultat['left']=0;
      return $resultat;
    }

    $width=max($width-(2*$border), 2*$border+8);
    $height=max($height-(2*$border), 2*$border+8);
    $dim["height"]=$height;

    if ($font_size <=5)
      $font_size=6;
      
    while ((( $dim["height"]  >= $height )||( $dim["width"]  >= $width )) && ($font_size > 5)) {
        $image_heigth=$height;
        $image_width=$width;
        $size=$font_size;
        $angle=$angle;
        $x=$border;
        $y=$border;

        $res=[];
        $res[0]="";
        $max_line_length=($image_width);
        $line_length=0;

        $arr=explode(' ', $text);
        $j=0;
        //for each word
        for ($i=0; isset($arr[$i]);$i++){
            //we calculate word dimensions
            $arr_stat=calculateTextBox($size, $angle, $font, $arr[$i].'#');

            //if  word +current ligne length larger than line, we change line
            if ($line_length +$arr_stat['width'] > $max_line_length){
                $res[$j]=trim($res[$j]);
                $j++;
                $res[$j]="";
                $line_length=0;
            }

            $res[$j]=$res[$j].$arr[$i].' ';
            $line_length=$line_length+$arr_stat['width'];
        }

        $text_res=implode("\n", $res);
        $dim=(calculateTextBox($size, $angle, $font, $text_res));

        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $grey = imagecolorallocate($image, 128, 128, 128);
        $black = imagecolorallocate($image, 0, 0, 0);
        $color=$black;
        imagefilledrectangle($image, 0, 0, $height-1, $width-1, $white);
        imagettftext (  $image , $size , $angle , $x ,  $y ,  $black , $font ,  $text_res );

        imagejpeg($image,"test.jpg");
        $resultat['font']=$font;
        $resultat['text']=$text_res;
        $resultat['size']=$size;
        $resultat['top']=$dim['top'];
        $resultat['left']=$dim['left'];
        $resultat['width_px']=$dim['width'];
        $resultat['height_px']=$dim['height'];
        $font_size--;
    }
  return $resultat;
}
