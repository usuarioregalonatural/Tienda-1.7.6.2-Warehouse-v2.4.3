<?php

/**
 * Title         : Aqua Resizer
 * Description   : Resizes WordPress images on the fly
 * Version       : 1.2.0
 * Author        : Syamil MJ
 * Author URI    : http://aquagraphite.com
 * License       : WTFPL - http://sam.zoy.org/wtfpl/
 * Documentation : https://github.com/sy4mil/Aqua-Resizer/
 *
 * @param string  $url    - (required) must be uploaded using wp media uploader
 * @param int     $width  - (required)
 * @param int     $height - (optional)
 * @param bool    $crop   - (optional) default to soft crop
 * @param bool    $single - (optional) returns an array if false
 * @uses  wp_upload_dir()
 * @uses  image_resize_dimensions()
 * @uses  wp_get_image_editor()
 *
 * @return str|array
 */

if(!class_exists('Rev_Aq_Resize')) {
    class Rev_Aq_Resize
    {
        /**
         * The singleton instance
         */
        static private $instance = null;

        /**
         * No initialization allowed
         */
        private function __construct() {}

        /**
         * No cloning allowed
         */
        private function __clone() {}

        /**
         * For your custom default usage you may want to initialize an Aq_Resize object by yourself and then have own defaults
         */
        static public function getInstance() {
            if(self::$instance == null) {
                self::$instance = new self;
            }

            return self::$instance;
        }

        /**
         * Run, forest.
         */
        public function process( $url, $width = null, $height = null, $crop = null, $single = true, $upscale = false ) {
            if ( ! $url || ( ! $width && ! $height ) ) return false;
            $upload_dir = UniteFunctionsWPRev::getUrlUploads();
            $upload_url = uploads_url();
            
            $http_prefix = "http://";
            $https_prefix = "https://";
            if(!strncmp($url,$https_prefix,strlen($https_prefix))){ 
                $upload_url = str_replace($http_prefix,$https_prefix,$upload_url);
            }
            elseif(!strncmp($url,$http_prefix,strlen($http_prefix))){
                $upload_url = str_replace($https_prefix,$http_prefix,$upload_url);      
            }
            if ( false === strpos( $url, $upload_url ) ) return false;
            $rel_path = str_replace( $upload_url, '', $url );
            $img_path = $upload_dir . $rel_path;
            if ( ! file_exists( $img_path ) or ! getimagesize( $img_path ) ) return false;
            $info = pathinfo( $img_path );
            $ext = $info['extension'];
            list( $orig_w, $orig_h ) = getimagesize( $img_path );
            $dims = image_resize_dimensions( $orig_w, $orig_h, $width, $height, $crop );
            $dst_w = $dims[4];
            $dst_h = $dims[5];
            if ( ! $dims && ( ( ( null === $height && $orig_w == $width ) xor ( null === $width && $orig_h == $height ) ) xor ( $height == $orig_h && $width == $orig_w ) ) ) {
                $img_url = $url;
                $dst_w = $orig_w;
                $dst_h = $orig_h;
            } else {
                $suffix = "{$dst_w}x{$dst_h}";
                $dst_rel_path = str_replace( '.' . $ext, '', $rel_path );
                $destfilename = "{$upload_dir}{$dst_rel_path}-{$suffix}.{$ext}";

                if ( ! $dims || ( true == $crop && false == $upscale && ( $dst_w < $width || $dst_h < $height ) ) ) {
                    return false;
                }
                elseif ( file_exists( $destfilename ) && getimagesize( $destfilename ) ) {
                    $img_url = "{$upload_url}{$dst_rel_path}-{$suffix}.{$ext}";
                }
                else {
                    $editor = wp_get_image_editor( $img_path );
                    if ( is_wp_error( $editor ) || is_wp_error( $editor->resize( $width, $height, $crop ) ) )
                        return false;
                    $resized_file = $editor->save();
                    if ( ! is_wp_error( $resized_file ) ) {
                        $resized_rel_path = str_replace( $upload_dir, '', $resized_file['path'] );
                        $img_url = $upload_url . $resized_rel_path;
                    } else {
                        return false;
                    }
                }
            }


            if ( true === $upscale ) remove_filter( 'image_resize_dimensions', array( $this, 'aq_upscale' ) );


            if ( $single ) {

                $image = $img_url;
            } else {

                $image = array (
                    0 => $img_url,
                    1 => $dst_w,
                    2 => $dst_h
                );
            }

            return $image;
        }

        /**
         * Callback to overwrite WP computing of thumbnail measures
         */
        function aq_upscale( $default, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {
            if ( ! $crop ) return null; // Let the wordpress default function handle this.

            // Here is the point we allow to use larger image size than the original one.
            $aspect_ratio = $orig_w / $orig_h;
            $new_w = $dest_w;
            $new_h = $dest_h;

            if ( ! $new_w ) {
                $new_w = intval( $new_h * $aspect_ratio );
            }

            if ( ! $new_h ) {
                $new_h = intval( $new_w / $aspect_ratio );
            }

            $size_ratio = max( $new_w / $orig_w, $new_h / $orig_h );

            $crop_w = round( $new_w / $size_ratio );
            $crop_h = round( $new_h / $size_ratio );

            $s_x = floor( ( $orig_w - $crop_w ) / 2 );
            $s_y = floor( ( $orig_h - $crop_h ) / 2 );

            return array( 0, 0, (int) $s_x, (int) $s_y, (int) $new_w, (int) $new_h, (int) $crop_w, (int) $crop_h );
        }
    }
}





if(!function_exists('rev_aq_resize')) {

    /**
     * This is just a tiny wrapper function for the class above so that there is no
     * need to change any code in your own WP themes. Usage is still the same :)
     */
  
    
//    function rev_aq_resize_custom( $url, $width = null, $height = null, $crop = null, $single = true, $upscale = false ) {
//       // $aq_resize = Rev_Aq_Resize::getInstance();      
//       // return $aq_resize->process( $url, $width, $height, $crop, $single, $upscale ); 
//        if($width<300){
//            $width = 300;
//        }
//        if($height<200){
//            $width = 200;
//        }
//        $name = basename($url);
//        $extension = pathinfo($url, PATHINFO_EXTENSION);
//        $only_file_name = pathinfo($url,PATHINFO_FILENAME);
//        $image_url = $url;
//        $image_dir = RevSliderFunctionsWP::getImageDirFromUrl($url);
//        
//        $image_url_without_name = str_replace($name, '', $image_url);
//        $image_dir_without_name = str_replace($name, '', $image_dir);
//        
//        $new_image_dir = $image_dir_without_name.$only_file_name.'-'.$width.'x'.$height.'.'.$extension;
//        $new_image_url = $image_url_without_name.$only_file_name.'-'.$width.'x'.$height.'.'.$extension;
//        
//        if(!file_exists($new_image_dir)){
//            $res_img = new ImageToolsHelper(); 
//            $res_img->resize($image_dir,$new_image_dir, $width, $height); 
//        }
//        
//        if(file_exists($new_image_dir)){
//            return $new_image_url;
//        }
//        
//    }
//    
    function rev_aq_resize( $url, $width = null, $height = null, $crop = null, $single = true, $upscale = false ) {
        $aq_resize = Rev_Aq_Resize::getInstance();
        return $aq_resize->process( $url, $width, $height, $crop, $single, $upscale );
    }
    
}


