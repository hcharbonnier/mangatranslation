<?php 
namespace mangatranslation;

#require __DIR__ . '/vendor/autoload.php';

# imports the Google Cloud client library
use Google\Cloud\Vision\V1\ImageAnnotatorClient;

require_once "function.php";

define ('FEATURE_PAGE', 1);
define ('FEATURE_BLOCK', 2);
define ('FEATURE_PARA', 3);
define ('FEATURE_WORD', 4);
define ('FEATURE_SYMBOL', 5);


class MangaImage
{
  public $path=null;
  public $image=null;
  public $image_drawn=null;
  public $text_blocks=[];
  //public $bounds=null;
  //public $ocr_text=null;
  //public $trans_text=null;
  public $clean_image=null;
  public $final_image=null;
  public $translated_path=null;
  public $clean_path=null;
 
  public $response=null;
  public $annotation=null;

  
  function __construct($path) {
    $this->path = $path;
  }

  function load(){
    $this->image = file_get_contents($this->path);
    
    # performs label detection on the image file
    $imageAnnotator = new ImageAnnotatorClient();
    $this->response = $imageAnnotator->textDetection($this->image);
    $this->annotation = $this->response->getFullTextAnnotation();
    $this->get_document_bounds($this->annotation, FEATURE_BLOCK);
    $this->draw_boxes2($this->path);
    $this->merge_similar_bloc();
   
    foreach ($this->text_blocks as $text_block) {
        $text_block->load();
    }

    $this->draw_boxes2($this->path,1,1);
    $this->clean_image();
    $this->insert_translations();
    //$this->draw_boxes($this->path,$this->bounds);
    //$this->extract_bounds($this->path,$this->bounds);
  }

  //debug function
  function dump(){
    echo("\n#########################DUMP START#########################");
    echo("\npath:".$this->path);

    //echo("\nocr_text:");
    //@print_r($this->ocr_text);

    //echo("\ntrans_text:");
    //@print_r($this->trans_text);

    echo("\ntext_blocks:");
    @print_r($this->text_blocks);
    
    @mkdir('dump');
    $i=0;
    foreach($this->text_blocks as $block) {
        imagejpeg($block->image,'./dump/'.$i.'.jpg');
        $dominantColors = ColorThief::getPalette('./dump/'.$i.'.jpg',$colorCount=2);

        if ($dominantColors[0][0]+$dominantColors[0][1]+$dominantColors[0][2] > $dominantColors[1][0]+$dominantColors[1][1]+$dominantColors[1][2])
            $dominantColor=$dominantColors[0];
        else
            $dominantColor=$dominantColors[1];
        echo ("\n./dump/".$i.'.jpg RGB:('.$dominantColor[0].','.$dominantColor[1].','.$dominantColor[2].')');

        $i++;
    }

    @imagejpeg($this->image_drawn,'./dump/boxes.jpg');
    @imagejpeg($this->image,'./dump/ori.jpg');
    @imagejpeg($this->clean_image,'./dump/clean.jpg');
    echo("\nimages dump in dump folder.");
    echo("\n######################### DUMP END #########################\n");
  }

  // return coordonates of a bound
  private function bound_to_coord($bound){
    $vertices=$bound->getVertices();
    $polygon=[
        'x1' => $vertices[0]->getX(),
        'y1' => $vertices[0]->getY(),
        'x2' => $vertices[1]->getX(),
        'y2' => $vertices[1]->getY(),
        'x3' => $vertices[2]->getX(),
        'y3' => $vertices[2]->getY(),
        'x4' => $vertices[3]->getX(),
        'y4' => $vertices[3]->getY(),
    ];
    return $polygon;
  }

  //Get document find bounds and stock them in this->text_blocks
  function get_document_bounds($annotation, $feature) {
    $bound = [];
    foreach ($annotation->getPages() as $page) {
        if ($feature == FEATURE_PAGE){
            $coord=$this->bound_to_coord($page->getBoundingBox());
            array_push($this->text_blocks , new TextBlock($this->path,$coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4']));
        }
        foreach ($page->getBlocks() as $block) {
            if ($feature == FEATURE_BLOCK) {
                $coord=$this->bound_to_coord($block->getBoundingBox());
                array_push($this->text_blocks , new TextBlock($this->path,$coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4']));
            }
            foreach ($block->getParagraphs() as $paragraph) {
                if ($feature == FEATURE_PARA){
                    $coord=$this->bound_to_coord($paragraph->getBoundingBox());
                    array_push($this->text_blocks , new TextBlock($this->path,$coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4']));
                }
                foreach ($paragraph->getWords() as $word) {
                    if ($feature == FEATURE_WORD){
                        $coord=$this->bound_to_coord($word->getBoundingBox());
                        array_push($this->text_blocks , new TextBlock($this->path,$coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4']));
                    }
                    foreach ($word->getSymbols() as $symbol) {
                        if ($feature == FEATURE_SYMBOL){
                            $coord=$this->bound_to_coord($symbol->getBoundingBox());
                            array_push($this->text_blocks , new TextBlock($this->path,$coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4']));
                        }
                    }
                }
            }
        }
    }
  }

  //Debug function to draw rectangle arrounf detected text boxes
  function draw_boxes ($fileName,$bounds) {

    $image = imagecreatefromjpeg($fileName);
    $black = imagecolorallocate($image, 0, 0, 0);

    $color=$black;
    foreach($bounds as $bound) {
        $vertices=$bound->getVertices();
        imageline ( $image ,  $vertices[0]->getX() ,  $vertices[0]->getY(),  $vertices[1]->getX() ,  $vertices[1]->getY() , $color );
        imageline ( $image ,  $vertices[1]->getX() ,  $vertices[1]->getY(),  $vertices[2]->getX() ,  $vertices[2]->getY() , $color );
        imageline ( $image ,  $vertices[2]->getX() ,  $vertices[2]->getY(),  $vertices[3]->getX() ,  $vertices[3]->getY() , $color );
        imageline ( $image ,  $vertices[3]->getX() ,  $vertices[3]->getY(),  $vertices[0]->getX() ,  $vertices[0]->getY() , $color );
        }
    $this->image_drawn=$image;
    @imagejpeg($this->image_drawn,'./dump/boxes.jpg');
  }

  function draw_boxes2 ($fileName, $color=0, $offset=0) {

    $image = imagecreatefromjpeg($fileName);

    $black = imagecolorallocate($image, 0, 0, 0);
    $red = imagecolorallocate($image, 255, 0, 0);

    if ($color == 0 )
      $linecolor=$black;
    else
      $linecolor=$red;

    foreach($this->text_blocks as $text_block) {
        imageline ( $image ,  $text_block->x1 -$offset, $text_block->y1 -$offset, $text_block->x2 +$offset, $text_block->y2 -$offset, $linecolor);
        imageline ( $image ,  $text_block->x2 +$offset, $text_block->y2 -$offset, $text_block->x3 +$offset , $text_block->y3+$offset , $linecolor);
        imageline ( $image ,  $text_block->x3 +$offset, $text_block->y3+$offset , $text_block->x4 -$offset, $text_block->y4+$offset , $linecolor);
        imageline ( $image ,  $text_block->x4 -$offset , $text_block->y4 +$offset, $text_block->x1 -$offset, $text_block->y1-$offset , $linecolor);
    }
    $this->image_drawn=$image;
    @imagejpeg($this->image_drawn,'dump/boxes'.basename($fileName).'-'.$offset.'-'.'.jpg');
  }
  

  /*function extract_bounds($fileName,$bounds) {
    $image = imagecreatefromjpeg($fileName);
    $images=[];
    foreach($bounds as $bound) {
        $vertices=$bound->getVertices();
        $rect=[
            'x' => $vertices[0]->getX()-1,
            'y' => $vertices[0]->getY()-1,
            'width' => abs($vertices[2]->getX()-$vertices[0]->getX()+2),
            'height' => abs($vertices[2]->getY()-$vertices[0]->getY()+2)
        ];
        $extract=imagecrop($image, $rect);
        array_push($images,$extract);
    }
    $this->extracts = $images;
  }*/

  // remove existing text in manga image
  function clean_image () {
      $this->clean_image = imagecreatefromjpeg($this->path);
      foreach ($this->text_blocks as $block) {
            $x1=$block->x1;
            $y1=$block->y1;
            $x2=$block->x2;
            $y2=$block->y2;
            $x3=$block->x3;
            $y3=$block->y3;
            $x4=$block->x4;
            $y4=$block->y4;
#          print_r($block);
          $r=$block->background_color_alt[0];
          $g=$block->background_color_alt[1];
          $b=$block->background_color_alt[2];
          $background = imagecolorallocate($this->clean_image, $r, $g, $b);
          $polygon=array($x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4);
          imagefilledpolygon($this->clean_image,$polygon,4,$background);
          @mkdir("clean");
          $this->clean_path="clean/".basename($this->path);          
          imagejpeg($this->clean_image,$this->clean_path); 
      }
  }

  function merge_similar_bloc($tolerance=20){

    for ($i=0;isset($this->text_blocks[$i]); $i++){
        for ($j=$i+1; isset($this->text_blocks[$j]);$j++) {
            $ix1=$this->text_blocks[$i]->x1;
            $iy1=$this->text_blocks[$i]->y1;
            $ix2=$this->text_blocks[$i]->x2;
            $iy2=$this->text_blocks[$i]->y2;
            $ix3=$this->text_blocks[$i]->x3;
            $iy3=$this->text_blocks[$i]->y3;
            $ix4=$this->text_blocks[$i]->x4;
            $iy4=$this->text_blocks[$i]->y4;
            $iavgx_bottom=($ix4+$ix3)/2;
            $iavgy_bottom=($iy4+$iy3)/2;
            $iavgx_top=($ix1+$ix2)/2;
            $iavgy_top=($iy1+$iy2)/2;   
            $jx1=$this->text_blocks[$j]->x1;
            $jy1=$this->text_blocks[$j]->y1;
            $jx2=$this->text_blocks[$j]->x2;
            $jy2=$this->text_blocks[$j]->y2;
            $jx3=$this->text_blocks[$j]->x3;
            $jy3=$this->text_blocks[$j]->y3;
            $jx4=$this->text_blocks[$j]->x4;
            $jy4=$this->text_blocks[$j]->y4;
            $javgx_bottom=($jx4+$jx3)/2;
            $javgy_bottom=($jy4+$jy3)/2;
            $javgx_top=($jx1+$jx2)/2;
            $javgy_top=($jy1+$jy2)/2;             

            $block_distance_y=$javgy_top-$iavgy_bottom;
                if ((($block_distance_y < $tolerance) && ($block_distance_y >0)) && ((($ix4 -$tolerance < $jx1) && ($ix3+$tolerance > $jx2)) || (($ix4 +$tolerance> $jx1) && ($ix3 -$tolerance < $jx2))))
                    {
                    // both x and y says it is the same block
                    $this->text_blocks[$i]->x1=(min($jx1,$ix1));
                    $this->text_blocks[$i]->iy1=$iy1;
                    $this->text_blocks[$i]->x2=(max($jx2,$ix2));
                    $this->text_blocks[$i]->y2=$iy2;
                    $this->text_blocks[$i]->x3=(max($jx3,$ix3));
                    $this->text_blocks[$i]->y3=$jy3;
                    $this->text_blocks[$i]->x4=(min($jx4,$ix4));
                    $this->text_blocks[$i]->y4=$jy4;

                    unset($this->text_blocks[$j]);
                    $this->text_blocks = array_values($this->text_blocks);
                    $j--;
                    }
                }
        }
    }

  //write translated text over cleaned image
  function insert_translations() {
    $this->final_image = imagecreatefromjpeg($this->clean_path);
    foreach ($this->text_blocks as $block) {
        $black = imagecolorallocate($this->final_image, 0, 0, 0);
        $red = imagecolorallocate($this->final_image, 255, 0, 0);

        $translation_width=$block->translation_width;
        $translation_height=$block->translation_height;

        $block_center_x=($block->x1+$block->x2+$block->x3+$block->x4)/4;
        $block_center_y=($block->y1+$block->y2+$block->y3+$block->y4)/4;

        
        //imageline (  $this->final_image ,  $block_center_x -60 , $block_center_y , $block_center_x+60 , $block_center_y ,  $red );
        //imageline (  $this->final_image ,  $block_center_x , $block_center_y -60, $block_center_x , $block_center_y +60,  $red );

        //Coordonates to place text centered
        $insert_x=$block_center_x-($translation_width/2);
        $insert_y=$block_center_y-($translation_height/2)+$block->translation_top_offset;
        //imageline (  $this->final_image ,  $insert_x -60 , $insert_y , $insert_x+60 , $insert_y ,  $black );
        //imageline (  $this->final_image ,  $insert_x , $insert_y -60, $insert_x , $insert_y +60,  $black );
        //echo "translation_width:$translation_width translation_height:$translation_height block->translation_top_offset:".$block->translation_top_offset."\n";

        //$insert_y=$block_center_y+($translation_height/2);
        //echo "______________________\n";
        //echo "x1:". $block->x1." y1:".$block->y1."\n";
        //echo "text:".  $block->formatted_text."\n";

        imagettftext (
          $this->final_image,
          $block->font_size,
          $block->text_angle,
          $insert_x,
          $insert_y,
          $black,
          $block->font,
          $block->formatted_text );
    @mkdir("translated");
        $this->translated_path="translated/".basename($this->path);          
        imagejpeg($this->final_image,$this->translated_path); 
    }
  }
}
