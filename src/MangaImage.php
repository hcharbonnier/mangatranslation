<?php 
namespace mangatranslation;

#require __DIR__ . '/vendor/autoload.php';

require_once("funtions.php");

# imports the Google Cloud client library
use Google\Cloud\Vision\V1\ImageAnnotatorClient;

define ('FEATURE_PAGE', 1);
define ('FEATURE_BLOCK', 2);
define ('FEATURE_PARA', 3);
define ('FEATURE_WORD', 4);
define ('FEATURE_SYMBOL', 5);


class MangaImage
{
  public $path=null;
  private $image=null;
  private $image_drawn=null;
  public $text_blocks=[];
  public $textbox_merge_tolerance=20;
  private $cleaned_image=null;
  private $final_image=null;
  private $response=null;
  private $annotation=null;
  private $denoiser=array("enable" => false);
  public $output_file=null;
  
  function __construct($path, $output_file) {
    $this->path = $path;
    $this->output_file=$output_file;
  }
  
  function load(){
    if ($this->denoiser['enable'])
      $this->denoise();
    $this->image = imagecreatefromany($this->path);
    # performs label detection on the image file
    $imageAnnotator = new ImageAnnotatorClient();
    $this->response = $imageAnnotator->textDetection(file_get_contents($this->path));
    $this->annotation = $this->response->getFullTextAnnotation();
    $this->get_document_bounds($this->annotation, FEATURE_BLOCK);
    //$this->draw_boxes2(cloneImg($this->image));
    $this->merge_similar_bloc_y($this->textbox_merge_tolerance);
    $this->merge_similar_bloc_x($this->textbox_merge_tolerance);
    $i=0;
    foreach ($this->text_blocks as $text_block) {
      $text_block->load();
    }
    //$this->draw_boxes2(cloneImg($this->image),1,1);
    $this->clean_image();
    $this->insert_translations();
    imagewrite($this->final_image,$this->output_file); 
  }

  private function denoise(){
    //@mkdir(sys_get_temp_dir()."/mangatranslation");
    //$output_file=sys_get_temp_dir()."/mangatranslation".basename($this->path);
    @mkdir("tmp/");
    $output_file="tmp/".basename($this->path);
    
    $cmd=str_replace($this->denoiser['inputfilepattern'], $this->path, $this->denoiser['command']);
    $cmd=str_replace($this->denoiser['outputfilepattern'], $output_file, $cmd);
    
    exec($cmd,$output, $return_status);
    if (file_exists("$output_file"))
    $this->path = $output_file;
    else
    {
        echo $this->path.": denoiser error:\n";
        print_r($output);
    }
}
  
  //Only for Before OCR, not on final image
  public function external_denoiser( $denoiser_cmd, $outputfilepattern="_DENOISEROUTPUTFILE_" ,$inputfilepattern="_DENOISERINPUTFILE_") {
    $this->denoiser['enable']= true;
    $this->denoiser['command']=$denoiser_cmd;
    $this->denoiser['inputfilepattern']=$inputfilepattern;
    $this->denoiser['outputfilepattern']=$outputfilepattern;
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
        array_push($this->text_blocks , new TextBlock(basename($this->path),$this->image,$coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4']));
      }
      foreach ($page->getBlocks() as $block) {
        if ($feature == FEATURE_BLOCK) {
          $coord=$this->bound_to_coord($block->getBoundingBox());
          array_push($this->text_blocks , new TextBlock(basename($this->path),$this->image,$coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4']));
        }
        foreach ($block->getParagraphs() as $paragraph) {
          if ($feature == FEATURE_PARA){
            $coord=$this->bound_to_coord($paragraph->getBoundingBox());
            array_push($this->text_blocks , new TextBlock(basename($this->path),$this->image,$coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4']));
          }
          foreach ($paragraph->getWords() as $word) {
            if ($feature == FEATURE_WORD){
              $coord=$this->bound_to_coord($word->getBoundingBox());
              array_push($this->text_blocks , new TextBlock(basename($this->path),$this->image,$coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4']));
            }
            foreach ($word->getSymbols() as $symbol) {
              if ($feature == FEATURE_SYMBOL){
                $coord=$this->bound_to_coord($symbol->getBoundingBox());
                array_push($this->text_blocks , new TextBlock(basename($this->path),$this->image,$coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4']));
              }
            }
          }
        }
      }
    }
  }
  
  //Debug function to draw rectangle arrounf detected text boxes
  function draw_boxes2 ($image, $color=0, $offset=0) {
    
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
    @mkdir("dump");
    @imagejpeg($this->image_drawn,'dump/boxes'.basename($fileName).'-'.$offset.'-'.'.jpg');
  }
  
  function draw_boxes_inimage ($image, $color=0, $offset=0) {
    
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
  }
  // remove existing text in manga image
  function clean_image () {
    $this->cleaned_image = cloneImg($this->image);
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
      $background = imagecolorallocate($this->cleaned_image, $r, $g, $b);
      $polygon=array($x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4);
      imagefilledpolygon($this->cleaned_image,$polygon,4,$background);
    }
  }
  
  function merge_similar_bloc_y($tolerance=20){
    
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
  function merge_similar_bloc_x($tolerance=20){
    
    for ($i=(count($this->text_blocks)-1);$i>=0; $i--){
      for ($j=$i-1; $j>=0;$j--) {
        $ix1=$this->text_blocks[$i]->ordered[0];
        $iy1=$this->text_blocks[$i]->ordered[1];
        $ix2=$this->text_blocks[$i]->ordered[2];
        $iy2=$this->text_blocks[$i]->ordered[3];
        $ix3=$this->text_blocks[$i]->ordered[4];
        $iy3=$this->text_blocks[$i]->ordered[5];
        $ix4=$this->text_blocks[$i]->ordered[6];
        $iy4=$this->text_blocks[$i]->ordered[7];
        $iavgx_bottom=($ix4+$ix3)/2;
        $iavgy_bottom=($iy4+$iy3)/2;
        $iavgx_top=($ix1+$ix2)/2;
        $iavgy_top=($iy1+$iy2)/2;   
        $iavgx_right=($ix2+$ix3)/2;
        $jx1=$this->text_blocks[$j]->ordered[0];
        $jy1=$this->text_blocks[$j]->ordered[1];
        $jx2=$this->text_blocks[$j]->ordered[2];
        $jy2=$this->text_blocks[$j]->ordered[3];
        $jx3=$this->text_blocks[$j]->ordered[4];
        $jy3=$this->text_blocks[$j]->ordered[5];
        $jx4=$this->text_blocks[$j]->ordered[6];
        $jy4=$this->text_blocks[$j]->ordered[7];
        $javgx_bottom=($jx4+$jx3)/2;
        $javgy_bottom=($jy4+$jy3)/2;
        $javgx_top=($jx1+$jx2)/2;
        $javgy_top=($jy1+$jy2)/2;
        $javgx_left=($jx1+$jx4)/2;   
              
        
        $block_distance_x=$javgx_left-$iavgx_right;
        if ((($block_distance_x < $tolerance) && ($block_distance_x >0)) &&
              (
                (($iy2 -$tolerance < $jy1) && ($iy3+$tolerance > $jy4)) ||
                (($iy2 +$tolerance> $jy1) && ($iy3 -$tolerance < $jy4))
              )
            )
        {
          // both x and y says it is the same block
          $this->text_blocks[$i]->x1=$ix1;
          $this->text_blocks[$i]->y1=min($iy1,$jy1);
          $this->text_blocks[$i]->x2=$jx2;
          $this->text_blocks[$i]->y2=(min($jy2,$iy2));
          $this->text_blocks[$i]->x3=$jx3;
          $this->text_blocks[$i]->y3=(max($jy3,$iy3));;
          $this->text_blocks[$i]->x4=$ix4;
          $this->text_blocks[$i]->y4=(max($jy4,$iy4));;
          
          unset($this->text_blocks[$j]);
          $this->text_blocks = array_values($this->text_blocks);
          $j--;
        }
      }
    }
  }
  
  //write translated text over cleaned image
  function insert_translations() {
    $this->final_image = cloneImg($this->cleaned_image);
    foreach ($this->text_blocks as $block) {
      $black = imagecolorallocate($this->final_image, 0, 0, 0);
      $red = imagecolorallocate($this->final_image, 255, 0, 0);
      $yellow = imagecolorallocate($this->final_image, 255, 255, 0);
      
      $translation_width=$block->translation_width;
      $translation_height=$block->translation_height;

      $block_height=round(distance($block->ori['x4'],$block->ori['y4'],$block->ori['x1'],$block->ori['y1']));
      $block_width=round(distance($block->ori['x1'],$block->ori['y1'],$block->ori['x2'],$block->ori['y2']));

      $Ix=$block->ori['x1']+($block_width-$translation_width)/2;
      $Iy=$block->ori['y1']+$block->translation_top_offset+($block_height-$translation_height)/2 ;

      $insert=rotate($Ix,$Iy,$block->ori['x1'],$block->ori['y1'],$block->text_angle);

      imagettftext (
        $this->final_image,
        $block->font_size,
        $block->text_angle,
        //$insert_x,
        //$insert_y,
        $insert[0],
        $insert[1],
        $black,
        $block->font,
        $block->formatted_text );
      }
    }

    

    
  }
  