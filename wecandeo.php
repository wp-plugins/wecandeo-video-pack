<?php

/*
 * Plugin Name: WECANDEO - VIDEO PACK
 * Plugin URI: http://www.wecandeo.com/
 * Description: With this plug-in, you can embed to posts and pages the videos that is uploaded in VIDEO PACK. It also can directly upload videos easily. In order to use the plug-in, is required WECANDEO - VIDEO PACK account and API Key.
 * Version: 0.1.7
 * Author: mgoonmedia
 * Author URI: http://www.wecandeo.com/
 * Requires at least: 3.8
 * Tested up to: 3.8
 * Text Domain: wecandeo
 * @version 0.1.7
 * @package wecandeo
 * @category Core
 * @author mgoonmedia
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WCANDEO_PATH', plugin_dir_path(__FILE__));
define('WCANDEO_PLAY_DOMAIN', "play.wecandeo.com");
define('WCANDEO_API_DOMAIN', "api.wecandeo.com");

require_once WCANDEO_PATH . 'admin/admin.php';


if (!class_exists('WecanDeo')):

    class WecanDeo {

        private $admin;
        var $version = '0.1.7';
        var $settings = array(
            'width' => '700',
            'height' => '700'
        );

        public function __construct() {

            $this->admin = new WecanDeo_Admin();
            
			###언어팩 호출
			add_action('init', array(__CLASS__, 'localize'));
            
            ###admin sector
            if (is_admin()) { // 어드민
                $this->admin->init_admin_page();
            } else { // everything but admin
            }

            ###short code
            add_shortcode('wecandeo', array(&$this, 'shortcode_wecandeo'));

        }

        public function WecanDeo() {
            //$this->__construct();
        }
        
        ###언어팩 호출
        public static function localize()
		{
			load_plugin_textdomain(
				'wecandeo',
				false,
				dirname(plugin_basename(__FILE__)) . '/LANG/'
			);
		}

        ###short code
        public function shortcode_wecandeo($atts, $content = '') {
            $origatts = $atts;
            $content = $this->wpuntexturize($content);

            ###Handle malformed WordPress.com shortcode format
            ###잘못된 WordPress.com의 단축 코드 형식을 처리
            if (isset($atts[0])) {
                $atts = $this->attributefix($atts);
                $content = $atts[0];
                unset($atts[0]);
            }

            ###No $content value
            if (empty($content)) {
                return 'error wecandeo shotcode';
            }

            ###Set any missing $atts items to the defaults
            ###빈값이면 기본 지정된값으로 치환
            $atts = shortcode_atts($this->settings, $atts);

            ###Allow other plugins to modify these values (for example based on conditionals)
            $atts = apply_filters('wecandeo_shortcodeatts', $atts, 'wecandeo', $origatts);
            
            ###Converts value to nonnegative integer.
            ###음수 양수로 변환
            $atts['width'] = absint($atts['width']);
            $atts['height'] = absint($atts['height']);

            return '<iframe frameborder="0" style="' . esc_attr('width:' . $atts['width'] . 'px;height:' . $atts['height'] . 'px;') . '" src="' . esc_url('http://' . WCANDEO_PLAY_DOMAIN . '/view/v1/?key=' . $content) . '"></iframe>';
        }
        
        ###Replace special characters
        ###특수문자 치환
        function wpuntexturize($text) {
            $find = array('&#8211;', '&#8212;', '&#215;', '&#8230;', '&#8220;', '&#8217;s', '&#8221;', '&#038;');
            $replace = array('--', '---', 'x', '...', '``', '\'s', '\'\'', '&');
            return str_replace($find, $replace, $text);
        }
        
        ###If not specified, the value
        ###지정되지않는값
        function attributefix($atts = array()) {
            // Quoted value
            if (0 !== preg_match('#=("|\')(.*?)\1#', $atts[0], $match))
                $atts[0] = $match[2];

            // Unquoted value
            elseif ('=' == substr($atts[0], 0, 1))
                $atts[0] = substr($atts[0], 1);

            return $atts;
        }

    }

    //class end

    $WecanDeo = new WecanDeo();

	###oEmbed provider regist
	wp_oembed_add_provider( '#http://' . str_replace('.', '\.', WCANDEO_PLAY_DOMAIN) . '/video/v/.*#i', 'http://' . WCANDEO_API_DOMAIN . '/oembed/', true );

endif;
?>
