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

// OCR text from image path
function detect_text($path)
{
  $resultats=array();
  $j=0;
  $imageAnnotator = new ImageAnnotatorClient();

  # annotate the image
  $image = file_get_contents($path);
  $response = $imageAnnotator->textDetection($image);
  $texts = $response->getTextAnnotations();
  $i=0;
  foreach ($texts as $text) {
      if ($i == 0) {
        $string=$text->getDescription();
        foreach ($text->getBoundingPoly()->getVertices() as $vertex) {
          $x=$vertex->getX();
        break;
        }
        $resultats[$j][0]=$x;
        $resultats[$j][1]=$string;
      }
      $j++;
      $i++;
  }
  $imageAnnotator->close();
  sort($resultats);
  $res="";
  for ($k=0 ; isset($resultats[$k]) ; $k++){
    $res.=' '.$resultats[$k][1];
  }
  $order=array("\r\n", "\n", "\r");
  $res=trim(str_replace($order, ' ', $res));
  $res=str_replace('  ', ' ', $res);
  return trim($res);
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
    echo "text:$text\n";
    $width=max($width-(2*$border), 2*$border+8);
    $height=max($height-(2*$border), 2*$border+8);
    $dim["height"]=$height;
    while (( $dim["height"]  >= $height ) && ($font_size > 5)) {
        $image = imagecreatetruecolor($width, $height);
        $ori=$image;
        $white = imagecolorallocate($image, 255, 255, 255);
        $grey = imagecolorallocate($image, 128, 128, 128);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $height-1, $width-1, $white);

        $image_heigth=$height;
        $image_width=$width;
        $size=$font_size;
        $angle=$angle;
        $x=$border;
        $y=$border;
        $color=$black;

        $res=[];
        $res[0]="";
        $max_line_length=($image_width);
        $line_length=0;

        $arr=explode(' ', $text);
        $j=0;
        for ($i=0; isset($arr[$i]);$i++){
            $arr_stat=calculateTextBox($size, $angle, $font, $arr[$i].'_');
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
        imagettftext (  $image , $size , $angle , $x ,  $y ,  $black , $font ,  $text_res );
        imagejpeg($image,"test.jpg");
        $resultat['font']=$font;
        $resultat['text']=$text_res;
        $resultat['size']=$size;
        $font_size--;
    }
  return $resultat;
}
