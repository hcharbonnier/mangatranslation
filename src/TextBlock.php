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
    public $background_color;
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
            $this->text_angle = round($this->pixels_angle(($x1+$x4)/2, ($y1+$y4)/2,($x2+$x3)/2, ($y2+$y3)/2));
            $this->mother_image = imagecreatefromjpeg($mother_path);
            $this->extract_bloc();
            $this->dominant_color();
            $this->dominant_color_alt();
            $this->detect_text();
            $this->expand_block_text();
            $this->find_font_size();
            $this->font_size=$this->original_font_size;


        $this->translated_text=translate($this->ocr_text, "en");
        $formatted_text=format_text(min($this->x2-$this->x1,$this->x3-$this->x4 ),min($this->y4-$this->y1, $this->y3-$this->y2), $this->text_angle, $this->font, $this->font_size, $this->translated_text,11);
        $this->translation_width = $formatted_text['width_px'];
        $this->translation_height = $formatted_text['height_px'];
        $this->translation_top_offset= $formatted_text['top'];
        $this->translation_left_offset = $formatted_text['left'];
        $this->formatted_text=html_entity_decode($formatted_text['text'],ENT_QUOTES);
        //echo($this->formatted_text);
        $this->font_size=$formatted_text['size'];
    }

    function detect_text()
    {
        $path=$this->path;
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

    function dominant_color(){
        $dominantColors = ColorThief::getPalette($this->path,$colorCount=2);
        if ($dominantColors[0][0]+$dominantColors[0][1]+$dominantColors[0][2] > $dominantColors[1][0]+$dominantColors[1][1]+$dominantColors[1][2])
            $this->background_color=$dominantColors[0];
        else
            $this->background_color=$dominantColors[1];
    }

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
    private function pixels_angle ($x1,$y1,$x2,$y2) {
        $x3=$x2;
        $y3=$y1;
        $ac_x=abs($x3-$x1);
        $ac_y=abs($y3-$y1);
        $ac_length=sqrt(pow($ac_x,2)+pow($ac_y,2));
        $bc_x=abs($x2-$x3);
        $bc_y=abs($y1-$y3);
        $bc_length=sqrt(pow($bc_x,2)+pow($bc_y,2));
        $bac=atan($bc_length/$ac_length);
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

    //Extract bloc textimage from manga image
    function extract_bloc() {
        $image = imagecreatefromjpeg($this->mother_path);
        $white   = imagecolorallocate($image, 255, 255, 255);
        list($image_width, $image_height, $type, $attr) = getimagesize($this->mother_path);        
        $pol1=array(
            0,0,
            $this->x1,$this->y1,
            $this->x2,$this->y2,
            $image_width,0
        );
        $pol2=array(
            $image_width,0,
            $this->x2,$this->y2,
            $this->x3,$this->y3,
            $image_width, $image_height
        );
        $pol3=array(
            
            $image_width, $image_height,
            $this->x3,$this->y3,
            $this->x4,$this->y4,
            0,$image_height
        );
        $pol4=array(
            0,$image_height,
            $this->x4,$this->y4,
            $this->x1,$this->y1,
            0,0
        );

        imagefilledpolygon($image, $pol1, 4, $white);
        imagefilledpolygon($image, $pol2, 4, $white);
        imagefilledpolygon($image, $pol3, 4, $white);
        imagefilledpolygon($image, $pol4, 4, $white);
        $this->image=imagecropauto($image,IMG_CROP_THRESHOLD, $threshold=0, $white);
        //$this->image=imagecrop($image, [ 'x' => $this->x, 'y' => $this->y,'width' => $this->width,'height' => $this->height]);
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
        }
        if (abs($y1-$y2) <4) {
            $dotop=true;
            $y1 =min($y1,$y2);
            $y2 =$y1;
        }
        if (abs($x2-$x3) <4) {
            $doright=true;
            $x2 =max($x2,$x3);
            $x3 =$x2;
        }
        if (abs($y3-$y4) <4) {
            $dobottom=true;
            $y3 =max($y3,$y4);
            $y4 =$y3;
        }

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
