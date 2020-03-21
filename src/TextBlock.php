<?php 
namespace mangatranslation;

#require __DIR__ . '/vendor/autoload.php';

# imports the Google Cloud client library
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Translate\TranslateClient;

use ColorThief\ColorThief;
require_once "function.php";

//  text_bloc: block text in a page
class TextBlock {
    public $image;
    public $mother_image;
    public $path;
    public $mother_path;
    public $x1;
    public $y1;
    public $x2;
    public $y2;
    public $x3;
    public $y3;
    public $x4;
    public $y4;
//    public $background_color;
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

    public $font=__DIR__."/../fonts/animeace2_reg.ttf";

    function __construct($mother_path,$x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4) {
        $this->x1 = $x1;
        $this->y1 = $y1;
        $this->x2 = $x2;
        $this->y2 = $y2;
        $this->x3 = $x3;
        $this->y3 = $y3;
        $this->x4 = $x4;
        $this->y4 = $y4;
        $this->mother_path = $mother_path;
    }

        function load() {
            $x1=$this->x1;
            $y1=$this->y1;
            $x2=$this->x2;
            $y2=$this->y2;
            $x3=$this->x3;
            $y3=$this->y3;
            $x4=$this->x4;
            $y4=$this->y4;
            $mother_path=$this->mother_path;
            $this->reorder_points();
            $this->text_angle = round($this->pixels_angle2(($x1+$x4)/2, ($y1+$y4)/2,($x2+$x3)/2, ($y2+$y3)/2));
           // echo "angle:".$this->text_angle."\n";
            //echo "x1:".(($x1+$x4)/2)." y1:". (($y1+$y4)/2)." x2:".(($x2+$x3)/2)." y2:".(($y2+$y3)/2)."\n;";
            $this->mother_image = imagecreatefromjpeg($mother_path);
            $this->extract_bloc();
            //$this->dominant_color();
            $this->dominant_color_alt();
            $this->detect_text();
            $this->expand_block_text();
            $this->find_font_size();
            $this->font_size=$this->original_font_size;

            $this->translated_text=translate($this->ocr_text, "en");

            $formatted_text=$this->format_text($this->x3-$this->x1,$this->y4-$this->y2, $this->text_angle, $this->font, $this->font_size, $this->translated_text,11);
            $this->translation_width = $formatted_text['width_px'];
            $this->translation_height = $formatted_text['height_px'];
            $this->translation_top_offset= $formatted_text['top'];
            $this->translation_left_offset = $formatted_text['left'];
             $this->formatted_text=html_entity_decode($formatted_text['text'],ENT_QUOTES);
            //echo($this->formatted_text);
            $this->font_size=$formatted_text['size'];
    }

    //Unused function
    function get_redressed_textbox_dimentions(){
        if ($angle !=0) {
            $tmpimage= imagecreatetruecolor(8000, 8000);
            $white = imagecolorallocate($tmpimage, 255, 255, 255);
            $black = imagecolorallocate($tmpimage, 0, 0, 0);
            imagefilledrectangle($tmpimage, 0, 0, 7999, 7999, $white);
            $pol=array(
              $this->x4,$image_height,
              $this->x4,$this->y4,
              $this->x1,$this->y1,
              0,$this->y1,
              0,$image_height
          );
        }
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

// Ugly angle hack
if  (($angle < 90) && ($width < 60) && ($height / $width > 3)){
    echo "ugly angle hack: ".$this->mother_path.":\n";
    echo "$text\n";
    $angle = $angle +90;
    $this->text_angle =$angle;
}
// Ugly angle hack end
        if ($angle !=0) {
            $tmpimage= imagecreatetruecolor(8000, 8000);
            $white = imagecolorallocate($tmpimage, 255, 255, 255);
            $black = imagecolorallocate($tmpimage, 0, 0, 0);
            imagefilledrectangle($tmpimage, 0, 0, 7999, 7999, $white);
            $pol=array(
                $this->x1,$this->y1,
                $this->x2,$this->y2,
                $this->x3,$this->y3,
                $this->x4,$this->y4,
          );
          imagefilledpolygon($tmpimage, $pol, 4, $black);
          $tmpimage=imagerotate ( $tmpimage , 0- $angle ,  $white );
          //echo "rotate angle: $angle\n";
          $tmpimage=imagecropauto($tmpimage,IMG_CROP_THRESHOLD, $threshold=0.1, $white);
          $horiz_width=max(imagesx($tmpimage)-(2*$border), 2*$border+8);
          $horiz_height=max(imagesy($tmpimage)-(2*$border), 2*$border+8);
          $txt_horiz_width=$horiz_width;
          $txt_horiz_height=$horiz_height;
          imagejpeg($tmpimage,"dump/angle_img.jpg");

        }
                
       // }

        if ($font_size <=7)
        $font_size=8;
        
        $dim["height"]  = $height;
        $dim["width"]  = $width;
        while (
            ((( $dim["height"]  >= $height )||( $dim["width"]  >= $width )) && ($font_size > 7)) || 
            ((isset($horiz_width) && $txt_horiz_width >= $horiz_width ) || (isset($horiz_height) && $txt_horiz_height >= $horiz_height ))) {
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
            if ($angle != 0){
                $tmp=(calculateTextBox($size, 0, $font, $text_res));
                $txt_horiz_width=$tmp['width'];
                $txt_horiz_height=$tmp['height'];
            }

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
        //echo "___________\n";
        //echo "size: ".$resultat['size']."\n";
        //echo "$text\n";
    return $resultat;
    }

    function detect_text()
    {
        ob_start(); // start a new output buffer
            imagejpeg($this->image,NULL,100);
            $image = ob_get_contents();
        ob_end_clean(); // stop this output buffer
//        $path=$this->path;
        $resultats=array();
        $j=0;
        $imageAnnotator = new ImageAnnotatorClient();

        # annotate the image
        //$image = file_get_contents($path);
        //$image=imagejpeg($this->image);
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

    function find_font_size(){
        $nb_line=substr_count( $this->ocr_paragraph, "\n" )+1;
        $height_pixel=$this->original_text_pixel_height();
        $font_pixel_height=$height_pixel/$nb_line;
        $this->original_font_size=round($font_pixel_height/2.2);
    }

   /* function dominant_color(){
        $dominantColors = ColorThief::getPalette($this->path,$colorCount=2);
        if ($dominantColors[0][0]+$dominantColors[0][1]+$dominantColors[0][2] > $dominantColors[1][0]+$dominantColors[1][1]+$dominantColors[1][2])
            $this->background_color=$dominantColors[0];
        else
            $this->background_color=$dominantColors[1];
    }*/

    function dominant_color_alt(){
        $pix1=array(($this->x1 + $this->x2)/2, ($this->y1 + $this->y2)/2-1);
        $pix2=array(($this->x2 + $this->x3)/2 +1, ($this->y2 + $this->y3)/2);
        $pix3=array(($this->x3 + $this->x4)/2, ($this->y3 + $this->y4)/2 +1);
        $pix4=array(($this->x4 + $this->x1)/2-1, ($this->y4 + $this->y1)/2);
        $rgb1=imagecolorsforindex($this->mother_image,imagecolorat($this->mother_image, $pix1[0], $pix1[1]));
        $rgb2=imagecolorsforindex($this->mother_image,imagecolorat($this->mother_image, $pix2[0], $pix2[1]));
        $rgb3=imagecolorsforindex($this->mother_image,imagecolorat($this->mother_image, $pix3[0], $pix3[1]));
        $rgb4=imagecolorsforindex($this->mother_image,imagecolorat($this->mother_image, $pix4[0], $pix4[1]));
        $r=max($rgb1['red'],$rgb2['red'],$rgb3['red'],$rgb4['red']);
        $g=max($rgb1['blue'],$rgb2['blue'],$rgb3['blue'],$rgb4['blue']);
        $b=max($rgb1['green'],$rgb2['green'],$rgb3['green'],$rgb4['green']);
//        $r=($rgb1['red']+$rgb2['red']+$rgb3['red']+$rgb4['red'])/4;
//        $g=($rgb1['blue']+$rgb2['blue']+$rgb3['blue']+$rgb4['blue'])/4;
//        $b=($rgb1['green']+$rgb2['green']+$rgb3['green']+$rgb4['green'])/4;

        $this->background_color_alt=array($r,$g,$b);
    }

    // calculate  angle between 2 pixels
    private function pixels_angle2 ($x1,$y1,$x2,$y2) {
        $x3=$x2;
        $y3=$y1;
        $ac=abs($x3-$x1);
        $bc=abs($y3-$y2);
        $ab=sqrt(pow($bc,2)+pow($ac,2));
        $tmp=((pow($ac,2) + pow($ab,2) - pow($bc,2)) / (2*$ac*$ab));
        $bac = rad2deg(acos($tmp));
        return $bac;
    }

    // return array(red,green, blue) color of a pixel
    private function pixel_color($path, $x,$y) {
        $im = imagecreatefrompng($path);
        $rgb = imagecolorat($im, 10, 15);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        return(array($r,$g,$b));
    }

    private function reorder_points (){
        //If text is not horizontal, we need x2,y2 to be the higher point (needed by extract_bloc())
        while (( min($this->y1,$this->y3,$this->y4,) < $this->y2 -2) && ( max($this->y1,$this->y2,$this->y3,) > $this->y4 +2)){
            //echo "rotate\n";
            $tmpx=$this->x1;
            $tmpy=$this->y1;
            $this->x1=$this->x2;
            $this->y1=$this->y2;
            $this->x2=$this->x3;
            $this->y2=$this->y3;
            $this->x3=$this->x4;
            $this->y3=$this->y4;
            $this->x4=$tmpx;
            $this->y4=$tmpy;
        }

    }

    //Extract bloc textimage from manga image
    function extract_bloc() {
        //image crop don't work with text with angles
        // so we fill everythin wich is not text in white,
        // and then use autocrop
        $image = imagecreatefromjpeg($this->mother_path);
        $white   = imagecolorallocate($image, 255, 255, 255);
        list($image_width, $image_height, $type, $attr) = getimagesize($this->mother_path);        
        $pol1=array(
            0,0,
            $this->x2,0,
            $this->x2,$this->y2,
            $this->x1,$this->y1,
            0,$this->y1
        );

        $pol2=array(
            $this->x2,0,
            $this->x2,$this->y2,
            $this->x3,$this->y3,
            $image_width, $this->y3,
            $image_width, 0
        );
        $pol3=array(
            
            $image_width,$this->y3,
            $this->x3,$this->y3,
            $this->x4,$this->y4,
            $this->x4,$image_height,
            $image_width,$image_height

        );
        $pol4=array(
            $this->x4,$image_height,
            $this->x4,$this->y4,
            $this->x1,$this->y1,
            0,$this->y1,
            0,$image_height
        );

//        echo "x1:". $this->x1. " ";
//        echo "y1:". $this->y1. " ";
//        echo "x2:". $this->x2. " ";
//        echo "y2:". $this->y2. " ";
//        echo "x3:". $this->x3. " ";
//        echo "y3:". $this->y3. " ";
//        echo "x4:". $this->x4. " ";
//        echo "y4:". $this->y4. "\n";
        

        imagefilledpolygon($image, $pol1, 5, $white);
        imagefilledpolygon($image, $pol2, 5, $white);
        imagefilledpolygon($image, $pol3, 5, $white);
        imagefilledpolygon($image, $pol4, 5, $white);
        $this->image=imagecropauto($image,IMG_CROP_THRESHOLD, $threshold=0.1, $white);
        @mkdir("dump");
        $this->path='dump/'.basename($this->mother_path).'-'.$this->x1.'-'.$this->y1.'.jpg';
        imagejpeg($this->image,$this->path);
    }

    function expand_block_text($tolerance=50,$offset=5){
        //known bug: To bloc can expand over eachother
        $mother_path=$this->mother_path;
        $background=$this->background_color_alt;
        $x1=$this->x1 - $offset;
        $y1=$this->y1 - $offset;
        $x2=$this->x2 + $offset;
        $y2=$this->y2 - $offset;
        $x3=$this->x3 + $offset;
        $y3=$this->y3 + $offset;
        $x4=$this->x4 - $offset;
        $y4=$this->y4 + $offset;
        $image=$this->mother_image;

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
        while ($doleft && $dotop && $doright && $dobottom) {
            //doleft
                $x=$x1-1;
                for ($y=$y4; ($y >= $y1) && $doleft; $y-- ){
                    $rgb=imagecolorat($image,$x,$y);
                    $colors=imagecolorsforindex($image,$rgb);
                    if ( 
                        (abs($colors['red'] - $background[0]) >$tolerance) ||
                        (abs($colors['green'] - $background[1]) >$tolerance) ||
                        (abs($colors['blue'] - $background[2]) >$tolerance))
                        {
                             $doleft =false;
                        }
                }
                if ($doleft ){
                    $x1=$x;
                    $x4=$x;
                    $this->x1=$x;
                    $this->x4=$x;
                }
            //dotop
                $y=$y1-1;
                for ($x=$x1; ($x <= $x2) && $dotop; $x++ ){
                    $rgb=imagecolorat($image,$x,$y);
                    $colors=imagecolorsforindex($image,$rgb);
                    if ( 
                        (abs($colors['red'] - $background[0]) >$tolerance) ||
                        (abs($colors['green'] - $background[1]) >$tolerance) ||
                        (abs($colors['blue'] - $background[2]) >$tolerance))
                        {
                             $dotop =false;
                        }
                }
                if ($dotop ){
                    $y1=$y;
                    $y2=$y;
                    $this->y1=$y;
                    $this->y2=$y;
                }
                //doright
                    $x=$x2+1;
                    for ($y=$y2; ($y <= $y3) && $doright; $y++ ){
                        $rgb=imagecolorat($image,$x,$y);
                        $colors=imagecolorsforindex($image,$rgb);
                        if ( 
                            (abs($colors['red'] - $background[0]) >$tolerance) ||
                            (abs($colors['green'] - $background[1]) >$tolerance) ||
                            (abs($colors['blue'] - $background[2]) >$tolerance))
                            {
                                 $doright =false;
                            }
                    }
                    if ($doright ){
                        $x2=$x;
                        $x3=$x;
                        $this->x2=$x;
                        $this->x3=$x;
                    }
                    
                //dobottom
                $y=$y3+1;
                for ($x=$x3; ($x >= $x4) && $dobottom; $x-- ){
                    $rgb=imagecolorat($image,$x,$y);
                    $colors=imagecolorsforindex($image,$rgb);
                    if ( 
                        (abs($colors['red'] - $background[0]) >$tolerance) ||
                        (abs($colors['green'] - $background[1]) >$tolerance) ||
                        (abs($colors['blue'] - $background[2]) >$tolerance))
                        {
                             $dobottom =false;
                        }
                }
                if ($dobottom ){
                    $y3=$y;
                    $y4=$y;
                    $this->y3=$y;
                    $this->y4=$y;
                }
        }
    }
}
