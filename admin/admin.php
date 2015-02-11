<?php
if (!defined('ABSPATH')) {
    exit;
}

class WecanDeo_Admin {

    public function __construct() {
        
    }

    public function init_admin_page() {



        ###Show activation entered
        ###활성화시 문구 보여주기
        $this->custom_post_activation_notice();

        $wecandeo_api_key = get_option('wecandeo_api_key');

        ###page Implementation
        add_action('admin_menu', array(&$this, 'create_menu'));

        ###buttons Implementation
        add_action('media_buttons', array(&$this, 'media_button'), 90);

        // Add actions to save video thumbnails when saving 포스트 저장할때
		add_action('save_post', array(&$this, 'autoset_featured_image'));
    }

    public function autoset_featured_image($post_id) {

		global $post;

		$already_has_thumb = has_post_thumbnail($post->ID);
		error_log($already_has_thumb ? "[{$post->ID}]already setted image" : "[{$post->ID}]not setted image");

		// First check whether Post Thumbnail is already set for this post.
		if (get_post_meta($post_id, '_thumbnail_id', true) || get_post_meta($post_id, 'skip_post_thumb', true)) {
			return;
		}

/*
          // Don't save video thumbnails during autosave or for unpublished posts
          // 임시 저장시 썸네일 저장 안함
          if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return null;
          if ( get_post_status( $post_id ) != 'publish' ) return null;
*/

		// try to get wecandeo video thumbnail
		if (!$already_has_thumb)
		{
			$text = $_REQUEST['content'];
			error_log("Content Text => ".$text);
			preg_match("/([a-zA-Z0-9\-\_]+\.|)wecandeo\.com\/video\/v\/(\?key\=)([a-zA-Z0-9\-\_]{46})([^<\s]*)/", $text, $matches2);

			error_log("wecandeo play url => {$matches2[0]} / video key => {$matches2[3]}");

			if (!$matches2[0])
				return;

			$imageurl = "http://{WCANDEO_API_DOMAIN}/video/thumbnail?k={$matches2[3]}";

			// Generate thumbnail
			$thumb_id = $this->wecandeo_generate_post_thumb($imageurl, $post_id);

			// If we succeed in generating thumg, let's update post meta
			if ($thumb_id) {
				update_post_meta( $post_id, '_thumbnail_id', $thumb_id );
			}
		}

		return;
	}


	/**
	 * Function to get image from url & check if is an available image.
	 */
	function wecandeo_generate_post_thumb($imageUrl, $post_id) {

		// Get the file name
		$filename = "POST_{$post_id}_Video1Thumb.jpg";

		if (!(($uploads = wp_upload_dir(current_time('mysql')) ) && false === $uploads['error'])) {
			return null;
		}

		// Generate unique file name
		$filename = wp_unique_filename( $uploads['path'], $filename );

		// Move the file to the uploads dir
		$new_file = $uploads['path'] . "/$filename";

		if (!ini_get('allow_url_fopen')) {
			$file_data = $this->curl_get_file_contents($imageUrl);
		} else {
			$file_data = @file_get_contents($imageUrl);
		}

		if (!$file_data) {
			return null;
		}

		file_put_contents($new_file, $file_data);

		// Set correct file permissions
		$stat = stat( dirname( $new_file ));
		$perms = $stat['mode'] & 0000666;
		@ chmod( $new_file, $perms );

		// Get the file type. Must to use it as a post thumbnail.
		$wp_filetype = wp_check_filetype( $filename, null );

		extract( $wp_filetype );

		// No file type! No point to proceed further
		if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) ) {
			return null;
		}

		// Compute the URL
		$url = $uploads['url'] . "/$filename";

		// Construct the attachment array
		$attachment = array(
			'post_mime_type' => $type,
			'guid' => $url,
			'post_parent' => null,
			'post_title' => $_REQUEST['post_title'],
			'post_content' => '',
		);

		$thumb_id = wp_insert_attachment($attachment, $new_file, $post_id);
		if ( !is_wp_error($thumb_id) ) {
			require_once(ABSPATH . '/wp-admin/includes/image.php');

			// Added fix by misthero as suggested
			wp_update_attachment_metadata( $thumb_id, wp_generate_attachment_metadata( $thumb_id, $new_file ) );
			update_attached_file( $thumb_id, $new_file );

			return $thumb_id;
		}

		return null;

	}

	/**
	 * Function to fetch the contents of URL using curl in absense of allow_url_fopen.
	 *
	 * Copied from user comment on php.net (http://in.php.net/manual/en/function.file-get-contents.php#82255)
	 */
	function curl_get_file_contents($URL) {
		$c = curl_init();
		curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($c, CURLOPT_URL, $URL);
		$contents = curl_exec($c);
		curl_close($c);

		if ($contents) {
			return $contents;
		}

		return FALSE;
	}

    ###create menu

    public function create_menu() {
        //설정 서브메뉴로
        add_submenu_page('options-general.php', __('WECANDEO - VP', 'wecandeo'), __('WECANDEO - VP', 'wecandeo'), 'manage_options', 'wecandeo', array(&$this, 'setting_menu'));
        ###left menu
        //add_plugins_page('wecandeo', __('Wecandeo'), 9, __('Wecandeo'), array(&$this, 'wecandeo_menu'));
        add_filter('plugin_action_links_' . 'wecandeo/wecandeo.php', array(&$this, 'add_action_links'));
    }

    public function media_button() {
        $wecandeo_api_key = get_option('wecandeo_api_key');

        //키가 없다면 팝업 HTML, css, 자바스크립트 미생성
        if ($wecandeo_api_key != '') {
            $this->upload_modal();
        }
        ?>

        <script>
			var WC_API_DOMAIN = "<?=WCANDEO_API_DOMAIN?>";
			var WC_PLAY_DOMAIN = "<?=WCANDEO_PLAY_DOMAIN?>";

            jQuery(document).ready(function() {
                jQuery(".wecandeo_add_video").click(function() {
        <?php if ($wecandeo_api_key != '') : ?>
                        jQuery("#wecandeo_modal_div").css("display", "block");

        <?php else : ?>
                        alert("<?php _e('You can use this plug-in after API KEY setup.', 'wecandeo'); ?>");//API KEY 를 설정 후 사용 가능합니다.
        <?php endif; ?>
                });
            });
        </script>

        <?php
    }

    ###modal javascript, css, html

    public function upload_modal() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');

        $wecandeo_api_key = get_option('wecandeo_api_key');

        ###다수의 에디터창 존재시
        $random_key = rand(1, 100);

        ###Internal sources
        wp_enqueue_script('swfobject');
        wp_enqueue_script('swfupload-handlers');

        ###Add source
        wp_enqueue_style('wecandeo_admin_css', plugins_url('/admin.css', __FILE__), false, '1.0', 'all');

        wp_enqueue_script('wecandeo_admin_js', plugins_url('/admin.js', __FILE__));
        wp_localize_script('wecandeo_admin_js', 'wecandeo_admin_vars', array(
            'random_key' => $random_key,
            'api_key' => $wecandeo_api_key,
            'path' => esc_url(plugins_url() . '/' . dirname(plugin_basename(__FILE__))),
            'plugins_url' => plugins_url(),
            'includes_url' => includes_url(),
            'file_selected' => '',
            'upload_tab' => 'f',
            'file_upload' => '',
            'package_id' => '',
            'folder_id' => '',
            'access_key' => '',
            'select_package_id' => '',
            'select_access_key' => ''
                )
        );
        //언어팩
        wp_localize_script('wecandeo_admin_js', 'wecandeo_admin_lang_vars', array(
            'LANG_SelectFolder' => __('Select a Video Folder', 'wecandeo'), //폴더 선택
            'LANG_SelectPackage' => __('Select a Publish Package', 'wecandeo'), //패캐지 선택
            'LANG_UploadFolderPackageSelectTheFile' => __('Select the file and Folder and Package to Upload video.', 'wecandeo'), //업로드할 폴더, 패키지, 파일을 선택 하십시오.
            'LANG_ItisPossibleToUploadFilesOneByOne' => __('You can upload files one by one.', 'wecandeo'), //파일은 한개씩 업로드가 가능 합니다.
            'LANG_VideoIsNotUploaded' => __('No video uploaded.', 'wecandeo'), //파일은 한개씩 업로드가 가능 합니다.
            'LANG_PleaseSelectThumbnail' => __('Please select a thumbnail.', 'wecandeo'), //썸네일을 선택해 주세요.
            'LANG_FailedToImportPackage' => __('Package information query failed.', 'wecandeo'), //패키지 가져오기 실패 하였습니다.
            'LANG_FilesAreAvailableOnlyUploadFilesLessThan2GB' => __('You can upload only files of less than 2GB.', 'wecandeo'), //2GB 이하의 파일만 업로드 가능 합니다.
            'LANG_CannotUploadZeroByteFiles' => __('Cannot upload 0Byte files.', 'wecandeo'), //0 바이트 파일을 업로드 할 수 없습니다.
            'LANG_InvalidFileType' => __('An unexpected error has occurred.', 'wecandeo'), //예상치 못한 오류가 발생 했습니다.
            'LANG_FailedToSetTheThumbnail' => __('Thumbnail setting is failed.', 'wecandeo'), //썸네일 설정 실패 하였습니다.
            'LANG_VideoPackageFails' => __('Video packaging is failed.', 'wecandeo'), //영상 패키징 실패 하였습니다.
            'LANG_VideoLoadingFailure' => __('Video loading is failed.', 'wecandeo'), //영상 로딩 실패 하였습니다.
            'LANG_NoVideoSelected' => __('No video selected.', 'wecandeo'), //선택된 비디오가 없습니다.
            'LANG_SelectedFileNameIsDisplayed' => __('File name is displayed.', 'wecandeo'), //선택한 파일명이 표시됩니다.
            'LANG_Waiting' => __('Waiting', 'wecandeo'), //대기중
            'LANG_Preparing' => __('Preparing', 'wecandeo'), //준비중
            'LANG_Uploading' => __('Uploading', 'wecandeo'), //업로딩
            'LANG_FailedToSetTheTitle' => __('Title setting is failed.', 'wecandeo')//제목 설정 실패 하였습니다.
                )
        );
        ?>

        <style>
            /*버튼*/
            .wecandeo_add_video span {
                background: url('<?php echo esc_url(plugins_url() . '/' . dirname(plugin_basename(__FILE__)) . '/images/camera-video.png'); ?>') no-repeat;
                height: 16px;
                width: 16px;
                padding: 0 0 1px 0;
                margin: 5px 5px 0 0;
                float: left;
            }
            @media print,
            (-o-min-device-pixel-ratio: 5/4),
            (-webkit-min-device-pixel-ratio: 1.25),
            (min-resolution: 120dpi) {
                .wecandeo_add_video span {
                    background: url('<?php echo esc_url(plugins_url() . '/' . dirname(plugin_basename(__FILE__)) . '/camera-video.png'); ?>') no-repeat;
                    background-size: 16px 16px;
                }
            }
        </style>

        <div class="wecandeo_add_video thickbox button" title="<?php _e('Add WECANDEO Video', 'wecandeo'); ?>">
            <span></span>
            <?php _e('Add WECANDEO Video', 'wecandeo'); //위캔디오 동영상 추가 ?>
        </div>

        <div id="wecandeo_modal_div" class="wecandeo-modal-outer" >
            <div class="wecandeo-modal" title="wecandeo video">
                <div class="wecandeo_modal_form_blind">
                    <div class='wecandeo_modal_status'>
                        <div  id="" class="wecandeo_state-text">Waiting...</div>
                        <div class="wcd-inner-bg">
                            <div class="wecandeo_inner_bar" style="position:absolute;left:0;background:url(<?php echo esc_url(plugins_url() . '/' . dirname(plugin_basename(__FILE__)) . '/images/progress.png'); ?>) 0 0 repeat-x;height:100%;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="wecondeo_m_media_frame_router">
                    <div class="wecondeo_m_media_router">
                        <a href="javascript://"  class="wecondeo_m_media_menu_item wecandeo_folder_top_btn nav-tab-active" ><?php _e('Upload directly', 'wecandeo'); //직접 업로드   ?></a>
                        <a href="javascript://"  class="wecondeo_m_media_menu_item wecandeo_package_top_btn" ><?php _e('Library', 'wecandeo'); //라이브러리   ?></a>
                        <button id="wecandeo_upload_top_close" type="button" class="wecandeo-modal-close"></button>
                    </div>
                </div>
                <div class="wecondeo_m_media_frame">
                    <!-- 중앙 컨텐츠 영역 시작 -->
                    <div class="wecondeo_m_media_frame_content">
                        <div class="wecondeo_m_uploader_inline">
                            <div class="wecondeo_m_uploader_inline_content">
                                <!--미디어영역-->
                                <div class="wecondeo_folder">
                                    <div class="wecondeo_file_top_area form-block" style="">
                                        <form id="wecondeo_form" method="post" enctype="multipart/form-data">
                                            <div class='wecondeo_file_search button'>
                                                <span id="wecondeo_span_button_place_holder"></span>
                                            </div>
                                            <div class='wecandeo_file_title'>
                                                <sapn id='wecandeo_file_title'>
                                                    <?php _e('Selected file name is displayed.', 'wecandeo'); //선택한 파일명이 표시 됩니다. ?>
                                                </sapn>
                                            </div>
                                        </form>
                                    </div>
                                    <div class="form-block">
                                        <input class="wecondeo_modal_new_title" type="text" value="" placeholder="<?php _e('Enter video title.', 'wecandeo'); //제목을 입력하세요.   ?>">
                                    </div>

                                    <div class="form-block">
                                        <select class="wecondeo_folder_select">
                                        </select>
                                        <select class="wecondeo_package_select">
                                        </select>
                                    </div>
                                    <div class='form-block wecandeo_file_control'>
                                        <button id="" class="wecondeo_start_upload browser button" type="button">
                                            <?php _e('Upload Video', 'wecandeo'); //동영상 올리기 ?>
                                        </button>
                                        <button id="" class="wecondeo_reset browser button" type="button">
                                            <?php _e('Cancel', 'wecandeo'); //삭제 ?>
                                        </button>
										<span style="display: inline-block;">※ <?php _e('Is represented by "Cannot find a resource" before encoding is complete.', 'wecandeo'); //인코딩이 완료되기까지 '리소스를 찾을 수 없습니다'로 표시됩니다. ?></span>
                                    </div>
                                    <div id="" class="form-block wecandeo_thumbnail_select">
                                        <ul>
                                            <li class="wecandeo_thumbnail_li">
                                                <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                                                    <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                                                </label>
                                            </li>
                                            <li class="wecandeo_thumbnail_li">
                                                <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                                                    <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                                                </label>
                                            </li>
                                            <li class="wecandeo_thumbnail_li">
                                                <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                                                    <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                                                </label>
                                            </li>
                                            <li class="wecandeo_thumbnail_li">
                                                <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                                                    <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                                                </label>
                                            </li>
                                            <li class="wecandeo_thumbnail_li">
                                                <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                                                    <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                                                </label>
                                            </li>
                                            <li class="wecandeo_thumbnail_li">
                                                <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                                                    <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                                                </label>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <!--패키지-->
                                <div class="wecondeo_package" style="display:none;">
                                    <div id="" class="wecandeo_modal_package_content">
                                        <select style="" id="" class="wecondeo_package_select_view">
                                        </select>
                                        <div id="" class="wecandeo_thumbnail_select_package">
                                            <ul></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- 중앙 컨텐츠 영역 끝 -->
                </div>
                <div class="ui-dialog-buttonpane ui-widget-content ui-helper-clearfix">
                    <div class="ui-dialog-buttonset">
                        <button id="wecandeo_upload_completed" class="browser button" type="button" role="button" aria-disabled="false">
                            <span class="ui-button-text"><?php _e('Insert into post');  ?></span>
                        </button>
                        <button id="wecandeo_upload_close" class="browser button" type="button" role="button" aria-disabled="false">
                            <span class="ui-button-text"><?php _e('Close Window'); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

            <ul id="wecandeo_thumbnail_ul" style="display: none;">
                <li class="wecandeo_thumbnail_li">
                    <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                        <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                    </label>
                </li>
                <li class="wecandeo_thumbnail_li">
                    <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                        <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                    </label>
                </li>
                <li class="wecandeo_thumbnail_li">
                    <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                        <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                    </label>
                </li>
                <li class="wecandeo_thumbnail_li">
                    <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                        <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                    </label>
                </li>
                <li class="wecandeo_thumbnail_li">
                    <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                        <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                    </label>
                </li>
                <li class="wecandeo_thumbnail_li">
                    <label class="wecandeo_thumbnail_label" for="pkg_up_thumbnail4">
                        <img class="wecandeo_thumbnail_image" src="<?=plugins_url( 'images/default-thumb.png', __FILE__ ); ?>">
                    </label>
                </li>
            </ul>
        <?php
    }

    ### 어드민에서 수정시 상단 알림 표시

    public function custom_post_activation_notice() {
        $run_once = get_option('wecandeo_video_activate_once');
        ###어드민 페이지에서 활성화 버튼을 클릭했을때
        if (basename($_SERVER['SCRIPT_FILENAME']) == 'plugins.php' && isset($_GET['activate']) && true == $_GET['activate'] && !$run_once) {
            add_action('admin_notices', array($this, 'video_notice'));
            return;
        }
    }

    public function video_notice() {
        _e('Plug-in is enabled. You can use this plug-in after API KEY setup.', 'wecandeo');
        update_option('wecandeo_video_activate_once', true);
    }

    public function add_action_links($links) {
        $links[] = '<a href="options-general.php?page=wecandeo">' . __('Settings') . '</a>';
        return $links;
    }

    //setting page
    public function setting_menu() {
		$wecandeo_user_id = '';
        $wecandeo_api_key = '';

        if (get_option('wecandeo_user_id') !== false) {
            $wecandeo_user_id = get_option('wecandeo_user_id');
        } else {
            add_option('wecandeo_user_id', '');
        }

        if (get_option('wecandeo_api_key') !== false) {
            $wecandeo_api_key = get_option('wecandeo_api_key');
        } else {
            add_option('wecandeo_api_key', '');
        }

        $mode = $_GET['mode'];
        if (isset($_POST['wecandeo_true']) && $_POST['wecandeo_true'] == 'y') {
            $wecandeo_user_id = $_POST['user_id'];
            update_option('wecandeo_user_id', $wecandeo_user_id);
            $wecandeo_api_key = $_POST['key'];
            update_option('wecandeo_api_key', $wecandeo_api_key);
        }
        ?>
        <h2><?php _e('WECANDEO - VIDEO PACK Setting', 'wecandeo'); //위캔디오 설정  ?></h2>
        <?php if ($wecandeo_api_key) {
            ?>
            <div  <?php if (!empty($_GET['mode']) && $_GET['mode'] == 'insert') : ?> style="display:none;"<?php endif; ?> >
                <p style="font-size: 1.1em;"><?php _e('Wecandeo User ID', 'wecandeo'); ?> : <?=$wecandeo_user_id; //계정아이디 ?></p>
                <p><?php _e('API key authentication is complete.', 'wecandeo'); //플러그인 사용 승인이 완료 되었습니다.   ?></p>
                <p><a href="options-general.php?page=wecandeo&mode=insert" ><?php _e('Reset API key', 'wecandeo'); //키를 재설정 합니다.   ?></a></p>
            </div>
            <?php
        }else {
            ?>

            <div  <?php if (!empty($_GET['mode']) && $_GET['mode'] == 'insert') : ?> style="display:none;"<?php endif; ?> >
                <p><?php _e('You can use this plug-in after WECANDEO - VIDEOPACK API KEY setup. Enter API key of your account already use or Join the WECANDEO.', 'wecandeo'); ?></p>
                <p><a href="http://www.wecandeo.com/" target="_blank"><?php _e('Create a API Key (Join the WECANDEO)', 'wecandeo'); ?></a></p>
                <p><a href="options-general.php?page=wecandeo&mode=insert" ><?php _e('API key already exists.', 'wecandeo'); ?></a></p>
            </div>
            <?php
        }
        ?>


        <!-- 세이브 완료 -->
        <?php if (isset($_POST['wecandeo_true']) && $_POST['wecandeo_true'] == 'y') { ?>
            <div><p><strong><?php _e('Settings saved.', 'wecandeo') ?></strong></p></div>
        <?php } ?>

        <!-- 키 입력 -->
        <?php if (!empty($_GET['mode']) && $_GET['mode'] == 'insert') { ?>
            <script>
                jQuery(document).ready(function() {

                    jQuery("#wecandeo_submit").click(function() {

                        var wecandeo_key = jQuery("#wecandeo_key").val();
                        jQuery.ajax({
                            url: "http://<?=WCANDEO_API_DOMAIN?>/info/v1/packages.jsonp",
                            data: {"key": wecandeo_key},
                            dataType: "jsonp", //외부 데이터를 받으려면 jsonp 형식이어야 한다
                            jsonp: "callback",
                            success: function(data) {
                                console.log(data);
                                if (data.packageList.errorInfo.errorCode != "None")
                                {
                                    jQuery("#wecandeo_confirm").html("*<?php _e('API Key is not valid.', 'wecandeo'); //유효하지 않은 Key 입니다.   ?>");
                                    return false;

                                } else {
                                    jQuery("#wecandeo_confirm").html("*<?php _e('API key authentication is complete.', 'wecandeo'); //플러그인 사용 승인이 완료 되었습니다.   ?>");
                                    jQuery("#wecandeo_true").val('y');
                                    jQuery('#wecandeo_confirm_form').submit();
                                }
                            }

                        })

                    });

                });
            </script>
            <form id="wecandeo_confirm_form" action="" method="post" >
                <table>
                    <tbody>
                        <tr>
                            <th style="vertical-align: top;text-align: left;padding: 20px 10px 20px 0;width: 200px;line-height: 1.3;font-weight: 600;"><label for="user_id"><?php _e('Wecandeo User ID', 'wecandeo'); ?></label></th>
                            <td>
                                <input id="wecandeo_user_id" name="user_id" type="text" value="<?php echo $wecandeo_user_id; ?>" >
                            </td>
                        </tr>
                        <tr>
                            <th style="vertical-align: top;text-align: left;padding: 20px 10px 20px 0;width: 200px;line-height: 1.3;font-weight: 600;"><label for="key"><?php _e('WECANDEO API Key', 'wecandeo'); ?></label></th>
                            <td>
                                <input id="wecandeo_key" name="key" type="text" value="<?php echo $wecandeo_api_key; ?>" style="width: 300px;" />
                                <input id="wecandeo_true" name="wecandeo_true" type="hidden" value="" />
                                <p id="wecandeo_confirm"class="need-key description"><?php
                                    if ($wecandeo_api_key) {
                                        _e('API key authentication is complete.', 'wecandeo'); //플러그인 사용 승인이 완료 되었습니다.
                                    } else {
                                        _e('Please enter a WECANDEO-VIDEOPACK API key.', 'wecandeo'); //유효한 키값을 입력하십시오.
                                    }
                                    ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="submit">
                <div id="wecandeo_submit"  class="thickbox button" ><span></span> <?php _e('Settings saved.', 'wecandeo'); //저장 완료   ?></div>
            </p>
            </form>

            <?php
        }
    }

}

//class end
?>
