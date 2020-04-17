<?php 
namespace hcharbonnier\mangatranslation;

#require __DIR__ . '/vendor/autoload.php';

require_once(__DIR__."/funtions.php");

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
  private $image_width=10;
  private $image_height=10;
  private $image_drawn=null;
  public $text_blocks=[];
  public $textbox_merge_tolerance=20;
  private $cleaned_image=null;
  private $cleaned_image_path=null;
  private $final_image=null;
  private $final_image_path=null;
  private $response=null;
  private $annotation=null;
  private $denoiser=array("enable" => false);
  private $translated=false;
  //public $output_file=null;
  
  function __construct($path) {
    $this->path = $path;
    $this->image = imagecreatefromany($this->path);
    $this->image_width=imagesx($this->get_image());
    $this->image_height=imagesy($this->get_image());
  }
  

  function load(){
   // if ($this->denoiser['enable'])
   //   $this->denoise();
    # performs label detection on the image file
    
    //$this->draw_boxes2(cloneImg($this->image));
    //$i=0;
    //$this->draw_boxes2(cloneImg($this->image),1,1);
    //$this->clean_image();
    //$this->insert_translations();
  }

  public function detect_block(){
    $imageAnnotator = new ImageAnnotatorClient();
    $this->response = $imageAnnotator->textDetection(file_get_contents($this->path));
    $this->annotation = $this->response->getFullTextAnnotation();
    if ($this->annotation != null){
      $this->get_document_bounds($this->annotation, FEATURE_BLOCK);
    }
  }

  public function get_width(){
    return $this->image_width;
  }

  public function get_height(){
    return $this->image_height;
  }
  public function get_cleaned_image_path(){
    return $this->cleaned_image_path;
  }

/*  private function load_textblock(){
    foreach ($this->text_blocks as $text_block) {
      $text_block->process();
    }
    $this->draw_boxes2(cloneImg($this->image),2,0);
  }*/

  public function translate($language="en"){
    foreach ($this->text_blocks as $text_block) {
      $text_block->translate($language);
    }
    $this->translated=true;
  }

  public function is_translated(){
    return $this->translated;
  }

  public function ocr(){
    foreach ($this->text_blocks as $text_block) {
      $text_block->ocr();
    }
  }

  public function auto_merge_blocks(){
    $this->draw_boxes2(cloneImg($this->get_image()),0,0);
    $this->merge_similar_bloc(20);
    $this->draw_boxes2(cloneImg($this->get_image()),1,1);
  }

  /*private function process_text_blocks(){
    foreach ($this->text_blocks as $text_block) {
      $text_block->process();
    }
    $this->draw_boxes2(cloneImg($this->image),2,0);
  }*/

  public function export($path,$quality=75){
    imagewrite($this->get_final_image(),$path,$quality);
  }

  public function get_blocks(){
    $i=0;
    $res=array();
    foreach ($this->text_blocks as $text_block) {
      $res[$i]= $text_block->get_block();
      $i++;
    }
    return $res;
  }

  public function set_block($id,$x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4){
    $this->text_blocks[$id]->set_block($x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4);
  }

  function get_image(){
    if (@get_resource_type($this->image) != "gd")
      $this->image=imagecreatefromany($this->path);
    return $this->image;
  }

  public function add_block($x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4,$calculate_angle){
    if (! isset($this->text_blocks))
      $this->text_blocks=array();
    if ( !(
      (distance ($x1,$y1,$x2,$y2) <4) ||
      (distance ($x2,$y2,$x3,$y3) <4) ||
      (distance ($x3,$y3,$x4,$y4) <4) ||
      (distance ($x4,$y4,$x1,$y1) <4) 
    )){
    array_push($this->text_blocks , new TextBlock($this->path,$this->get_image(),$x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4,$calculate_angle));
    } else {
      echo "block to small!!!";
    }
  }

  public function del_block($id){
    array_splice($this->text_blocks,$id,1);
  }

  public function del_blocks(){
    unset($this->text_blocks);
  }

  public function get_block_translation($id) {
    return $this->text_blocks[$id]->get_translation();
  }

  public function get_block_ocr($id) {
    return $this->text_blocks[$id]->get_ocr();
  }

  public function get_block_image_path($id) {
    return $this->text_blocks[$id]->get_image_path();
  }

  public function set_block_translation($id,$translation) {
    return $this->text_blocks[$id]->set_translation($translation);
  }
  public function get_block_translation_paragraph($id) {
    return $this->text_blocks[$id]->get_block_translation_paragraph($id);
  }

  public function get_cleaned_image(){
    if (@get_resource_type($this->cleaned_image) != "gd")
      $this->cleaned_image=imagecreatefromany($this->cleaned_image_path);
    return $this->cleaned_image;
  }

  public function get_final_image(){
    if (@get_resource_type($this->final_image) != "gd")
      $this->final_image=imagecreatefromany($this->final_image_path);
    return $this->final_image;
  }
  public function get_text_angle($id){
    return $this->text_blocks[$id]->get_text_angle();
  }
  public function get_font_size($id){
    return $this->text_blocks[$id]->get_font_size();
  }
  public function get_image_path(){
    return $this->path;
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
        $this->add_block($coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4'],true);
      }
      foreach ($page->getBlocks() as $block) {
        if ($feature == FEATURE_BLOCK) {
          $coord=$this->bound_to_coord($block->getBoundingBox());
          $this->add_block($coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4'],true);
        }
        foreach ($block->getParagraphs() as $paragraph) {
          if ($feature == FEATURE_PARA){
            $coord=$this->bound_to_coord($paragraph->getBoundingBox());
            $this->add_block($coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4'],true);
          }
          foreach ($paragraph->getWords() as $word) {
            if ($feature == FEATURE_WORD){
              $coord=$this->bound_to_coord($word->getBoundingBox());
              $this->add_block($coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4'],true);
            }
            foreach ($word->getSymbols() as $symbol) {
              if ($feature == FEATURE_SYMBOL){
                $coord=$this->bound_to_coord($symbol->getBoundingBox());
                $this->add_block($coord['x1'],$coord['y1'],$coord['x2'],$coord['y2'],$coord['x3'],$coord['y3'],$coord['x4'],$coord['y4'],true);
              }
            }
          }
        }
      }
    }
  }
  
  //Debug function to draw rectangle arrounf detected text boxes
  function draw_boxes2 ($image, $colorid=0, $offset=0) {
    
    $color[0]= imagecolorallocate($image, 0, 0, 0);
    $color[1]= imagecolorallocate($image, 255, 0, 0);
    $color[2]= imagecolorallocate($image, 0, 255, 0);
    $color[3]= imagecolorallocate($image, 0, 0, 255);
    $color[4]= imagecolorallocate($image, 255, 255, 0);
    
    $linecolor=$color[$colorid];
    
    foreach($this->text_blocks as $text_block) {
      imageline ( $image ,  $text_block->ordered['x1'] -$offset, $text_block->ordered['y1'] -$offset, $text_block->ordered['x2'] +$offset, $text_block->ordered['y2'] -$offset, $linecolor);
      imageline ( $image ,  $text_block->ordered['x2'] +$offset, $text_block->ordered['y2'] -$offset, $text_block->ordered['x3'] +$offset, $text_block->ordered['y3']+$offset , $linecolor);
      imageline ( $image ,  $text_block->ordered['x3'] +$offset, $text_block->ordered['y3'] +$offset, $text_block->ordered['x4'] -$offset, $text_block->ordered['y4']+$offset , $linecolor);
      imageline ( $image ,  $text_block->ordered['x4'] -$offset, $text_block->ordered['y4'] +$offset, $text_block->ordered['x1'] -$offset, $text_block->ordered['y1']-$offset , $linecolor);
    }
    $this->image_drawn=$image;
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
  function clean_raw() {
    $this->cleaned_image = cloneImg($this->get_image());
    foreach ($this->text_blocks as $block) {
      $x1=$block->x1;
      $y1=$block->y1;
      $x2=$block->x2;
      $y2=$block->y2;
      $x3=$block->x3;
      $y3=$block->y3;
      $x4=$block->x4;
      $y4=$block->y4;
      $r=$block->background_color_alt[0];
      $g=$block->background_color_alt[1];
      $b=$block->background_color_alt[2];
      $background = imagecolorallocate($this->cleaned_image, $r, $g, $b);
      $polygon=array($x1,$y1,$x2,$y2,$x3,$y3,$x4,$y4);
      imagefilledpolygon($this->cleaned_image,$polygon,4,$background);
      $this->cleaned_image_path="uploads/".microtime().".jpg";
      imagewrite($this->cleaned_image,$this->cleaned_image_path,$quality=100);
    }
  }

  function merge_similar_bloc($tolerance) {
    
    for ($i=0;isset($this->text_blocks[$i]); $i++){
      for ($j=$i+1; isset($this->text_blocks[$j]);$j++) {
        $ix1=$this->text_blocks[$i]->ordered['x1'];
        $iy1=$this->text_blocks[$i]->ordered['y1'];
        $ix2=$this->text_blocks[$i]->ordered['x2'];
        $iy2=$this->text_blocks[$i]->ordered['y2'];
        $ix3=$this->text_blocks[$i]->ordered['x3'];
        $iy3=$this->text_blocks[$i]->ordered['y3'];
        $ix4=$this->text_blocks[$i]->ordered['x4'];
        $iy4=$this->text_blocks[$i]->ordered['y4'];
        $iavgx_bottom=($ix4+$ix3)/2;
        $iavgy_bottom=($iy4+$iy3)/2;
        $iavgx_top=($ix1+$ix2)/2;
        $iavgy_top=($iy1+$iy2)/2;
        $iavgx_left=($ix1+$ix4)/2;
        $iavgy_left=($iy1+$iy4)/2;
        $iavgx_right=($ix2+$ix3)/2;  
        $iavgy_right=($iy2+$iy3)/2;

        $jx1=$this->text_blocks[$j]->ordered['x1'];
        $jy1=$this->text_blocks[$j]->ordered['y1'];
        $jx2=$this->text_blocks[$j]->ordered['x2'];
        $jy2=$this->text_blocks[$j]->ordered['y2'];
        $jx3=$this->text_blocks[$j]->ordered['x3'];
        $jy3=$this->text_blocks[$j]->ordered['y3'];
        $jx4=$this->text_blocks[$j]->ordered['x4'];
        $jy4=$this->text_blocks[$j]->ordered['y4'];
        $javgx_bottom=($jx4+$jx3)/2;
        $javgy_bottom=($jy4+$jy3)/2;
        $javgx_top=($jx1+$jx2)/2;
        $javgy_top=($jy1+$jy2)/2;
        $javgx_left=($jx1+$jx4)/2;
        $javgy_left=($jy1+$jy4)/2;
        $javgx_right=($jx2+$jx3)/2;  
        $javgy_right=($jy2+$jy3)/2;  
         
        //Merge overring_block
        $block_distance_y=$javgy_top-$iavgy_bottom;
        if ((($jx1 +$tolerance >$ix4) && ($jx1 <$ix3+$tolerance) && ($jy1+$tolerance > $iy1) && ($jy1 < $iy4+$tolerance)) ||
            (($jx2 +$tolerance >$ix4) && ($jx2 <$ix3+$tolerance) && ($jy2+$tolerance > $iy1) && ($jy2 < $iy4+$tolerance)) ||
            (($ix1 +$tolerance >$jx4) && ($ix1 <$jx3+$tolerance) && ($iy1+$tolerance > $jy1) && ($iy1 < $jy4+$tolerance)) ||
            (($ix2 +$tolerance >$jx4) && ($ix2 <$jx3+$tolerance) && ($iy2+$tolerance > $jy1) && ($iy2 < $jy4+$tolerance))
           )
        {
          // both x and y says it is the same block
          $this->text_blocks[$i]->ordered['x1']=(min($jx1,$ix1));
          $this->text_blocks[$i]->ordered['y1']=(min($jy1,$iy1));
          $this->text_blocks[$i]->ordered['x2']=(max($jx2,$ix2));
          $this->text_blocks[$i]->ordered['y2']=(min($jy2,$iy2));
          $this->text_blocks[$i]->ordered['x3']=(max($jx3,$ix3));
          $this->text_blocks[$i]->ordered['y3']=(max($jy3,$iy3));
          $this->text_blocks[$i]->ordered['x4']=(min($jx4,$ix4));
          $this->text_blocks[$i]->ordered['y4']=(max($jy4,$iy4));
          $this->text_blocks[$i]->reorderpoint_to_ori();
          $this->text_blocks[$i]->calculate_angle = $this->text_blocks[$i]->calculate_angle && $this->text_blocks[$j]->calculate_angle;

          array_splice($this->text_blocks,$j,1);
          $this->text_blocks = array_values($this->text_blocks);
          $j--;
          continue;
        }

        //Merge block vertically (occidental reading style)
        if ((($block_distance_y < $tolerance) && ($block_distance_y >0)) && ((($ix4 -$tolerance < $jx1) && ($ix3+$tolerance > $jx2)) || (($ix4 +$tolerance> $jx1) && ($ix3 -$tolerance < $jx2))))
        {
          // both x and y says it is the same block
          $this->text_blocks[$i]->ordered['x1']=(min($jx1,$ix1));
          $this->text_blocks[$i]->ordered['y1']=(min($jy1,$iy1));
          $this->text_blocks[$i]->ordered['x2']=(max($jx2,$ix2));
          $this->text_blocks[$i]->ordered['y2']=(min($jy2,$iy2));
          $this->text_blocks[$i]->ordered['x3']=(max($jx3,$ix3));
          $this->text_blocks[$i]->ordered['y3']=(max($jy3,$iy3));
          $this->text_blocks[$i]->ordered['x4']=(min($jx4,$ix4));
          $this->text_blocks[$i]->ordered['y4']=(max($jy4,$iy4));
          $this->text_blocks[$i]->reorderpoint_to_ori();
          $this->text_blocks[$i]->calculate_angle = $this->text_blocks[$i]->calculate_angle && $this->text_blocks[$j]->calculate_angle;
          
          array_splice($this->text_blocks,$j,1);
          $this->text_blocks = array_values($this->text_blocks);
          $j--;
          continue;
        }

        //Merge block horizontally (Asian reading style )
        $block_distance_x=min($javgx_left-$iavgx_right, $javgx_right-$iavgx_left);
        if ((($block_distance_x < $tolerance) && ($block_distance_x >0)) &&
              (
                (($iy2 -$tolerance < $jy1) && ($iy3 + $tolerance > $jy4)) ||
                (($iy2 +$tolerance > $jy1) && ($iy3 - $tolerance < $jy4)) ||
                (($jy2 -$tolerance < $iy1) && ($jy3 + $tolerance > $iy4)) ||
                (($jy2 +$tolerance > $iy1) && ($jy3 - $tolerance < $iy4))
              )
            )
        {
          // both x and y says it is the same block
          $this->text_blocks[$i]->ordered['x1']=min($ix1,$jx1);
          $this->text_blocks[$i]->ordered['y1']=min($iy1,$jy1);
          $this->text_blocks[$i]->ordered['x2']=max($ix2,$jx2);
          $this->text_blocks[$i]->ordered['y2']=min($jy2,$iy2);
          $this->text_blocks[$i]->ordered['x3']=max($ix3,$jx3);
          $this->text_blocks[$i]->ordered['y3']=max($jy3,$iy3);
          $this->text_blocks[$i]->ordered['x4']=min($ix4,$jx4);
          $this->text_blocks[$i]->ordered['y4']=max($jy4,$iy4);
          $this->text_blocks[$i]->reorderpoint_to_ori();
          $this->text_blocks[$i]->calculate_angle = $this->text_blocks[$i]->calculate_angle && $this->text_blocks[$j]->calculate_angle;
          
          array_splice($this->text_blocks,$j,1);
          $this->text_blocks = array_values($this->text_blocks);
          $j--;
          continue;
        }
      }
    }
  }

  function merge_blocks($id1,$id2) {
    $block1=$this->text_blocks[$id1];
    $block2=$this->text_blocks[$id2];

    $block1->ordered['x1']=(min($block1->ordered['x1'],$block2->ordered['x1']));
    $block1->ordered['y1']=(min($block1->ordered['y1'],$block2->ordered['y1']));
    $block1->ordered['x2']=(max($block1->ordered['x2'],$block2->ordered['x2']));
    $block1->ordered['y2']=(min($block1->ordered['y2'],$block2->ordered['y2']));
    $block1->ordered['x3']=(max($block1->ordered['x3'],$block2->ordered['x3']));
    $block1->ordered['y3']=(max($block1->ordered['y3'],$block2->ordered['y3']));
    $block1->ordered['x4']=(min($block1->ordered['x4'],$block2->ordered['x4']));
    $block1->ordered['y4']=(max($block1->ordered['y4'],$block2->ordered['y4']));
    $block1->reorderpoint_to_ori();
    $block1->calculate_angle = $block1->calculate_angle && $block2->calculate_angle;

    array_splice($this->text_blocks,$id2,1);
    $this->text_blocks = array_values($this->text_blocks);
  }


  

  //write translated text over cleaned image
  function write_translation() {
    $this->final_image = cloneImg($this->get_cleaned_image());
    foreach ($this->text_blocks as $block) {
      $black = imagecolorallocate($this->final_image, 0, 0, 0);
      $red = imagecolorallocate($this->final_image, 255, 0, 0);
      $green = imagecolorallocate($this->final_image, 0, 255, 0);
      $yellow = imagecolorallocate($this->final_image, 255, 255, 0);
      
      $translation_width=$block->translation_width;
      $translation_height=$block->translation_height;

      $block_height=round(distance($block->x4,$block->y4,$block->x1,$block->y1));
      $block_width=round(distance($block->x1,$block->y1,$block->x2,$block->y2));

      $Ix=$block->x1+($block_width-$translation_width)/2;
      $Iy=$block->y1+$block->translation_top_offset+($block_height-$translation_height)/2 ;
      $tmpx=$Ix;
      $tmpy=$Iy;

      if ($block->text_angle !=0) {
        $insert=rotate($Ix,$Iy, $block->x1,$block->y1,0- $block->text_angle);
        $Ix = $insert[0];
        $Iy = $insert[1];
      }

      imagettftext (
        $this->final_image,
        $block->font_size,
        $block->text_angle,
        $Ix,
        $Iy,
        $black,
        $block->font,
        $block->formatted_text );
      }

      
      $this->final_image_path="uploads/".microtime().".jpg";
      imagewrite($this->final_image,$this->final_image_path,$quality=100);
    }
  }
  