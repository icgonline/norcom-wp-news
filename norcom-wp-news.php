<?php

/*

Plugin Name: Northern Commercials News

Plugin URI: https://icg.agency

Description: Plugin to migrate old news into WP.

Version: 1.0

Author: Doug Mouncey

Author URI: https://icg.agency

License: GPLv2 or later

Text Domain: norcomnews

*/

/*function find_wordpress_base_path() {
    $dir = dirname(__FILE__);
    do {
        //it is possible to check for other files here
        if( file_exists($dir."/wp-config.php") ) {
            return $dir;
        }
    } while( $dir = realpath("$dir/..") );
    return null;
}

define( 'BASE_PATH', find_wordpress_base_path()."/" );
define('WP_USE_THEMES', false);
global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
require(BASE_PATH . 'wp-load.php');

USE THIS URL: https://posts-norcom.icgonline.co.uk/wp-admin/plugins.php?domigrate=true
*/
define('SITE_ROOT', str_replace( 'wp-content', '', WP_CONTENT_DIR));

require_once(SITE_ROOT . 'wp-admin' . '/includes/image.php');
require_once(SITE_ROOT . 'wp-admin' . '/includes/file.php');
require_once(SITE_ROOT . 'wp-admin' . '/includes/media.php');

class newsSync {

  public function __construct() {
      $this->wpdb2 = new WPDB( 'hhZx240', 'Iu4lu@34', 'norcom', 'localhost');
      $this->currentTime = time();
      $this->newsArray = [];

  }

  public function emailError($id, $title, $data){
    $to = 'liam@icg.agency';
    $subject = 'Problem migrating news item'.$id;
    $body = 'Problem with: '.$id.'<br/>';
    $body .= $title;
    $body .= "<br/>----------------------------<br/>";
    $body .= $data;
    // To send HTML mail, the Content-type header must be set
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=iso-8859-1';

    // Additional headers
    $headers[] = 'From: James Sharp Error <error@news-jamessharp.icgonline.co.uk>';
    mail( $to, $subject, $body, implode("\r\n", $headers) );
  }

  public function logError($id, $title, $data){
    $subject = 'Problem migrating news item'.$id;
    $body = 'Problem with: '.$id.'<br/>';
    $body .= $title;
    $body .= $data;
    write_log($subject);
    write_log($body);
  }




// Note, there are two types of way it could fail,
// the fail2 fail is when try to unserialise just
// false, it should fail. Also note, what you
// do when something fails is up to your app.
// So replace var_dump("fail...") with your
// own app logic for error handling
public function unserializeSensible($value) {
    $caught = false;
    try {
        $unserialised = unserialize($this->serialize_corrector($value));
    } catch(ErrorException $e) {
        var_dump("fail");
        $caught = true;
        return false;
    }
    // PHP doesn't have a try .. else block like Python
    if(!$caught) {
      //In case of failure let's try to repair it
        if($unserialised === false){
            $repairedSerialization = $this->fix_serialized($value);
            $unserialised = unserialize($repairedSerialization);
        }
        if($unserialised === false && $value !== serialize(false)) {
            var_dump("fail2");
            return false;
        } else {
            var_dump("pass");
            return $unserialised;
        }
    }
}

    function add_quotes($str) {
        return sprintf("'%s'", $str);
    }

    function serialize_corrector($serialized_string){
    // at first, check if "fixing" is really needed at all. After that, security checkup.
        if ( @unserialize($serialized_string) !== true &&  preg_match('/^[aOs]:/', $serialized_string) ) {
            $serialized_string = preg_replace_callback( '/s\:(\d+)\:\"(.*?)\";/s',    function($matches){return 's:'.strlen($matches[2]).':"'.$matches[2].'";'; },   $serialized_string );
        }
        return $serialized_string;
    }

    function fix_str_length($matches) {
      $string = $matches[2];
      $right_length = strlen($string); // yes, strlen even for UTF-8 characters, PHP wants the mem size, not the char count
      return 's:' . $right_length . ':"' . $string . '";';
    }
    function fix_serialized($string) {
        // securities
        if ( !preg_match('/^[aOs]:/', $string) ) return $string;
        if ( @unserialize($string) !== false ) return $string;
        $string = preg_replace("%\n%", "", $string);
        // doublequote exploding
        $data = preg_replace('%";%', "µµµ", $string);
        $tab = explode("µµµ", $data);
        $new_data = '';
        foreach ($tab as $line) {
            $new_data .= preg_replace_callback('%\bs:(\d+):"(.*)%', 'self::fix_str_length', $line);
        }
        return $new_data;
    }

    public function run() {



      $categories = array('template');
      $newcategories = array('general');

      $catsfordb = implode(',', array_map('self::add_quotes', $categories));

      $rows = $this->wpdb2->get_results("SELECT * from post where post_type_id IN ("1") AND is_active >= '1' ORDER BY id");
      //var_dump($rows);
      //exit;
      //echo "<ul>";
      foreach ($rows as $obj) {
        $slug = $obj->slug;
        $id = $obj->id;
        $type =  $obj->post_type_id;
        $newtype = null;
        $catid = 0;
        foreach ($categories as $key => $cat) {
          if($cat == $type){
            $newtype = $newcategories[$key];
            $post_category = get_term_by('slug', $newtype, 'category');
            if ( $post_category instanceof WP_Term ) {
                $catid = $post_category->term_id;
            }
          }
        }
        $f1 = preg_replace("/^.*\//","",$slug);
        $title = $obj->title;
        $published = $obj->is_active;
        $deleted = $obj->date_down;
        $published_date = $obj->date_created;
        $data = null;
        //var_dump($published_date);
        if(isset($obj->data)){
            $data = $obj->data;
            //echo "<br>";
            //var_dump($obj->data);
            //echo "<br>";
            $content_data = $this->unserializeSensible($data);
            //var_dump($content_data);
            //echo $id."-".$title;

            if($content_data === false){
              $this->logError($id, $title, $data);
              continue;
            }
            //var_dump($content);
            $excerpt = '';
            if(isset($content_data->snippet)){
              $excerpt = $content_data->label;
            }
            $content = '';
            if(isset($content_data->content)){
              $content = $content_data->content_1;
            }
            $date = $content_data->visible_from;
            //var_dump($date);
            $canonical = false;
            if(isset($content_data->meta_canonical)){
              $canonical = $content_data->meta_canonical;
              //Set this using Yoast
            }
            $banner = false;
            if(isset($content_data->banner)){
              $banner = $content_data->banner;
              //Set this using Yoast
            }

            $status = "publish";
            if($published != 1){
              $status = "draft";
            }
             //echo "<li>".$slug."</li>";
            if($deleted == 1){
              echo "This post is deleted.";
            }

             if($deleted != 1){
                $content = html_entity_decode($content);
               $content = preg_replace("/<img[^>]+\>/i", " ", $content);
                $content = apply_filters('the_content', $content);
                $content = str_replace(']]>', ']]>', $content);

               echo "Entering post array";
             $_postArray = [
                'ID' => 0,
                'post_author' => 1,
                'post_content' => '<!-- wp:html -->'.$content.'<!-- /wp:html -->',
                'post_excerpt' => html_entity_decode($excerpt),
                'post_title' => html_entity_decode($title),
                'post_category' => array( $catid ),
                'post_name'=> $f1,
                'post_status' => $status,
                'post_date' => date("Y-m-d H:i:s", substr($published_date, 0, 10)),
                'comment_status' => 'closed',
                'ping_status' => 'closed',
                'post_type' => 'post'
            ];
                echo "Trying to post <br/>";
                kses_remove_filters();
                $result = wp_insert_post($_postArray, true);
                kses_init_filters();
                //var_dump($_postArray);

                if(!is_wp_error($result)){
                  echo "Result: ".$result;
                  if($canonical){
                    add_post_meta( $result, '_yoast_wpseo_canonical', $canonical, true );
                  }
                  if($banner){
                    $image = media_sideload_image( $banner, $result, $title, 'id' );
                    if($image && !is_wp_error($image)){
                      set_post_thumbnail($result, $image);
                      wp_update_post( array(
                              'ID' => $image,
                              'post_parent' => $result
                          )
                      );
                    }
                  }
                }
                if($result == 0 || is_wp_error($result)){
                  var_dump($result);
                }

            }
        }
        //if(!isset($obj->data)){
        //  echo "No content data";
        //}
      }
      //echo "</ul>";

    }

}

function migrate_news()
{
  if ( isset($_GET['domigrate']) ) {
    $m = new newsSync();
    $m->run();
  }
}
add_action('init', 'migrate_news');

function exception_error_handler($errno, $errstr, $errfile, $errline ) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
}

set_error_handler("exception_error_handler");
