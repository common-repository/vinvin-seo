<?php
/**
 * Plugin Name:       Vinvin SEO
 * Plugin URI:        https://vinvin.dev/
 * Description:       Do your SEO properly
 * Version:           2.0.0
 * Requires at least: 5.0
 * text-domain: vinvinseo
 * Donate link: https://vinvin.dev
 * author URI: https://vinvin.dev
*/

/*

- Ajout d'un param pour disable BHM
- Antidaté les post

*/


defined('ABSPATH') or die('No way !');


require_once 'vendor/autoload.php';


add_action('page_generator_pro_generate_content_finished' , 'vinvin_turn_post_to_draft' , 10, 5 );
function vinvin_turn_post_to_draft( $post_id, $group_id, $settings, $index, $test_mode ){

  // test if post_id is not page_on_front 
  $page_on_front = get_option('page_on_front');
  if( $post_id != $page_on_front){
    wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
  }
  
}

add_action('the_content', 'vinvin_the_content');
function vinvin_the_content( $content ){


      global $post;
      if( is_single() && isset($post->ID)){
        return $content.'<div style="display:none" id="v_id_post">'.$post->ID.'</div>';
      }
      return $content;
}


add_action('vinvin_toto' , 'vinvin_save_post_wpauto' , 10 , 1);
function vinvin_save_post_wpauto( $args ){

  // Recupération de la meta
  $post_id = $args['post_id'];
  $vinvin_settings = get_option('vinvin_seo');
  $vinvin_original_post_id = get_post_meta( $post_id , 'vinvin_id_post' , false );
  //error_log('Original post ID ' . json_encode($vinvin_original_post_id) . ' - POST ID ' . $post_id . ' lang ' . $vinvin_settings['from_lang']);
  //die();

  //print_r($vinvin_original_post_id); die();
  PLL()->model->post->set_language($post_id, $vinvin_settings['from_lang'] );
  PLL()->model->post->set_language($vinvin_original_post_id, $vinvin_settings['default_lang'] );

  //PLL()->model->post->save_translations( 1059, array( 'en' => 1092 ) );
  PLL()->model->post->save_translations($vinvin_original_post_id, array( 'en' => $post_id ) );
  error_log('Original post ID ' . json_encode($vinvin_original_post_id) . ' - POST ID ' . $post_id . ' lang ' . $vinvin_settings['from_lang']);
//  PLL()->model->post->save_translations($vinvin_original_post_id, array( $vinvin_settings['default_lang'] => $vinvin_original_post_id ) );

}
add_action('admin_menu', 'vinvin_plugin_setup_menu');
function vinvin_plugin_setup_menu(){
        add_menu_page( 'Vinvin SEO', 'Vinvin SEO', 'manage_options', 'vinvin-seo', 'vinvin_fn' );
}

function vinvin_fn(){ ?>
    <style>
    label {
      min-width: 150px;
      display: inline-block;
      margin: 10px 0;}
      fieldset > legend {
    font-weight: bold;
    text-transform: uppercase;
    margin: 0 0px;
}
fieldset {
    border: 1px solid #dedede;
    padding: 20px;
    margin: 10px 0;
}
.field input[type="text"] {
    width: 400px;
    padding: 5px;
    margin: 5px 0;
}
    </style>

    <?php
    if (  current_user_can( 'manage_options' ) ) {

        if( isset( $_POST['vinvin'] ) && !empty( $_POST['vinvin'] ) ){

          update_option('vinvin_seo' ,  $_POST['vinvin'] );

        }

        if( isset( $_POST['vinvin_accepted_posttypes'] ) && !empty( $_POST['vinvin_accepted_posttypes'] ) ){

          update_option('vinvin_accepted_posttypes' ,  $_POST['vinvin_accepted_posttypes'] );

        }

        $vinvin_accepted_posttypes = get_option('vinvin_accepted_posttypes');
        if (isset( $_POST['delete_existing_post']) || isset($_GET['vgdelete'])){
            deleteAllData($vinvin_accepted_posttypes);
        }

        // Generate all thumbnails :
        if (isset( $_POST['thumbnail_exisiting_posts_btn']) && 
            isset($_POST['thumbnail_exisiting_posts']) && $_POST['thumbnail_exisiting_posts']=='on'  )
        {
            $backup_max_execution_time = ini_get('max_execution_time'); 
            set_time_limit(-1);


            $post_page_no_thumbnail_args = [ 
                'post_type'      => array('post' , 'page'),
                'posts_per_page' => -1,
                'meta_query' => [
                  [
                    'key' => '_thumbnail_id',
                    'value' => '',
                    'compare' => 'NOT EXISTS'
                  ]
                ]
            ];
            $post_page_no_thumbnail = new WP_Query($post_page_no_thumbnail_args);
            $count_reviewed_post = 0;
            //echo 'totot ' . count($post_page_no_thumbnail->posts); die();
            if ($post_page_no_thumbnail->posts){
            
                foreach ( $post_page_no_thumbnail->posts as $post ){
                  //   echo 'on traite le post id ' . $post->ID;
                  createThumbnailsByPostTitle(  $post->ID );
                  $count_reviewed_post++;
                }
            }
            set_time_limit( $backup_max_execution_time );
            ?>
              <h2><?php  echo sprintf(__('Hurra ! %d thumbnails has been generated' , 'vinvinseo') , $count_reviewed_post); ?></h2>
            <?php
        }

        // delete_table_of_content
        if (isset( $_POST['delete_table_of_content']) || isset($_GET['delete_table_of_content'])){


          $posts = get_posts(array(
            'post_type' => 'post',
            'numberposts' => -1
          ));
          foreach ($posts as $post){
            vinvin_save_post_remove_summary( $post->ID , $post );
          }

          $pages = get_posts(array(
            'post_type' => 'page',
            'numberposts' => -1
          ));
          foreach ($pages as $page){
            vinvin_save_post_remove_summary( $page->ID , $page );
          }
        

        
      }


        if (isset( $_POST['process_existing_post']) || isset($_GET['vgprocess'])  ){

          $accepted_type_post = implode( ',' , array_keys($vinvin_accepted_posttypes) );
          $allPosts = get_posts(array(
            'numberposts'      => -1,
            'post_type' => array_keys($vinvin_accepted_posttypes),
            'post_status' => 'any'
          ));
          $count_reviewed_post = 0;

          $process_existing_post = isset( $_POST['process_existing_post_already_reviewed'] ) && $_POST['process_existing_post_already_reviewed'] == 'on'  ? true : false;
          if ( isset($_GET['vgprocess']) ){

            $process_existing_post = true;

          }

          foreach ( $allPosts as $matched_posts ) {

            $this_post_hasbeen_reviewed = get_post_meta( $matched_posts->ID , 'hasbeenreviewed' , true);

            // Si on a coché PAS OVERRIDE ? => il faut PAS que le post soit deja review
            // SI coché on les fait tous
            if( $process_existing_post == false  ){
                if( $this_post_hasbeen_reviewed == false ){
                  addReviewsToExistingPost( $matched_posts->ID );
                  $count_reviewed_post++;
                }
            }else{
                addReviewsToExistingPost( $matched_posts->ID );
                $count_reviewed_post++;
            }


          }
          ?>
          <h2><?php  echo sprintf(__('Hurra ! %d posts has been reviewed :d ' , 'vinvinseo') , $count_reviewed_post); ?></h2>
          <?php

        }


        $vinvin_settings = get_option('vinvin_seo');

        $brand = get_bloginfo('name');
        $description = get_bloginfo('description');
        $actived = $bef_h2 = $above_h2 = $product_random_image = false;
        $reviews = $gtin8 = $currency = $low_price = $high_price = $sku = $name = $review_title = $product_image = '';
        $review_default = $rating_default = $rating_count_default = 4;
        $min_review = 5;
        $max_review = 40;
        $count_offer = 1000;
        $default_lang = $from_lang = '';

        //print_r( $vinvin_settings );

        if( isset($vinvin_settings['default_lang']) && $vinvin_settings['default_lang'] ){
            $default_lang = $vinvin_settings['default_lang'];
        }

        if( isset($vinvin_settings['from_lang']) && $vinvin_settings['from_lang'] ){
            $from_lang = $vinvin_settings['from_lang'];
        }

        if( isset($vinvin_settings['actived']) && $vinvin_settings['actived'] ){
            $actived = $vinvin_settings['actived'];
        }
        if( isset($vinvin_settings['sku']) &&  $vinvin_settings['sku'] ){
            $sku = $vinvin_settings['sku'];
        }
        if( isset($vinvin_settings['low_price']) &&  $vinvin_settings['low_price'] ){
            $low_price = $vinvin_settings['low_price'];
        }
        if( isset($vinvin_settings['offers_currency']) &&  $vinvin_settings['offers_currency'] ){
            $currency = $vinvin_settings['offers_currency'];
        }
        if( isset($vinvin_settings['count_offer']) &&  $vinvin_settings['count_offer'] ){
            $count_offer = $vinvin_settings['count_offer'];
        }
        if( isset($vinvin_settings['min_review']) &&  $vinvin_settings['min_review'] ){
            $min_review = $vinvin_settings['min_review'];
        }
        if( isset($vinvin_settings['max_review']) &&  $vinvin_settings['max_review'] ){
            $max_review = $vinvin_settings['max_review'];
        }
        if( isset($vinvin_settings['high_price']) &&  $vinvin_settings['high_price'] ){
            $high_price = $vinvin_settings['high_price'];
        }
        if( isset($vinvin_settings['gtin8']) &&  $vinvin_settings['gtin8'] ){
            $gtin8 = $vinvin_settings['gtin8'];
        }
        if( isset($vinvin_settings['brand']) &&  $vinvin_settings['brand'] ){
            $brand = $vinvin_settings['brand'];
        }
        if( isset($vinvin_settings['description']) &&  $vinvin_settings['description'] ){
            $description = $vinvin_settings['description'];
        }
        if( isset($vinvin_settings['name']) && $vinvin_settings['name'] ){
            $name = $vinvin_settings['name'];
        }
        if( isset($vinvin_settings['review_title']) && $vinvin_settings['review_title'] ){
            $review_title = $vinvin_settings['review_title'];
        }
        if( isset($vinvin_settings['product_image']) && $vinvin_settings['product_image'] ){
            $product_image = $vinvin_settings['product_image'];
        }
        //product_random_image
        if( isset($vinvin_settings['product_random_image']) && $vinvin_settings['product_random_image'] ){
          $product_random_image = $vinvin_settings['product_random_image'];
        //  die();
        }
        if( isset($vinvin_settings['review_default']) && $vinvin_settings['review_default'] ){
            $review_default = $vinvin_settings['review_default'];
        }
        if( isset($vinvin_settings['rating_default']) && $vinvin_settings['rating_default'] ){
            $rating_default = $vinvin_settings['rating_default'];
        }
        if( isset($vinvin_settings['rating_count_default']) && $vinvin_settings['rating_count_default'] ){
            $rating_count_default = $vinvin_settings['rating_count_default'];
        }
        if( isset($vinvin_settings['reviews']) && $vinvin_settings['reviews'] ){
            $reviews = $vinvin_settings['reviews'];
        }
        if( isset($vinvin_settings['bef_h2']) && $vinvin_settings['bef_h2'] ){
          $bef_h2 = $vinvin_settings['bef_h2'];
        }
        if( isset($vinvin_settings['above_h2']) && $vinvin_settings['above_h2'] ){
          $above_h2 = $vinvin_settings['above_h2'];
        }

        ?>

        <h2><?php _e('SEO Setting' , 'vinvinseo'); ?></h2>
        <form method="post">
        <fieldset style="display:none">
            <legend><?php _e('Content Settings','vinvinseo') ?></legend>
            <div class="field">
              <label for="brand"><?php _e('Add something before heading 2 :' , 'vinvinseo'); ?></label>
              <input type="text" name="vinvin[bef_h2]" value="" />
            </div>
            <div class="field">
              <label for="brand"><?php _e('Add text or call to action above the third heading 2 :' , 'vinvinseo'); ?></label><br/>
              <textarea style="width:100%;height:250px" name="vinvin[above_h2]" placeholder="<?php _e('Content above h2' , 'vinvinseo'); ?>"></textarea>
            </div>
        </fieldset>

          <fieldset>
            <legend><?php _e('Multilingual settings','vinvinseo') ?></legend>
            <div class="field">
              <label for="default_lang"><?php _e('Default lang (iso code) :' , 'vinvinseo'); ?></label>
              <input type="text" name="vinvin[default_lang]" value="<?php echo $default_lang ?>" />
            </div>
            <div class="field">
              <label for="from_lang"><?php _e('From lang (iso code) :' , 'vinvinseo'); ?></label>
              <input type="text" name="vinvin[from_lang]" value="<?php echo $from_lang ?>" />
            </div>
            <legend><?php _e('Global product settings','vinvinseo') ?></legend>
            <div class="field">
              <label for="brand"><?php _e('Activé :' , 'vinvinseo'); ?></label>
              <input type="checkbox" name="vinvin[actived]" <?php if ($actived){ echo 'checked="checked"'; }?> />
            </div>
            <div class="field">
              <label for="brand"><?php _e('Brand :' , 'vinvinseo'); ?></label>
              <input type="text" name="vinvin[brand]" placeholder="<?php _e('Brand' , 'vinvinseo'); ?>" value="<?php echo $brand ?>" />
            </div>
            <div class="field">
              <label for="description"><?php _e('Description :' , 'vinvinseo'); ?></label>
              <input type="text" name="vinvin[description]" placeholder="<?php _e('Description' , 'vinvinseo'); ?>" value="<?php echo stripslashes_deep($description) ?>" />
            </div>
            <div class="field">
              <label for="sku"><?php _e('SKU :' , 'vinvinseo'); ?></label>
              <input type="text" name="vinvin[sku]" placeholder="<?php _e('sku' , 'vinvinseo'); ?>" value="<?php echo $sku ?>" />
            </div>
            <div class="field">
              <label for="name"><?php _e('Name :' , 'vinvinseo'); ?></label>
              <input type="text" name="vinvin[name]" placeholder="<?php _e('Name' , 'vinvinseo'); ?>" value="<?php echo $name ?>" />
            </div>
            <div class="field">
              <label for="review_title"><?php _e('Gtin8 :' , 'vinvinseo'); ?></label>
              <input type="text" name="vinvin[gtin8]" placeholder="<?php _e('gtin8' , 'vinvinseo'); ?>" value="<?php echo $gtin8 ?>" />
            </div>
            <div class="field">
              <label for="review_title"><?php _e('Review Title :' , 'vinvinseo'); ?></label>
              <input type="text" name="vinvin[review_title]" placeholder="<?php _e('Review Title' , 'vinvinseo'); ?>" value="<?php echo $review_title ?>" />
            </div>

            <div class="field">
              <label for="review_image"><?php _e('Image :' , 'vinvinseo'); ?></label>
              <input type="text" name="vinvin[product_image]" placeholder="<?php _e('Image for Product' , 'vinvinseo'); ?>" value="<?php echo $product_image ?>" />
              <br/>
              <label for="review_image"><?php _e('Random Image :' , 'vinvinseo'); ?></label>
              <input type="checkbox" name="vinvin[product_random_image]" placeholder="<?php _e('Random Image for Product' , 'vinvinseo'); ?>"  <?php if ($product_random_image){ echo 'checked="checked"'; }?> />
            </div>
            <div class="field">
              <label for="review_image"><?php _e('Enable for post type :' , 'vinvinseo'); ?></label>
              <?php
              $allPostType = get_post_types(array('public' => true));
              foreach( $allPostType as $k => $pt ) : ?>
                <input type="checkbox" <?php if( isset($vinvin_accepted_posttypes[$k]) && $vinvin_accepted_posttypes[$k] == 'on' ){ echo 'checked="checked"'; } ?> name="vinvin_accepted_posttypes[<?php echo $k ?>]" /> <?php echo $pt ?> &nbsp; &nbsp;&nbsp;
              <?php endforeach; ?>
            </div>
          </fieldset>
          <fieldset>
            <legend><?php _e('Aggregate Rating settings',  'vinvinseo') ?></legend>
            <div class="field">
              <label for="review_review_default"><?php _e('Default reviews count :' , 'vinvinseo'); ?></label>
              <input type="number" name="vinvin[review_default]" placeholder="<?php _e('Review Title' , 'vinvinseo'); ?>" value="<?php echo $review_default ?>" />
            </div>

            <div class="field">
              <label for="review_rating_default"><?php _e('Default rating :' , 'vinvinseo'); ?></label>
              <input type="number" name="vinvin[rating_default]" placeholder="<?php _e('Review Title' , 'vinvinseo'); ?>" value="<?php echo $rating_default ?>" />
            </div>

            <div class="field">
              <label for="review_count_default"><?php _e('Default rating count :' , 'vinvinseo'); ?></label>
              <input type="number" name="vinvin[rating_count_default]" placeholder="<?php _e('Review Title' , 'vinvinseo'); ?>" value="<?php echo $rating_count_default ?>" />
            </div>
            <div class="field">
              <label for="offers_high_price"><?php _e('Min Review :' , 'vinvinseo'); ?></label>
              <input type="number" name="vinvin[min_review]" placeholder="<?php _e('Min review' , 'vinvinseo'); ?>" value="<?php echo $min_review ?>" />
            </div>

            <div class="field">
              <label for="offers_high_price"><?php _e('Max review :' , 'vinvinseo'); ?></label>
              <input type="number" name="vinvin[max_review]" placeholder="<?php _e('Max review' , 'vinvinseo'); ?>" value="<?php echo $max_review ?>" />
            </div>
          </fieldset>
          <fieldset>
            <legend><?php _e('Offers settings',  'vinvinseo') ?></legend>
            <div class="field">
              <label for="offers_currency"><?php _e('Currency :' , 'vinvinseo'); ?></label>
              <input type="text" name="vinvin[offers_currency]" placeholder="<?php _e('Currency' , 'vinvinseo'); ?>" value="<?php echo $currency ?>" />
            </div>
            <div class="field">
              <label for="offers_count"><?php _e('Count offers :' , 'vinvinseo'); ?></label>
              <input type="number" name="vinvin[count_offer]" placeholder="<?php _e('Count offers' , 'vinvinseo'); ?>" value="<?php echo $count_offer ?>" />
            </div>
            <div class="field">
              <label for="offers_low_price"><?php _e('Lower price :' , 'vinvinseo'); ?></label>
              <input type="number" name="vinvin[low_price]" placeholder="<?php _e('Lower price' , 'vinvinseo'); ?>" value="<?php echo $low_price ?>" />
            </div>

            <div class="field">
              <label for="offers_high_price"><?php _e('Higher price :' , 'vinvinseo'); ?></label>
              <input type="number" name="vinvin[high_price]" placeholder="<?php _e('Higher price' , 'vinvinseo'); ?>" value="<?php echo $high_price ?>" />
            </div>



          </fieldset>
          <fieldset>
            <legend><?php _e('AggregateRating settings',  'vinvinseo') ?></legend>
            <h2> <?php _e('Reviews' , 'vinvinseo')?> </h2>

              <textarea name="vinvin[reviews]" placeholder="<?php _e('Review one by line' , 'vinvinseo'); ?>" style="width:100%;height:250px"><?php echo wp_unslash(esc_textarea($reviews)); ?></textarea>

          </fieldset>
          <input type="submit" class="button button-primary" value="<?php _e('Save settings' , 'vinvinseo'); ?>" />
        </form>
        <h2> <?php _e('You already have exiting posts ?' , 'vinvinseo'); ?> </h2>
        <form method="post">
          <div class="field">
            <input type="checkbox" name="process_existing_post_already_reviewed" id="process_existing_post_already_reviewed" /> <label for="process_existing_post_already_reviewed"><?php _e('Reviewed all posts?','vinvinseo'); ?></label>
          </div>
          <br/>
          <input type="submit"  name="process_existing_post" class="button button-primary" value="<?php _e('🧹 Process existing post' , 'vinvinseo'); ?>" />
        </form>
        <h2> <?php _e('Delete all existing data ? ' , 'vinvinseo'); ?> </h2>
        <form method="post">
          <div class="field">
            <input type="checkbox" name="delete_existing_post_already_reviewed" id="delete_existing_post_already_reviewed" />
              <label for="delete_existing_post_already_reviewed"><?php _e('Delete all posts?','vinvinseo'); ?></label>
          </div>
          <br/>
          <input type="submit"  name="delete_existing_post" class="button button-primary" value="<?php _e('❌ Delete existing post' , 'vinvinseo'); ?>" />
        </form>
        <h2> <?php _e('Remove Table Of Content' , 'vinvinseo'); ?> </h2>
        <form method="post">
          <div class="field">
            <input type="checkbox" name="delete_table_of_content" id="delete_table_of_content" />
              <label for="delete_table_of_content"><?php _e('Delete Table Of Content?','vinvinseo'); ?></label>
          </div>
          <br/>
          <input type="submit"  name="delete_table_of_content" class="button button-primary" value="<?php _e('❌ Delete Table Of Content' , 'vinvinseo'); ?>" />
        </form>
        <h2> <?php _e('Generate Thumbnails for all existing post' , 'vinvinseo'); ?> </h2>
        <form method="post">
          <div class="field">
            <input type="checkbox" name="thumbnail_exisiting_posts" id="thumbnail_exisiting_posts" />
              <label for="thumbnail_exisiting_posts"><?php _e('Generate Thumbnail for existing post','vinvinseo'); ?></label>
          </div>
          <br/>
          <input type="submit"  name="thumbnail_exisiting_posts_btn" class="button button-primary" value="<?php _e('🔁 Generate all thumbnails' , 'vinvinseo'); ?>" />
        </form>
        <?php
      }
      else{
        die('you are not authorized');
      }
}

function vinvin_spintax( $string ) {
	$data = preg_match_all( "/(?=\{).*?(?=\})./", $string, $match );

	if ( !empty( $match ) ) {
		foreach ( $match as $key => $value ) {
			if ( !empty( $value ) ) {
				foreach ( $value as $values ) {
					$v = explode( "|", $values );
					$i = array_rand( $v );
					$string = str_replace( $values, str_replace( array( "{", "}" ), "", $v[ $i ] ), $string );
				}
			}
		}
	}
	return $string;
}

add_shortcode('vinvin_site_description' , 'vinvin_site_description_fn');
function vinvin_site_description_fn( $atts ) {
  return get_bloginfo('description');

}


add_shortcode('vinvin_site_name' , 'vinvin_site_name_fn');
function vinvin_site_name_fn( $atts ) {
  return get_bloginfo('name');
}


add_shortcode('vinvin_post_title' , 'vinvin_post_title_fn');
function vinvin_post_title_fn( $atts ) {
  global $post;
//  return 'totot';
  if($post){
    return $post->post_title;
  }

}

add_filter('the_content' , 'vinvin_review_to_content');
function vinvin_review_to_content( $content ){

  $vinvin_settings = get_option('vinvin_seo');
  $reviews = get_post_meta( get_the_ID() , 'reviews', true);
  //print_r($reviews); die();
  if( !$reviews ){ return $content; }
  $product_value = get_post_meta( get_the_ID() , 'product_value' , true );
  $result = '
  <div id="reviews">';
  if(isset($vinvin_settings['review_title']) && $vinvin_settings['review_title'] ) {   $result .= '<h2> '.($product_value['review_title']). '</h2><br/>'; }


  $result .= '<div class="vinvin_review_wrapper">';
  foreach( $reviews as $rev ){
    if( $rev['review'] ){
      $originalDate = $rev['date']->date;
      $newDate = date("d/m/Y", strtotime($originalDate));
      $result .= '<span class="vinvin_name">'.$rev['name'].'</span>' . ' - <span class="vinvin_date">' . $newDate . '</span><br>';
      $result .= '<span class="vinvin_review_single">'.$rev['review']. '</span><br>';
    }

  }
  $result .= '</div></div>';
  return $content . $result;

}

function deleteAllData($vinvin_accepted_posttypes){

  $accepted_type_post = implode( ',' , array_keys($vinvin_accepted_posttypes) );
  $allPosts = get_posts(array(
    'numberposts'      => -1,
    'post_type' => array_keys($vinvin_accepted_posttypes),
    'post_status' => 'any'
  ));

  foreach ( $allPosts as $matched_posts ) {

    $post_id = $matched_posts->ID;
    delete_post_meta( $post_id , 'ratingCount'  );
    delete_post_meta( $post_id , 'reviewCount'  );
    delete_post_meta( $post_id , 'rating' );
    delete_post_meta( $post_id , 'product_value'  );
    delete_post_meta( $post_id , 'hasbeenreviewed' );
    delete_post_meta( $post_id , 'reviews' );


  }


}

function addReviewsToExistingPost( $post_id , $addReviewsToExistingPost = false){

  $vinvin_settings = get_option('vinvin_seo');

  $min_review = 4;
  $max_review = 20;
  if( isset($vinvin_settings['min_review']) &&  $vinvin_settings['min_review'] ){
      $min_review = $vinvin_settings['min_review'];
  }
  if( isset($vinvin_settings['max_review']) &&  $vinvin_settings['max_review'] ){
      $max_review = $vinvin_settings['max_review'];
  }
  //print_r(array($min_review, $max_review)); die();
  $reviewCount = rand ( $min_review, $max_review );
  $rating =  rand ( 42 , 49 ) / 10;

  //update post reviesw title :
  //update_post_meta( $post_id , 'vinvin_review_title' , $vinvin_settings['max_review'] );


  update_post_meta( $post_id , 'ratingCount' , $reviewCount );
  update_post_meta( $post_id , 'reviewCount' , $reviewCount );
  update_post_meta( $post_id , 'rating' , $rating );

  $product_value = array(
    'name' => $vinvin_settings['name'],
    'brand' => $vinvin_settings['brand'],
    'sku' => mt_rand(100000000,999999999),
    'gtin8' => mt_rand(10000000,99999999),//$vinvin_settings['gtin8'],
    'description' => $vinvin_settings['description'],
    'review_title' => $vinvin_settings['review_title']
  );

  $spinted_product_value = array_map( 'vinvin_spintax' ,  $product_value );

  // Deal with image :
  if( isset($vinvin_settings['product_random_image']) &&  $vinvin_settings['product_random_image'] ){

    // get all image ids available
    $image_ids = get_posts( 
      array(
          'post_type'      => 'attachment', 
          'post_mime_type' => 'image', 
          'post_status'    => 'inherit', 
          'posts_per_page' => -1,
          'fields'         => 'ids',
      ) 
    );
   // print_r($image_ids); die();
    // based on the number of image_ids retrieved, generate a random number within bounds.
    $num_of_images = count($image_ids);
    $random_index = rand(0, $num_of_images - 1);
    $random_img_id = $image_ids[$random_index];
    
    // set post image 
    update_post_meta( $post_id , 'product_random_image' , ($random_img_id) );
    //set_post_thumbnail( $post_id , $random_img_id );

  }

  update_post_meta( $post_id , 'product_value' , ($spinted_product_value) );
  //print_r(get_option('vinvin_seo')['reviews']); die();
  $vinvin = explode(PHP_EOL, get_option('vinvin_seo')['reviews']);
  //print_r($vinvin);
  if( !empty( $vinvin )){
    $faker = Faker\Factory::create();
    $reviews_data = array();

    $currentPostedReviews  = get_post_meta( $post_id , 'reviews' , true );
    for( $i = 0 ; $i < $reviewCount ; $i++ ){

      $reviews_data[$i]['name'] = $faker->name;
      $reviews_data[$i]['date'] = $faker->dateTimeBetween('-1 year');
      $selected_reviews = array_rand( $vinvin );
  //    echo $selected_reviews;
      $reviews_data[$i]['review'] = vinvin_spintax($vinvin[$selected_reviews]);
      //unset:
      //unset( $vinvin[$selected_reviews] );

    }
  //  die();
    update_post_meta( $post_id , 'hasbeenreviewed' , 'true');
    update_post_meta( $post_id , 'reviews' , ($reviews_data) );
  }
}


add_action( 'save_post', 'vinvin_save_post' );
function vinvin_save_post($post_id){

    $hasbeenreviewed = get_post_meta( $post_id , 'hasbeenreviewed' , true);
    //ie();
    if($hasbeenreviewed == false){
      addReviewsToExistingPost($post_id);
    }

}


add_action('wp_head', 'vinvin_head');
function vinvin_head(){
    global $post;

    $hasbeenreviewed = get_post_meta($post->ID , 'reviews', true);

    if( !is_admin() && !is_home() && $post && $hasbeenreviewed ){



		$vinvin_settings = get_option('vinvin_seo');
    $activated = $product_random_image = false;
    $brand = $description = $sku = $name = $review_title = $product_image = '';
    $review_default = $rating_default = $rating_count_default = 4;

    $brand = get_bloginfo('name');
    $description = get_bloginfo('description');
    $actived = false;
    $reviews = $low_price = $currency = $high_price = $gtin8 = $sku = $name = $review_title = $product_image = '';
    $review_default = $rating_default = $rating_count_default = 4;
    $count_offer = 1000;

    if( isset($vinvin_settings['low_price']) &&  $vinvin_settings['low_price'] ){
        $low_price = $vinvin_settings['low_price'];
    }
    if( isset($vinvin_settings['low_price']) &&  $vinvin_settings['low_price'] ){
        $low_price = $vinvin_settings['low_price'];
    }
    if( isset($vinvin_settings['offers_currency']) &&  $vinvin_settings['offers_currency'] ){
        $currency = $vinvin_settings['offers_currency'];
    }
    if( isset($vinvin_settings['high_price']) &&  $vinvin_settings['high_price'] ){
        $high_price = $vinvin_settings['high_price'];
    }
    if( isset($vinvin_settings['actived']) && $vinvin_settings['actived'] ){
        $actived = $vinvin_settings['actived'];
    }
    if( isset($vinvin_settings['sku']) &&  $vinvin_settings['sku'] ){
        $sku = $vinvin_settings['sku'];
    }
    if( isset($vinvin_settings['name']) && $vinvin_settings['name'] ){
        $name = $vinvin_settings['name'];
    }
    if( isset($vinvin_settings['count_offer']) &&  $vinvin_settings['count_offer'] ){
        $count_offer = $vinvin_settings['count_offer'];
    }
    if( isset($vinvin_settings['gtin8']) && $vinvin_settings['gtin8'] ){
        $gtin8 = $vinvin_settings['gtin8'];
    }
    if( isset($vinvin_settings['review_title']) && $vinvin_settings['review_title'] ){
        $review_title = $vinvin_settings['review_title'];
    }
    if( isset($vinvin_settings['product_image']) && $vinvin_settings['product_image'] ){
        $product_image = $vinvin_settings['product_image'];
    }
    if( isset($vinvin_settings['product_random_image']) && $vinvin_settings['product_random_image'] ){
      $product_random_image = $vinvin_settings['product_random_image'];
  }
    if( isset($vinvin_settings['review_default']) && $vinvin_settings['review_default'] ){
        $review_default = $vinvin_settings['review_default'];
    }
    if( isset($vinvin_settings['rating_default']) && $vinvin_settings['rating_default'] ){
        $rating_default = $vinvin_settings['rating_default'];
    }
    if( isset($vinvin_settings['rating_count_default']) && $vinvin_settings['rating_count_default'] ){
        $rating_count_default = $vinvin_settings['rating_count_default'];
    }
    if( isset($vinvin_settings['reviews']) && $vinvin_settings['reviews'] ){
        $reviews = $vinvin_settings['reviews'];
    }

    $ratingCount = get_post_meta( $post->ID , 'ratingCount' , true );
    $reviewCount = get_post_meta( $post->ID , 'reviewCount' , true );
    $rating = get_post_meta( $post->ID , 'rating' , true );


    $product_value = get_post_meta( $post->ID , 'product_value' , true );

    /* default value */
    if( !$ratingCount ){ $ratingCount = $rating_count_default; }
    if( !$reviewCount ){ $reviewCount = $review_default; }
    if( isset($product_value['brand']) ) {  $brand = do_shortcode($product_value['brand']); }
    if( isset($product_value['description']) ) {  $description = do_shortcode($product_value['description']); }
    if( isset($product_value['sku']) ) {  $sku = $product_value['sku']; }
    if( isset($product_value['name']) ) {  $name = do_shortcode($product_value['name']); }
    if( isset($product_value['gtin8']) ) {  $gtin8 = $product_value['gtin8']; }

    $thumbnails_image = get_the_post_thumbnail_url($post->ID);
    $hasRandomImage = get_post_meta( $post->ID, 'product_random_image' , true );
    // Si on a une image et que Product image nest pas a true ?
    if(  $thumbnails_image != false ){
      $product_image = $thumbnails_image;
    } else if(  $hasRandomImage ){
      $product_image = wp_get_attachment_url( $hasRandomImage );
    }

    $reviews = get_post_meta( $post->ID , 'reviews' , true );
    //print_r($reviews); die();
    if($reviews) {
      foreach( $reviews as $rev ){
        if( $rev['review'] ){
          $json_arr[] = array(
              'reviewRating' => array(
                '@type' => 'Rating',
                'ratingValue' => $rating
              ),
              'name' => $name,
              'author' => array(
                '@type' => 'Person',
                'name' => $rev['name']
              ),
              'datePublished' => $rev['date']->date,
              'reviewBody' => $rev['review']
          );
        }
      }
    }else{
        $json_arr = array();
    }


    //ob_start('apply_spintax_filter');

?>
    <script type="application/ld+json">
    {
      "@context": "http://schema.org/",
      "@type": "Product",
      "brand": "<?php echo $brand; ?>",
      "Description" : "<?php echo $description; ?>",
      "image" : "<?php echo $product_image; ?>",
      "sku" :  "<?php echo $sku;  ?>",
      "gtin8" : "<?php echo ($gtin8);  ?>",
      "name": "<?php echo $name; ?>",
      "aggregateRating": {
        "@type": "AggregateRating",
        "ratingValue" : "<?php echo $rating; ?>",
        "ratingCount": "<?php echo $ratingCount;  ?>",
        "reviewCount": "<?php echo $reviewCount; ?>"

      },
        <?php if( !empty($json_arr) ) : echo '"review"' . ':'  ?>
        <?php echo json_encode($json_arr) ?>,
        <?php endif; ?>
        "offers": {
          "@type": "AggregateOffer",
          "availability": "http://schema.org/InStock",
          "offerCount": "<?php echo $count_offer; ?>",
          "lowPrice": "<?php echo $low_price; ?>",
          "highPrice": "<?php echo $high_price; ?>",
          "priceCurrency": "<?php echo $currency; ?>"
       }

      }
    </script>

<?php

}


}

add_shortcode('vg_sc_fs_multi_faq' , 'sc_fs_multi_faq_vinvin');
function sc_fs_multi_faq_vinvin($atts){


    // loop in da faq atts :v
    $newAtts = array();
    $string_atts = '';
    foreach( $atts as $k => $v ){
        // location
        $tmpValue = str_replace(
            '%LOCATION%' ,
            do_shortcode('[location]'),
            $v );
        // Region
        $tmpValue = str_replace(
            '%REGION%' ,
            do_shortcode('[region]'),
            $tmpValue );

        $tmpValue = str_replace(
            '%COUNTRY%' ,
            do_shortcode('[country]'),
            $tmpValue );


         //echo  do_shortcode('[site_title]'); die();
         $tmpValue = str_replace(
            '%SITETITLE%' ,
            get_bloginfo('name'),
            $tmpValue );

        $tmpValue = str_replace(
            '%CITIES%' ,
            strip_tags(do_shortcode('[cities type="csvt"]')),
            $tmpValue );

        // build new atts
        $string_atts .= $k.'="'.$tmpValue.'" ';
    }
    // print_r($string_atts); die();
    // On rebalance la purée à sc_fs_multi_faq :d
    return do_shortcode( '[sc_fs_multi_faq '.$string_atts.']' );
}


//add_action('save_post' , 'vinvin_save_post_remove_summary' , 10 , 2);
function vinvin_save_post_remove_summary( $post_id , $post ){

  $doc = new DOMDocument();
  $loaded = $doc->loadHTML( $post->post_content );
  $i = 0;
  

  // On vire les table of content
  $uls = $doc->getElementsByTagName('ul');
  foreach( $uls as $ul ){
    
    // si on a bien des class et qu'on est bien dans le sommaire ez toc
    if( is_object($ul->attributes['class']) && isset($ul->attributes['class']->value) ){
      if ( in_array( 'ez-toc-list' , explode(' ', $ul->attributes['class']->value) ) ){
        $item_found = $uls->item( $i );
        //on delete le <p> au dessus (le titre du sommaire) (si c'est bien un p)
        if($ul->previousSibling->previousSibling->tagName == 'p'){
          $item_found->parentNode->removeChild($ul->previousSibling->previousSibling);
        }
        $item_found->parentNode->removeChild($item_found);
        //$item_found->parentNode->remove()
      }
    }
    $i++;
  }

  // on cherche les box auteur :
  $imgs = $doc->getElementsByTagName('img');
  $i_img = 0;
  foreach( $imgs as $img ){
    if( $img->attributes['style']->name == 'style' ){
      $item_found_img = $uls->item( $i_img );
      $item_found_img->parentNode->removeChild($item_found_img);

      //print_r( $img->attributes[2] );
    }
    $i_img++;
  }
  //die();
  // on save le tout
  $html = utf8_decode($doc->saveHTML($doc->documentElement));

  
  if($html != false){
    remove_action('save_post','vinvin_save_post_remove_summary' , 10, 3);
    wp_update_post( array(
      'ID' => $post_id,
      'post_content' => $html
    ));
    add_action('save_post','vinvin_save_post_remove_summary' , 10, 3);
  }


}



function getRandomImage(){
  $image_ids =  get_posts( 
      array(
          'post_type'      => 'attachment', 
          'post_mime_type' => 'image', 
          'post_status'    => 'inherit', 
          'posts_per_page' => -1,
          'fields'         => 'ids',
          'post_mime_type' => 'image/jpeg',
          'meta_query' => [
              [
                'key' => 'is_thumbnail_img',
                'value' => '',
                'compare' => 'NOT EXISTS'
              ]
          ]
      ) 
  );
 
  // based on the number of image_ids retrieved, generate a random number within bounds.
  $num_of_images = count($image_ids);
  error_log( json_encode($image_ids));
  $attach = array();
  do {
      $random_index = rand(0, $num_of_images - 1) ;
      $random_image_id = $image_ids[$random_index];
      // now that we have a random_image_id, lets fetch the image itself.
      //$image = get_post($random_image_id);
      $attach = wp_get_attachment_metadata( $random_image_id );
      $attach_path = wp_get_attachment_url( $random_image_id );
      if( !isset( $attach['width'] )){
          $attach['width'] = 1;
      }
      if( !isset( $attach['height'] )){
          $attach['height'] = 2;
      }
      // print_r( $attach ); die();
} while(( $attach['width'] < 1000  || $attach['width'] < $attach['height'] ));
  
  // si l'image random&& exif_imagetype($attach_path) != IMAGETYPE_JPEG
  //
  //print_r( $attach ); die();
  return $attach_path;
  //print_r( $attach ); die();

}

function createThumbnailsByPostTitle( $post_id ){


  $attach = getRandomImage();
  $background_image = imagecreatefromjpeg( $attach );
  //print_r($background_image); die();
  $title = get_the_title( $post_id );
  //$im = imagecreatetruecolor(1200, 1080);
  $text_color = imagecolorallocate($background_image, 255, 255, 255);
  
  
  
  ImageStringCenter($background_image, 1, $title );

  // Set the content type header - in this case image/jpeg
  //header('Content-Type: image/jpeg');

  // Output the image
  //$uniqueId = md5(uniqid()); // Create unique name for the new image
  $imagePath = wp_upload_dir()['path'] . '/' . sanitize_title($title) . '.jpg';

  imagejpeg($background_image , $imagePath);
  $attachment = array(
      'post_mime_type' => 'image/jpeg',
      'post_title' => basename($imagePath),
      'post_content' => '',
      'post_status' => 'inherit'
  );
  
  $croppedAttachmentId = wp_insert_attachment( $attachment, $imagePath );
  if ( ! function_exists( 'wp_crop_image' ) ) {
      include( ABSPATH . 'wp-admin/includes/image.php' );
  }
  $attachedData = wp_generate_attachment_metadata( $croppedAttachmentId, $imagePath);
  
  wp_update_attachment_metadata( $croppedAttachmentId , $attachedData );
  set_post_thumbnail( $post_id ,$croppedAttachmentId );
  error_log( 'assign ' .  $imagePath . ' to ' . $post_id .' ' . $title );
  update_post_meta( $croppedAttachmentId , 'is_thumbnail_img' , 1 );
  // Free up memory
  imagedestroy($background_image);

}


function ImageStringCenter($image, $y, $str) {
// http://www.puremango.co.uk/2009/04/php-imagestringright-center-italic/
$font=40;
  $font_file = __DIR__.'/fonts/Arial.ttf';
  $angle=0;
$font_width = ImageFontWidth($font);
$str_width = strlen($str)*$font_width;
 // print_r( $str_width ); die();
  $black = imagecolorallocate($image, 0, 0, 0);
  // Get image dimensions
  $width = imagesx($image);
  $height = imagesy($image);
  // Get center coordinates of image
  $centerX = $width / 2;
  $centerY = $height / 2;
  // Get size of text
  list($left, $bottom, $right, , , $top) = imageftbbox($font, $angle , $font_file, $str);
  // Determine offset of text
  $left_offset = ($right - $left) / 2;
  $top_offset = ($bottom - $top) / 2;
  // Generate coordinates
  $x = $centerX - $left_offset;
  $y = $centerY + $top_offset;
  $padding = 200;

      //print_r(list($left, $bottom, $right, , , $top) );die();

  $canvas = imagecreatetruecolor(200, 200);
  
  // Allocate colors
  $white = imagecolorallocatealpha($image, 255, 255, 255 , 40);
  ImageFilledRectangle($image, $x - $padding , $y - $padding,  $centerX + $left_offset + $padding,$centerY - $top_offset + $padding,$white);


imagettftext($image, $font, $angle, $x,$y, $black ,  $font_file ,$str);
//  die();
}

/*
$backup_max_execution_time = ini_get('max_execution_time'); 
set_time_limit(-1);


$post_page_no_thumbnail_args = [ 
  'post_type'      => array('post' , 'page'),
  'posts_per_page' => -1,
  'meta_query' => [
    [
      'key' => '_thumbnail_id',
      'value' => '',
      'compare' => 'NOT EXISTS'
    ]
  ]
];
$post_page_no_thumbnail = new WP_Query($post_page_no_thumbnail_args);

if ($post_page_no_thumbnail->posts){
  foreach ( $post_page_no_thumbnail->posts as $post ){
   //   echo 'on traite le post id ' . $post->ID;
   createThumbnailsByPostTitle(  $post->ID );
  }
}
set_time_limit( $backup_max_execution_time );
*/

add_action('save_post' , 'vinvin_generated_thumbnails', 10, 3);
function vinvin_generated_thumbnails( $post_id , $post , $update){
 // die('qsdsq');
  if( ! has_post_thumbnail( $post_id ) && $update == false){
      createThumbnailsByPostTitle(  $post_id );
  }
}