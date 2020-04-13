<?php 
namespace hcharbonnier\mangatranslation;
#require __DIR__ . '/vendor/autoload.php';

require_once(__DIR__."/funtions.php");
# imports the Google Cloud client library
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Translate\TranslateClient;

use ColorThief\ColorThief;

//  text_bloc: block text in a page
class TextBlock {
    public $image;
    public $mother_image;
    public $path;
    public $mother_name;
    public $mother_path;
    public $x1;
    public $y1;
    public $x2;
    public $y2;
    public $x3;
    public $y3;
    public $x4;
    public $y4;
    public $ordered=array();
    public $ori=array();
    public $nb_rotate=0;
    public $background_color_alt;
    public $ocr_text;
    public $ocr_paragraph;
    public $translated_text;
    public $formatted_text;
    public $font_size;
    public $text_angle=0;
    public $translation_width;
    public $translation_height;
    public $translation_top_offset;
    public $translation_left_offset;
    public $original_font_size;
    public $calculate_angle;
    
    public $font=__DIR__."/../fonts/animeace2_reg.ttf";
    
    function __construct($mother_path,$motherimage,$x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4,$calculate_angle) {
        $this->calculate_angle=$calculate_angle;
        $this->mother_image = cloneImg($motherimage);
        $this->mother_path= $mother_path;          
        $this->mother_name= basename($mother_path);
        
        $this->set_block($x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4);
        
        $this->dominant_color_alt();
    }

    function get_image(){
        if (@get_resource_type($this->image) != "gd")
          $this->image=imagecreatefromany($this->path);
        return $this->image;
      }

      function get_mother_image(){
        if (@get_resource_type($this->mother_image) != "gd")
          $this->mother_image=imagecreatefromany($this->mother_path);
        return $this->mother_image;
      }

    public function process(){
        
        $this->extract_bloc();
    //    $this->draw_boxes2(2,0);
       $this->expand_block_text();
    //    $this->draw_boxes2(3,0);
    }

    public function ocr(){
        $this->process();
        $this->detect_text();
        $this->find_font_size();
        $this->font_size=$this->original_font_size;
    }

    public function translate(){
        //Translate string
            $this->translated_text=$this->translate_string($this->ocr_text, "en");
            //Get best format for translated string (size fonts, etc..)
            $formatted_text=$this->format_text( $this->font, $this->font_size, $this->translated_text,11);
            $this->translation_width = $formatted_text['width_px'];
            $this->translation_height = $formatted_text['height_px'];
            $this->translation_top_offset= $formatted_text['top'];
            $this->translation_left_offset = $formatted_text['left'];
            $this->formatted_text=html_entity_decode($formatted_text['text'],ENT_QUOTES);
            $this->font_size=$formatted_text['size'];
    }
     
    public function get_block(){
        $res['x1']=$this->x1;
        $res['y1']=$this->y1;
        $res['x2']=$this->x2;
        $res['y2']=$this->y2;
        $res['x3']=$this->x3;
        $res['y3']=$this->y3;
        $res['x4']=$this->x4;
        $res['y4']=$this->y4;
        return $res;
    }

    public function get_translation(){
        return $this->translated_text;
    }

    public function get_ocr(){
        return $this->ocr_text;
    }

    public function get_image_path(){
        return $this->path;
    }

    public function set_translation($translation){
         $this->translated_text = $translation;
    }

    public function set_block($x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4){
        $this->x1 = $x1;
        $this->y1 = $y1;
        $this->x2 = $x2;
        $this->y2 = $y2;
        $this->x3 = $x3;
        $this->y3 = $y3;
        $this->x4 = $x4;
        $this->y4 = $y4;
        $this->ori['x1']=$x1;
        $this->ori['y1']=$y1;
        $this->ori['x2']=$x2;
        $this->ori['y2']=$y2;
        $this->ori['x3']=$x3;
        $this->ori['y3']=$y3;
        $this->ori['x4']=$x4;
        $this->ori['y4']=$y4;
        
        if ($this->calculate_angle)
          $this->calculate_text_angle();
        else {
          $this->text_angle=0;
          $this->ordered['x1']=$this->x1;
          $this->ordered['y1']=$this->y1;
          $this->ordered['x2']=$this->x2;
          $this->ordered['y2']=$this->y2;
          $this->ordered['x3']=$this->x3;
          $this->ordered['y3']=$this->y3;
          $this->ordered['x4']=$this->x4;
          $this->ordered['y4']=$this->y4;
        }
    }

    private function translate_string ($text,$targetLanguage="en"){
        if (!(isset($this->translated_text))) {
            $translate = new TranslateClient();
            $result = $translate->translate($text, [
            'target' => $targetLanguage,
            ]);
            return($result["text"]);
        } else {
            return($this->translated_text);
        }
    }  
    // Find parameters to fit text in image
    function format_text( $font, $font_size, $text,$border=0) 
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
        
        $imgx=max(imagesx($this->get_mother_image()),imagesy($this->get_mother_image()));
        $imgy=$imgx;
        $tmpimage=imagecreatetruecolor($imgx, $imgy);
        $white = imagecolorallocate($tmpimage, 255, 255, 255);
        $black = imagecolorallocate($tmpimage, 0, 0, 0);
        imagefilledrectangle($tmpimage, 0, 0, $imgx-1, $imgy-1, $black);

        $pol=array(
            $this->ordered['x1'],$this->ordered['y1'],
            $this->ordered['x2'],$this->ordered['y2'],
            $this->ordered['x3'],$this->ordered['y3'],
            $this->ordered['x4'],$this->ordered['y4'],
        );

        imagefilledpolygon($tmpimage, $pol, 4, $white);
        if ($this->text_angle != 0)
            $tmpimage=imagerotate( $tmpimage , 0- $this->text_angle ,  0 );

        $tmpimage=imagecropauto($tmpimage,IMG_CROP_SIDES);
        $width=max(imagesx($tmpimage)-(2*$border), 2*$border+8);
        $height=max(imagesy($tmpimage)-(2*$border), 2*$border+8);
                    
        if ($font_size <=6)
        $font_size=7;
        
        $dim["height"]  = $height;
        $dim["width"]  = $width;
        while (
            ((( $dim["height"]  >= $height )||( $dim["width"]  >= $width )) && ($font_size > 6)) ) {
                $image_heigth=$height;
                $image_width=$width;
                $size=$font_size;
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
                    $arr_stat=$this->calculateTextBox($size, 0, $font, $arr[$i].'#');
                    
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
                $dim=($this->calculateTextBox($size, 0, $font, $text_res));
                
                $image = imagecreatetruecolor($width, $height);
                $white = imagecolorallocate($image, 255, 255, 255);
                $grey = imagecolorallocate($image, 128, 128, 128);
                $black = imagecolorallocate($image, 0, 0, 0);
                $color=$black;
                imagefilledrectangle($image, 0, 0, $height-1, $width-1, $white);
                imagettftext (  $image , $size , 0 , $x ,  $y ,  $black , $font ,  $text_res );
                
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
        
        function detect_text()
        {
            ob_start(); // start a new output buffer
            imagejpeg($this->get_image(),NULL,100);
            $image = ob_get_contents();
            ob_end_clean(); // stop this output buffer
            $resultats=array();
            $j=0;
            $imageAnnotator = new ImageAnnotatorClient();
            
            # annotate the image
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
            $txt=trim(str_replace($order, ' ', $res));
            $paragraph=trim(str_replace($order, "\n", $res));
            $txt=str_replace('  ', ' ', $txt);
            $this->ocr_paragraph=$paragraph;
            $this->ocr_text=trim($txt);
        }
    
    function original_text_pixel_height(){
        $a=(($this->y3+$this->y4)/2)-(($this->y1+$this->y2)/2);
        $b=(($this->x3+$this->x4)/2)-(($this->x1+$this->x2)/2);
        $c=sqrt(pow($a,2)+pow($b,2));
        return $c;
    }
    
    function find_font_size($max_font_size=32){
        
        $nb_line=substr_count( $this->ocr_paragraph, "\n" )+1;
        $height_pixel=$this->original_text_pixel_height();
        $font_pixel_height=$height_pixel/$nb_line;
        $this->original_font_size=min(round($font_pixel_height/2.2),$max_font_size);

    }
    
    /* function dominant_color(){
        $dominantColors = ColorThief::getPalette($this->path,$colorCount=2);
        if ($dominantColors[0][0]+$dominantColors[0][1]+$dominantColors[0][2] > $dominantColors[1][0]+$dominantColors[1][1]+$dominantColors[1][2])
        $this->background_color=$dominantColors[0];
        else
        $this->background_color=$dominantColors[1];
    }*/
    
    function dominant_color_alt_ori(){
        $pix1=array(($this->x1 + $this->x2)/2, ($this->y1 + $this->y2)/2-1);
        $pix2=array(($this->x2 + $this->x3)/2 +1, ($this->y2 + $this->y3)/2);
        $pix3=array(($this->x3 + $this->x4)/2, ($this->y3 + $this->y4)/2 +1);
        $pix4=array(($this->x4 + $this->x1)/2-1, ($this->y4 + $this->y1)/2);
        $rgb1=imagecolorsforindex($this->get_mother_image(),imagecolorat($this->get_mother_image(), $pix1[0], $pix1[1]));
        $rgb2=imagecolorsforindex($this->get_mother_image(),imagecolorat($this->get_mother_image(), $pix2[0], $pix2[1]));
        $rgb3=imagecolorsforindex($this->get_mother_image(),imagecolorat($this->get_mother_image(), $pix3[0], $pix3[1]));
        $rgb4=imagecolorsforindex($this->get_mother_image(),imagecolorat($this->get_mother_image(), $pix4[0], $pix4[1]));
        $r=max($rgb1['red'],$rgb2['red'],$rgb3['red'],$rgb4['red']);
        $g=max($rgb1['blue'],$rgb2['blue'],$rgb3['blue'],$rgb4['blue']);
        $b=max($rgb1['green'],$rgb2['green'],$rgb3['green'],$rgb4['green']);
        
        $this->background_color_alt=array($r,$g,$b);
    }


    function dominant_color_alt(){
        $pix1=array($this->x1 , $this->y1 );
        $pix2=array($this->x2 , $this->y2 );
        $pix3=array($this->x3 , $this->y3 );
        $pix4=array($this->x4 , $this->y4 );
        $rgb1=imagecolorsforindex($this->get_mother_image(),imagecolorat($this->get_mother_image(), $pix1[0], $pix1[1]));
        $rgb2=imagecolorsforindex($this->get_mother_image(),imagecolorat($this->get_mother_image(), $pix2[0], $pix2[1]));
        $rgb3=imagecolorsforindex($this->get_mother_image(),imagecolorat($this->get_mother_image(), $pix3[0], $pix3[1]));
        $rgb4=imagecolorsforindex($this->get_mother_image(),imagecolorat($this->get_mother_image(), $pix4[0], $pix4[1]));
        $r=max($rgb1['red'],$rgb2['red'],$rgb3['red'],$rgb4['red']);
        $g=max($rgb1['blue'],$rgb2['blue'],$rgb3['blue'],$rgb4['blue']);
        $b=max($rgb1['green'],$rgb2['green'],$rgb3['green'],$rgb4['green']);
        
        $this->background_color_alt=array($r,$g,$b);
    }
    
    // calculate  angle between 2 pixels
    private function pixels_angle2 ($x1,$y1,$x2,$y2) {
        $x3=$x2;
        $y3=$y1;
        $ac=abs($x3-$x1);
        $bc=abs($y3-$y2);
        if (($ac !=0) && ($bc !=0)) {
        $ab=sqrt(pow($bc,2)+pow($ac,2));
        $tmp=((pow($ac,2) + pow($ab,2) - pow($bc,2)) / (2*$ac*$ab));
        $bac = rad2deg(acos($tmp));
        } else
        {
            if ($ac == 0 )
                $bac=90;
            if ($bc == 0)
                $bac=0;
        }
        return $bac;
    }

    //Reorder pixel coordinates and fix angle
    private function calculate_text_angle (){
        if ($this->calculate_angle){
            $angle=round($this->pixels_angle2(($this->x1+$this->x4)/2, ($this->y1+$this->y4)/2,($this->x2+$this->x3)/2, ($this->y2+$this->y3)/2));
            $rotate=$this->nb_rotate;
            
            if ($rotate ==0)
                $this->text_angle=$angle;
            if ($rotate ==1)
                $this->text_angle=2*90-$angle;
            if ($rotate ==2)
                $this->text_angle=2*90+$angle;;
            if ($rotate ==3)
                $this->text_angle=3*90+(90-$angle);

            while ($this->text_angle >= 360){
                $this->text_angle-=360;}
            
            while ($this->text_angle <= -360){
                $this->text_angle+=360;
            }
        }
        $this->ori_point_to_reordered();
    }

    public function ori_point_to_reordered ($marge=3){
        $rotate=0;
        $x1=$this->x1;
        $y1=$this->y1;
        $x2=$this->x2;
        $y2=$this->y2;
        $x3=$this->x3;
        $y3=$this->y3;
        $x4=$this->x4;
        $y4=$this->y4;
        while (
            ( $x1 >=  $x2 ) ||
            ( $y2 >= $y3) ||
            ($x3 <= $x4) ||
            ($y4 <= $y1)  ||
            ($x4 +$marge< $x1) ||
            ($y2 -$marge> $y1) 
            ){
                $rotate++;
                $tmpx=$x1;
                $tmpy=$y1;
                $x1=$x2;
                $y1=$y2;
                $x2=$x3;
                $y2=$y3;
                $x3=$x4;
                $y3=$y4;
                $x4=$tmpx;
                $y4=$tmpy;                
            }

        $this->ordered = array(
            'x1'=>$x1,
            'y1'=>$y1,
            'x2'=>$x2,
            'y2'=>$y2,
            'x3'=>$x3,
            'y3'=>$y3,
            'x4'=>$x4,
            'y4'=>$y4
        );
        
        $this->nb_rotate=$rotate;
    }

    public function reorderpoint_to_ori (){
        // A appeller après merge text box

        $x1=$this->ordered['x1'];
        $y1=$this->ordered['y1'];
        $x2=$this->ordered['x2'];
        $y2=$this->ordered['y2'];
        $x3=$this->ordered['x3'];
        $y3=$this->ordered['y3'];
        $x4=$this->ordered['x4'];
        $y4=$this->ordered['y4'];
        
        for ($i=0 ; $i<$this->nb_rotate; $i++)
            {
                $tmpx=$x1;
                $tmpy=$y1;
                $x1=$x4;
                $y1=$y4;
                $x4=$x3;
                $y4=$y3;
                $x3=$x2;
                $y3=$y2;
                $x2=$tmpx;
                $y2=$tmpy;                
        }
        $this->x1=$x1;
        $this->y1=$y1;
        $this->x2=$x2;
        $this->y2=$y2;
        $this->x3=$x3;
        $this->y3=$y3;
        $this->x4=$x4;
        $this->y4=$y4;
    }
    
    //Extract bloc textimage from manga image
    function extract_bloc() {
        //image crop don't work with text with angles
        // so we fill everythin wich is not text in white,
        // and then use autocrop
        $image = cloneImg($this->get_mother_image());        
        $white   = imagecolorallocate($image, 255, 255, 255);
        $image_width = imagesx($image);
        $image_height = imagesy($image);
        

        // block height can't be < 4 pixels
        if (max($this->ordered['y3'], $this->ordered['y4']) - min($this->ordered['y1'],$this->ordered['y2']) < 4){
            $this->ordered['y1']-=1;
            $this->ordered['y2']-=1;
            $this->ordered['y3']+=1;
            $this->ordered['y4']+=1;
        }

        // block width can't be < 4 pixels
        if (max($this->ordered['x2'], $this->ordered['x3']) - min($this->ordered['x1'],$this->ordered['x4']) < 4){
            $this->ordered['x1']-=1;
            $this->ordered['x2']+=1;
            $this->ordered['x3']+=1;
            $this->ordered['x4']-=1;
        }

        $pol1=array(
            0,0,
            $this->ordered['x2'],0,
            $this->ordered['x2'],$this->ordered['y2'],
            $this->ordered['x1'],$this->ordered['y1'],
            0,$this->ordered['y1']
        );
        
        $pol2=array(
            $this->ordered['x2'],0,
            $this->ordered['x2'],$this->ordered['y2'],
            $this->ordered['x3'],$this->ordered['y3'],
            $image_width, $this->ordered['y3'],
            $image_width, 0
        );
        $pol3=array(
            
            $image_width,$this->ordered['y3'],
            $this->ordered['x3'],$this->ordered['y3'],
            $this->ordered['x4'],$this->ordered['y4'],
            $this->ordered['x4'],$image_height,
            $image_width,$image_height
            
        );
        $pol4=array(
            $this->ordered['x4'],$image_height,
            $this->ordered['x4'],$this->ordered['y4'],
            $this->ordered['x1'],$this->ordered['y1'],
            0,$this->ordered['y1'],
            0,$image_height
        );

        imagefilledpolygon($image, $pol1, 5, $white);
        imagefilledpolygon($image, $pol2, 5, $white);
        imagefilledpolygon($image, $pol3, 5, $white);
        imagefilledpolygon($image, $pol4, 5, $white);

        $this->path="uploads/".microtime().'.jpg';
        $this->image=imagecropauto($image,IMG_CROP_THRESHOLD, $threshold=0.1, $white);
        imagewrite($this->image,$this->path,$quality=100);
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

    function expand_block_text($color_tolerance=50,$offset=4){

        $x1=$this->x1;
        $y1=$this->y1;
        $x2=$this->x2;
        $y2=$this->y2;
        $x3=$this->x3;
        $y3=$this->y3;
        $x4=$this->x4;
        $y4=$this->y4;
        
        $image=cloneImg($this->get_mother_image());
        $black = imagecolorallocate($image, 0, 0, 0);
        $background=$this->background_color_alt;
        
        if ($this->text_angle !=0){

            $xmax_ori=imagesx($image);
            $ymax_ori=imagesy($image);
            
            $diago_prec=(sqrt($xmax_ori*$xmax_ori+$ymax_ori*$ymax_ori));
            $diago=round($diago_prec);
            $tmpimg=imagecreatetruecolor($diago,$diago);

            $white = imagecolorallocate($tmpimg, 255,255,255);
            $black = imagecolorallocate($tmpimg, 0, 0, 0);
            $red = imagecolorallocate($tmpimg, 255,0,0);
            $blue = imagecolorallocate($tmpimg, 0,0,255);
            $green = imagecolorallocate($tmpimg, 0,255,0);

            imagefilledrectangle($tmpimg, 0,0,$diago-1,$diago-1,$white);
            imagecopy ( $tmpimg, $image , ($diago-$xmax_ori)/2, ($diago-$ymax_ori)/2 , 0 , 0 , $xmax_ori, $ymax_ori);
            imageellipse($tmpimg, $diago/2, $diago/2, $diago-1, $diago-1, $black);
            imagerectangle (  $tmpimg , $diago/2-4 , $diago/2-4 , $diago/2+4 , $diago/2+4, $blue );

            imagesetinterpolation ( $image ,  IMG_NEAREST_NEIGHBOUR);

            $image=imagerotate ($tmpimg , 0 - $this->text_angle , $white );
            $tmpimg=imagecropauto($image,IMG_CROP_THRESHOLD, $threshold=0.1, $white);
            
            $xmax=imagesx($tmpimg);
            $ymax=imagesy($tmpimg);

            $x_offset=($xmax-$xmax_ori)/2;
            $y_offset=($ymax-$ymax_ori)/2;

            $this->reorderpoint_to_ori();

            $x1=$this->x1+$x_offset;
            $y1=$this->y1+$y_offset;
            $x2=$this->x2+$x_offset;
            $y2=$this->y2+$y_offset;
            $x3=$this->x3+$x_offset;
            $y3=$this->y3+$y_offset;
            $x4=$this->x4+$x_offset;
            $y4=$this->y4+$y_offset;

            imagerectangle ($tmpimg , $xmax/2-2 , $ymax/2-2 , $xmax/2+2 , $ymax/2+2, $blue );
            
            $a=rotate($x1,$y1,$xmax/2,$ymax/2,$this->text_angle);
            $b=rotate($x2,$y2,$xmax/2,$ymax/2,$this->text_angle);
            $c=rotate($x3,$y3,$xmax/2,$ymax/2,$this->text_angle);
            $d=rotate($x4,$y4,$xmax/2,$ymax/2,$this->text_angle);

            $x1=$a[0] - $offset;
            $y1=$a[1] - $offset;
            $x2=$b[0] + $offset;
            $y2=$b[1] - $offset;
            $x3=$c[0] + $offset;
            $y3=$c[1] + $offset;
            $x4=$d[0] - $offset;
            $y4=$d[1] + $offset;
        } else {
            $tmpimg=$image;
            $xmax=imagesx($tmpimg);
            $ymax=imagesy($tmpimg);

            $x1=max($this->ordered['x1'] - $offset,0);
            $y1=max($this->ordered['y1'] - $offset,0);
            $x2=min($this->ordered['x2'] + $offset,$xmax);
            $y2=max($this->ordered['y2'] - $offset,0);
            $x3=min($this->ordered['x3'] + $offset,$xmax);
            $y3=min($this->ordered['y3'] + $offset,$ymax);
            $x4=max($this->ordered['x4'] - $offset,0);
            $y4=min($this->ordered['y4'] + $offset,$ymax);
        }
        $xmax=imagesx($tmpimg);
        $ymax=imagesy($tmpimg);


        if (abs($x1-$x4) <4) {
            $doleft=true;
            $x1 =min($x1,$x4);
            $x4=$x1;
        }else {$doleft=false;}
        
        if (abs($y1-$y2) <4) {
            $dotop=true;
            $y1 =min($y1,$y2);
            $y2 =$y1;
        }else {$dotop=false;}
        if (abs($x2-$x3) <4) {
            $doright=true;
            $x2 =max($x2,$x3);
            $x3 =$x2;
        }else {$doright=false;}
        if (abs($y3-$y4) <4) {
            $dobottom=true;
            $y3 =max($y3,$y4);
            $y4 =$y3;
        }else {$dobottom=false;}

        //We expand each block side 1px per 1 px, so  we keep the original textbolck center
        while ( $doleft && $doright && (min($x1,$x4) > 0) && (max($x2,$x3) < $xmax-1)) {
            //doleft
            $x=$x1-1;
            for ($y=max($y1,$y4); ($y >= min($y1,$y4)) && $doleft; $y-- ){
                $rgb=imagecolorat($tmpimg,$x,$y);
                $colors=imagecolorsforindex($tmpimg,$rgb);
                if ( 
                    (abs($colors['red'] - $background[0]) >$color_tolerance) ||
                    (abs($colors['green'] - $background[1]) >$color_tolerance) ||
                    (abs($colors['blue'] - $background[2]) >$color_tolerance))
                    {
                        $doleft =false;
                    }
            }
            if ($doleft ){
                $x1=$x;
                $x4=$x;
            }
            //doright
            $x=$x2+1;
            for ($y=min($y2,$y3); ($y <= max($y3,$y3)) && $doright; $y++ ){
                $rgb=imagecolorat($tmpimg,$x,$y);
                $colors=imagecolorsforindex($tmpimg,$rgb);
                if ( 
                    (abs($colors['red'] - $background[0]) >$color_tolerance) ||
                    (abs($colors['green'] - $background[1]) >$color_tolerance) ||
                    (abs($colors['blue'] - $background[2]) >$color_tolerance))
                    {
                        $doright =false;
                    }
                }
                if ($doright ){
                    $x2=$x;
                    $x3=$x;
                }
            }

            while ( $dotop && $dobottom&& (min($y1,$y2) > 0) && (max($y3,$y4) < $ymax-1)) {
                //dotop
                $y=$y1-1;
                for ($x=min($x1,$x2); ($x <= max($x1,$x2)) && $dotop; $x++ ){
                    $rgb=imagecolorat($tmpimg,$x,$y);
                    $colors=imagecolorsforindex($tmpimg,$rgb);
                    if ( 
                        (abs($colors['red'] - $background[0]) >$color_tolerance) ||
                        (abs($colors['green'] - $background[1]) >$color_tolerance) ||
                        (abs($colors['blue'] - $background[2]) >$color_tolerance))
                        {
                            $dotop =false;
                        } 
                    }
                if ($dotop ){
                    $y1=$y;
                    $y2=$y;
                }
                            
                //dobottom
                $y=$y3+1;
                for ($x=max($x3,$x4); ($x >= min($x3,$x4)) && $dobottom; $x-- ){
                    $rgb=imagecolorat($tmpimg,$x,$y);
                    $colors=imagecolorsforindex($tmpimg,$rgb);
                    if ( 
                        (abs($colors['red'] - $background[0]) >$color_tolerance) ||
                        (abs($colors['green'] - $background[1]) >$color_tolerance) ||
                        (abs($colors['blue'] - $background[2]) >$color_tolerance))
                        {
                            $dobottom =false;
                        }
                }
                if ($dobottom ){
                    $y3=$y;
                    $y4=$y;
                }
            }
            if ($this->text_angle != 0) {
                $a=rotate($x1,$y1,$xmax/2,$ymax/2,0-$this->text_angle);
                $b=rotate($x2,$y2,$xmax/2,$ymax/2,0-$this->text_angle);
                $c=rotate($x3,$y3,$xmax/2,$ymax/2,0-$this->text_angle);
                $d=rotate($x4,$y4,$xmax/2,$ymax/2,0-$this->text_angle);

                $x1=$a[0]-$x_offset;
                $y1=$a[1]-$y_offset;
                $x2=$b[0]-$x_offset;
                $y2=$b[1]-$y_offset;
                $x3=$c[0]-$x_offset;
                $y3=$c[1]-$y_offset;
                $x4=$d[0]-$x_offset;
                $y4=$d[1]-$y_offset;
                    
        }

        $this->x1=$x1;
        $this->y1=$y1;
        $this->x2=$x2;
        $this->y2=$y2;
        $this->x3=$x3;
        $this->y3=$y3;
        $this->x4=$x4;
        $this->y4=$y4;
        if ($this->text_angle !=0)
            $this->ori_point_to_reordered();

        $image=cloneImg($this->get_mother_image());
        $red = imagecolorallocate($image, 255,0,0);
        $blue = imagecolorallocate($image, 0,0,255);
        }

        function draw_boxes2 ($colorid=0, $offset=0) {
            $image=cloneImg($this->get_mother_image());

            $color[0]= imagecolorallocate($image, 0, 0, 0);
            $color[1]= imagecolorallocate($image, 255, 0, 0);
            $color[2]= imagecolorallocate($image, 0, 255, 0);
            $color[3]= imagecolorallocate($image, 0, 0, 255);
            $color[4]= imagecolorallocate($image, 255, 255, 0);
            
            $linecolor=$color[$colorid];
            
            imageline ( $image ,  $this->ordered['x1'] -$offset, $this->ordered['y1'] -$offset, $this->ordered['x2'] +$offset, $this->ordered['y2'] -$offset, $linecolor);
            imageline ( $image ,  $this->ordered['x2'] +$offset, $this->ordered['y2'] -$offset, $this->ordered['x3'] +$offset, $this->ordered['y3'] +$offset , $linecolor);
            imageline ( $image ,  $this->ordered['x3'] +$offset, $this->ordered['y3'] +$offset, $this->ordered['x4'] -$offset, $this->ordered['y4'] +$offset , $linecolor);
            imageline ( $image ,  $this->ordered['x4'] -$offset, $this->ordered['y4'] +$offset, $this->ordered['x1'] -$offset, $this->ordered['y1'] -$offset , $linecolor);
          }
    }
                