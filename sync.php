<?php
/**
 * wp2wb Sync functions.
 *
 * @package     wp2wb
 * @author      Ryan ( and sTeeLM)
 * @license     GPL 3.0
 * @version     1.1
 */

if ( get_option('wp2wb_sync') == 'enable' ) {
    add_action('admin_menu', 'wp2wb_sync_add_sidebox');
    if ( get_option('wp2wb_update_sync') == 'true' ) {
        add_action('publish_post', 'wp2wb_update_sync_publish');
    } elseif ( get_option('wp2wb_update_sync') == 'false' ) {
        add_action('publish_post', 'wp2wb_sync_publish');
    }
}

// Add Sync Sidebox.
if ( !function_exists( 'wp2wb_sync_sidebox' ) ) {
    function wp2wb_sync_sidebox() {
        global $post;
        echo '<p><label for="publish_no_sync"><input id="publish_no_sync" type="checkbox" name="publish_no_sync" value="true" />'.__( ' Don&#039;t Sync Post', 'wp2wb' ).'</label></p>';
    }
}

if ( !function_exists( 'wp2wb_sync_add_sidebox' ) ) {
    function wp2wb_sync_add_sidebox() {
        add_meta_box('wp2wb_sync_sidebox', __( 'Sync Setting', 'wp2wb' ), 'wp2wb_sync_sidebox', 'post', 'side', 'high');
    }
}

require_once __DIR__ . '/vendor/autoload.php';

if ( !function_exists( 'wp2wb_trans_html_to_img' ) ) {
    function wp2wb_trans_html_to_img($post_content) {
        $p = 'P';
        $uniq_name = md5($post_content).'__'.time();
        $temp_base = '/tmp/wp2wb2/';
        $pdf_file_name = $uniq_name.'.pdf';
        $png_file_name = $uniq_name.'.png';
        $pdf_file_path = $temp_base.$pdf_file_name;
        $png_file_path = $temp_base.$png_file_name;

        mkdir($temp_base);

        $css = file_get_contents(__DIR__ . get_option('wp2wb_html2img_css'));

        $html = '<!doctype html><html lang="zh_CN"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></meta>'.
            '<style>'.$css.'</style></head><body>'.$post_content.
            '</body></html>';

        $img_width = get_option('wp2wb_html2img_width');

        $defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        $mpdf= new \Mpdf\Mpdf([
            'fontDir' => array_merge($fontDirs, [
                __DIR__ . '/assets/fonts',]),
            'fontdata' => $fontData + [
                'pingfang' => [
                'R' => 'PingFang Light.ttf',
                'B' => 'PingFang Bold.ttf',
                ]],
            'default_font' => 'pingfang',
            'mode' => 'utf-8', 
            'format' => [$img_width, 1000000], 
            'margin_left' => 1, 
            'margin_right' => 1, 
            'margin_top' => 0, 
            'margin_bottom' => 0]);

        if (get_option('wp2wb_html2img_watermark')) {
            $mpdf->SetWatermarkText(get_option('wp2wb_html2img_watermark_txt'), 
                get_option('wp2wb_html2img_watermark_alpha'));
            $mpdf->showWatermarkText = true;
        }

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->SetDisplayMode('fullpage');
        $mpdf->WriteHTML($html);
        $mpdf->_setPageSize(array($img_width, $mpdf->y), $p);
        $mpdf->addPage();
        $mpdf->WriteHTML($html);
        $mpdf->DeletePages(1,1);
        $mpdf->Output($pdf_file_path);

        $im = new Imagick();
        $im->setResolution(300, 300);
        $im->setCompressionQuality(100);

        $im->readImage($pdf_file_path);
        //$im->setImageResolution(96, 96);
        //$im->setImageUnits(imagick::RESOLUTION_PIXELSPERINCH);
        $im->resetIterator();
        $imgs = $im->appendImages(true);
        $imgs->setImageFormat("png");
        $imgs->writeImage($png_file_path);
        $imgs->clear();
        $imgs->destroy();
        $im->clear();
        $im->destroy();

        unlink($pdf_file_path);
        return $png_file_path;
    }
}

// Update Sync Function.
if ( !function_exists('wp2wb_update_sync_publish') ) {
    function wp2wb_update_sync_publish($post_ID) {
        global $post;
        $remove_image = false;
        if (isset($post) && $post->post_type != 'post' || isset($_POST['publish_no_sync'])) return;
        $post = get_post($post_ID);
        $access_token = get_option('wp2wb_access_token');
        $headers = array();
        $headers[] = "Authorization: OAuth2 ".$access_token;
        $post_title = wp2wb_replace(get_the_title($post_ID));
        $post_url = get_permalink($post_ID);
        $post_content = $post -> post_content;
        $pic_src = wp2wb_post_img_src($post_ID);

        if ( get_option('wp2wb_weibo_type') == 'simple' ) {
            $apiurl = 'https://api.weibo.com/2/statuses/share.json';
            $status = sprintf( __( '%1$s: %2$s.', 'wp2wb' ), $post_title, $post_url );
            if( !empty($pic_src) || get_option('wp2wb_html2img') == 'true') {
                if(!empty($pic_src)) {
                    $pic_file = str_replace(home_url(),$_SERVER["DOCUMENT_ROOT"],$pic_src);
                } elseif (get_option('wp2wb_html2img') == 'true') {
                    $pic_file = wp2wb_trans_html_to_img($post_content);
                    $remove_image = true;
                }
                if( !empty($pic_file) ) {
                    $file_content = file_get_contents($pic_file);
                } else {
                    $file_content = file_get_contents($pic_src);
                }

                if($remove_image && !empty($pic_file) ) {
                    unlink($pic_file);
                }

                $array = explode('?', basename($pic_src));
                $file_name = $array[0];
                $sep = uniqid('------------------');
                $mpSep = '--'.$sep;
                $endSep = $mpSep. '--';
                $multibody = '';
                $multibody .= $mpSep . "\r\n";
                $multibody .= 'Content-Disposition: form-data; name="pic"; filename="' . $file_name . '"' . "\r\n";
                $multibody .= "Content-Type: image/unknown\r\n\r\n";
                $multibody .= $file_content. "\r\n";
                $multibody .= $mpSep . "\r\n";
                $multibody .= 'content-disposition: form-data; name="status' . "\"\r\n\r\n";
                $multibody .= urlencode($status)."\r\n";
                $multibody .= $endSep;
                $headers[] = "Content-Type: multipart/form-data; boundary=" . $sep;
                $data = $multibody;
            } else {
                $data = "status=" . urlencode($status);
            }
        }

        if ( get_option('wp2wb_weibo_type') == 'article' ) {
            $apiurl = "https://api.weibo.com/proxy/article/publish.json";
            $original_link = sprintf( __( '<p>Click here for details: <a href="%1$s">%2$s</a>.</p>', 'wp2wb' ), $post_url, $post_title );
            $content = wp2wb_replace_code($post_content) . $original_link;
            if( !empty($pic_src) ) {
                $cover = $pic_src;
            } else {
                $cover = plugins_url('/assets/default-cover.jpg',__FILE__);
            }
            $data = array(
                'title' => $post_title,
                'content' => rawurlencode($content),
                'cover' => $cover,
                'text' => $post_title,
                'access_token' => $access_token,
            );
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiurl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);
        
        // debug
        // $results = json_decode($response);
        // var_dump($results);
        // echo '<hr />';
        // var_dump($data);
    }
}

// Sync Function.
if ( !function_exists('wp2wb_sync_publish') ) {
    function wp2wb_sync_publish($post_ID) {
        global $post;
        $remove_image = false;
        if (!wp_is_post_revision($post_ID) && $post->post_status != 'publish'){
            if (isset($post) && $post->post_type != 'post' || isset($_POST['publish_no_sync'])) return;
            $post = get_post($post_ID);
            $access_token = get_option('wp2wb_access_token');
            $headers = array();
            $headers[] = "Authorization: OAuth2 ".$access_token;
            $post_title = wp2wb_replace(get_the_title($post_ID));
            $post_url = get_permalink($post_ID);
            $post_content = $post -> post_content;
            $pic_src = wp2wb_post_img_src($post_ID);

            if ( get_option('wp2wb_weibo_type') == 'simple' ) {
                $apiurl = 'https://api.weibo.com/2/statuses/share.json';
                $status = sprintf( __( '%1$s: %2$s.', 'wp2wb' ), $post_title, $post_url );
                if( !empty($pic_src) || get_option('wp2wb_html2img') == 'true' ) {
                    if(!empty($pic_src)) {
                        $pic_file = str_replace(home_url(),$_SERVER["DOCUMENT_ROOT"],$pic_src);
                    } elseif (get_option('wp2wb_html2img') == 'true') {
                        $pic_file = wp2wb_trans_html_to_img($post_content);
                        $remove_image = true;
                    }
                    if( !empty($pic_file) ) {
                        $file_content = file_get_contents($pic_file);
                    } else {
                        $file_content = file_get_contents($pic_src);
                    }

                    if($remove_image && !empty($pic_file) ) {
                        unlink($pic_file);
                    }

                    $array = explode('?', basename($pic_src));
                    $file_name = $array[0];
                    $sep = uniqid('------------------');
                    $mpSep = '--'.$sep;
                    $endSep = $mpSep. '--';
                    $multibody = '';
                    $multibody .= $mpSep . "\r\n";
                    $multibody .= 'Content-Disposition: form-data; name="pic"; filename="' . $file_name . '"' . "\r\n";
                    $multibody .= "Content-Type: image/unknown\r\n\r\n";
                    $multibody .= $file_content. "\r\n";
                    $multibody .= $mpSep . "\r\n";
                    $multibody .= 'content-disposition: form-data; name="status' . "\"\r\n\r\n";
                    $multibody .= urlencode($status)."\r\n";
                    $multibody .= $endSep;
                    $headers[] = "Content-Type: multipart/form-data; boundary=" . $sep;
                    $data = $multibody;
                } else {
                    $data = "status=" . urlencode($status);
                }
            }

            if ( get_option('wp2wb_weibo_type') == 'article' ) {
                $apiurl = "https://api.weibo.com/proxy/article/publish.json";
                $original_link = sprintf( __( '<p>Click here for details: <a href="%1$s">%2$s</a>.</p>', 'wp2wb' ), $post_url, $post_title );
                $content = wp2wb_replace_code($post_content) . $original_link;
                if( !empty($pic_src) ) {
                    $cover = $pic_src;
                } else {
                    $cover = plugins_url('/assets/default-cover.jpg',__FILE__);
                }
                $data = array(
                    'title' => $post_title,
                    'content' => rawurlencode($content),
                    'cover' => $cover,
                    'text' => $post_title,
                    'access_token' => $access_token,
                );
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiurl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            $response = curl_exec($ch);
            curl_close($ch);
            $results = json_decode($response);
            
            // debug
            // var_dump($results);
            // echo '<hr />';
            // var_dump($data);
        }
    }
}

// Get Post Image Src.
if ( !function_exists( 'wp2wb_post_img_src' ) ) {
    function wp2wb_post_img_src($post_ID) {
        global $post;
        if ( has_post_thumbnail() ) {
            $thumbnail_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID),'full');
            $post_img_src = $thumbnail_src [0];
        } else {
            $content = get_post( $post_ID )->post_content;
            $output = preg_match_all('/<img.*?(?: |\\t|\\r|\\n)?src=[\'"]?(.+?)[\'"]?(?:(?: |\\t|\\r|\\n)+.*?)?>/sim', $content, $strResult, PREG_PATTERN_ORDER);
            if ( $output ) {
                $post_img_src = $strResult [1][0];
            } else {
                $post_img_src = '';
            }
        }
        return $post_img_src;
    }
}

// Replace Escape Character.
if (!function_exists('wp2wb_replace')) {
    function wp2wb_replace($str) {
        $a = array('&#160;', '&#038;', '&#8211;', '&#8216;', '&#8217;', '&#8220;', '&#8221;', '&amp;', '&lt;', '&gt', '&ldquo;', '&rdquo;', '&nbsp;', 'Posted by Wordmobi');
        $b = array(' ', '&', '-', '‘', '’', '“', '”', '&', '<', '>', '“', '”', ' ', '');
        $str = str_replace($a, $b, strip_tags($str));
        return trim($str);
    }
}

// Replace <pre>-><blockquote> and <code>-><i> in Sina Article.
// Because sina toutiao article don't support the pre and code tags.
if (!function_exists('wp2wb_replace_b')) {
    function wp2wb_replace_code($str) {
        $strtemp = trim($str); 
        $search = array('/<pre>(.+?)<\/pre>/is', '/<code>(.+?)<\/code>/is');
        $replace = array('<blockquote>\1</blockquote>', '<i>\1</i>');
        $text = preg_replace($search, $replace, $strtemp);
        return $text;
    }
}
