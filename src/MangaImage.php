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
    $this->draw_boxes2($this->path,1,2);

    
    foreach ($this->text_blocks as $text_block) {
        $text_block->load();
    }
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
    @imagejpeg($this->image_drawn,"./dump/boxes$color.jpg");
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

  function merge_similar_bloc($tolerance=25){
      $new_blocks=[];
      foreach($this->text_blocks as $text_block){
        //echo "#############################################################################################\n";
        $x1=$text_block->x1;
        $y1=$text_block->y1;
        $x2=$text_block->x2;
        $y2=$text_block->y2;
        $x3=$text_block->x3;
        $y3=$text_block->y3;
        $x4=$text_block->x4;
        $y4=$text_block->y4;
        $avgx_bottom=($x4+$x3)/2;
        $avgy_bottom=($y4+$y3)/2;
        $avgx_top=($x1+$x2)/2;
        $avgy_top=($y1+$y2)/2;

        //echo "x1:$x1 y1:$y1 x2:$x2 y2:$y2 x3:$x3 y3:$y3 x4:$x4 y4:$y4  avgx_bottom:$avgx_bottom avgy_bottom:$avgy_bottom avgx_to:$avgx_top avgy_to:$avgy_top \n";
        if (! isset($px1)) {
            $px1 = $x1;
            $py1 = $y1;
            $px2 = $x2;
            $py2 = $y2;
            $px3 = $x3;
            $py3 = $y3;
            $px4 = $x4;
            $py4 = $y4;
            $pavgx_bottom=$avgx_bottom;
            $pavgy_bottom=$avgy_bottom;
            $pavgx_top=$avgx_top;
            $pavgy_top=$avgy_top;
        } else {
            //echo "px1:$px1 py1:$py1 px2:$px2 py2:$py2 px3:$px3 py3:$py3 px4:$px4 py4:$py4  pavgx_bottom:$pavgx_bottom pavgy_bottom:$pavgy_bottom pavgx_top:$pavgx_top pavgy_top:$pavgy_top \n";
            $block_distance_y=$avgy_top-$pavgy_bottom;
            //echo "block_distance_y:$block_distance_y\n";
            if ((($block_distance_y < $tolerance) && ($block_distance_y >0)) && ((($px4 -$tolerance < $x1) && ($px3+$tolerance > $x2)) || (($px4 +$tolerance> $x1) && ($px3 -$tolerance < $x2))))
                {
                //echo "same block\n";
                // both x and y says it is the same block
                $px1=(min($x1,$px1));
                $py1=$py1;
                $px2=(max($x2,$px2));
                $py2=$py2;
                $px3=(max($x3,$px3));
                $py3=$y3;
                $px4=(min($x4,$px4));
                $py4=$y4;
                $pavgx_bottom=($px4+$px3)/2;
                $pavgy_bottom=($py4+$py3)/2;
                $pavgx_top=($px1+$px2)/2;
                $pavgy_top=($py1+$py2)/2;
                } else {
                    array_push($new_blocks,new TextBlock($this->path, $px1,$py1,$px2,$py2,$px3,$py3,$px4,$py4));
                    $px1 = $x1;
                    $py1 = $y1;
                    $px2 = $x2;
                    $py2 = $y2;
                    $px3 = $x3;
                    $py3 = $y3;
                    $px4 = $x4;
                    $py4 = $y4;
                    $pavgx_bottom=$avgx_bottom;
                    $pavgy_bottom=$avgy_bottom;
                    $pavgx_top=$avgx_top;
                    $pavgy_top=$avgy_top;       
                }
            }
        }
        array_push($new_blocks,new TextBlock($this->path, $x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4));
        $this->text_blocks=$new_blocks;
  }

  //write translated text over cleaned image
  function insert_translations () {
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