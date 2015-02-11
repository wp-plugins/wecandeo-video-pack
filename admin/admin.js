jQuery(document).ready(function() {
    var wecandeo_swfu;
    var cid, duration; //지속시간

    //중복 모달 삭제
    jQuery(".wecandeo_form").each(function(index) {
        if (index > 0) {
            jQuery(this).remove();
        }
    });

    //select box insert start
    jQuery.ajax({
        url: "http://" + WC_API_DOMAIN + "/info/v1/folders.jsonp",
        data: {"key": wecandeo_admin_vars.api_key},
        dataType: "jsonp", //외부 데이터를 받으려면 jsonp 형식이어야 한다
        jsonp: "callback",
        success: function(data) {
            if (data.folderList.errorInfo.errorCode != "None")
            {
                return false;
            }

            jQuery(".wecondeo_folder_select").html("<option value=''>"+wecandeo_admin_lang_vars.LANG_SelectFolder+"</option>");
            for (var i in data.folderList.list) {
                jQuery(".wecondeo_folder_select").append("<option value='" + data.folderList.list[i].id + "' >" + data.folderList.list[i].folder_name + "</option>");
            }
        }
    });

    jQuery.ajax({
        url: "http://" + WC_API_DOMAIN + "/info/v1/packages.jsonp",
        data: {"key": wecandeo_admin_vars.api_key},
        dataType: "jsonp", //외부 데이터를 받으려면 jsonp 형식이어야 한다
        jsonp: "callback",
        success: function(data) {
            if (data.packageList.errorInfo.errorCode != "None")
            {
                return false;
            }

            jQuery(".wecondeo_package_select").html("<option value=''>"+wecandeo_admin_lang_vars.LANG_SelectPackage+"</option>");
            jQuery(".wecondeo_package_select_view").html("<option value=''>"+wecandeo_admin_lang_vars.LANG_SelectPackage+"</option>");
            for (var i in data.packageList.packageList) {
                jQuery(".wecondeo_package_select").append("<option value='" + data.packageList.packageList[i].package_id + "'>" + data.packageList.packageList[i].package_name + "</option>");
                jQuery(".wecondeo_package_select_view").append("<option value='" + data.packageList.packageList[i].package_id + "'>" + data.packageList.packageList[i].package_name + "</option>");
            }
        }
    });
    //select box insert end
    var wecandeo_swfupload_settings = {
        flash_url: wecandeo_admin_vars.includes_url+"js/swfupload/swfupload.swf",
        file_post_name: "videofile",
        post_params: {"cid": ""},
        file_size_limit: "2GB",
        file_types: "*.*",
        file_types_description: "Media Files",
        file_upload_limit: 100,
        file_queue_limit: 100,
        debug: false,
        // Button settings
       // button_image_url: wecandeo_admin_vars.path + "/images/select-file.png1",
        button_text: "파일 선택",
        button_text_top_padding: 5,
        button_width: "83",
        button_height: "24",
        button_placeholder_id: "wecondeo_span_button_place_holder",
        button_cursor: SWFUpload.CURSOR.HAND,
        button_window_mode: SWFUpload.WINDOW_MODE.TRANSPARENT,
        file_queued_handler: wecandeo_admin.wecandeo_file_queued,
        file_queue_error_handler: wecandeo_admin.wecandeo_file_queue_error,
        upload_start_handler: uploadStart,
        upload_progress_handler: wecandeo_admin.wecandeo_upload_progress,
        upload_error_handler: uploadError,
        upload_success_handler: wecandeo_admin.wecandeo_upload_success
    };

    wecandeo_swfu = new SWFUpload(wecandeo_swfupload_settings);

    jQuery('.wecondeo_start_upload').on('click', function() {
       
                        
        if (!wecandeo_admin.wecandeo_upload_folder_package_check() || jQuery("#wecandeo_file_title").html() == '' || !wecandeo_admin_vars.file_selected) {
            alert(wecandeo_admin_lang_vars.LANG_UploadFolderPackageSelectTheFile);
            return false;
        }

        if (wecandeo_admin_vars.file_upload) {
            alert(wecandeo_admin_lang_vars.LANG_ItisPossibleToUploadFilesOneByOne);
            return false;
        }

        jQuery(".wecandeo_state-text").html(wecandeo_admin_lang_vars.LANG_Preparing+"...");//준비중

        jQuery.ajax({
            url: "http://" + WC_API_DOMAIN + "/web/v3/uploadToken.jsonp",
            data: {"key": wecandeo_admin_vars.api_key},
            dataType: "jsonp", //외부 데이터를 받으려면 jsonp 형식이어야 한다
            jsonp: "callback",
            success: function(data) {
                if (data.uploadInfo.errorInfo.errorCode != "None")
                {
                    alert(data.uploadInfo.errorInfo.errorMessage);
                    jQuery(".wecandeo_state-text").html(wecandeo_admin_lang_vars.LANG_Waiting+"...");//대기중
                    return false;
                }

                wecandeo_swfu.setUploadURL(data.uploadInfo.uploadUrl + "?token=" + data.uploadInfo.token);
                wecandeo_swfu.addPostParam("token", data.uploadInfo.token);
                wecandeo_swfu.addPostParam("folder", wecandeo_admin_vars.folder_id);

                jQuery(".wecandeo_state-text").html(wecandeo_admin_lang_vars.LANG_Uploading+"...");//업로딩
                wecandeo_admin.wecandeo_blind('block');
                wecandeo_swfu.startUpload();
            }
        });
    });

    jQuery('.wecondeo_reset').on('click', function() {
        wecandeo_swfu.cancelUpload();//업로드 중이었다면 켄슬
        wecandeo_admin.wecandeo_upoad_reset();
    });

    jQuery('.wecandeo_folder_top_btn').on('click', function() {
        wecandeo_admin_vars.upload_tab = 'f';
        jQuery(".wecondeo_package").css('display', 'none');
        jQuery(".wecondeo_folder").css('display', 'block');
        jQuery(".wecandeo_package_top_btn").removeClass("nav-tab-active");
        jQuery(this).addClass("nav-tab-active");
    });


    jQuery('.wecandeo_package_top_btn').on('click', function() {
        wecandeo_admin_vars.upload_tab = 'p';
        jQuery(".wecondeo_folder").css('display', 'none');
        jQuery(".wecondeo_package").css('display', 'block');
        jQuery(".wecandeo_folder_top_btn").removeClass("nav-tab-active");
        jQuery(this).addClass("nav-tab-active");
    });

    jQuery('#wecandeo_upload_completed').on('click', function() {
        if (wecandeo_admin_vars.upload_tab == 'p') {
            wecandeo_admin.wecandeo_selection_completed();
            return false;
        }else{
            if (!wecandeo_admin_vars.file_upload) {
            alert(wecandeo_admin_lang_vars.LANG_VideoIsNotUploaded);
            return false;
            }
        }

        var selectedSeq = jQuery(".wecandeo_thumbnail_select input:radio:checked").val();
        if (typeof(selectedSeq) == "undefined" || selectedSeq.length == 0)
        {
            alert(wecandeo_admin_lang_vars.LANG_PleaseSelectThumbnail);
            return false;
        }

        wecandeo_admin.wecandeo_set_thumbnail(selectedSeq);
        wecandeo_admin.wecandeo_set_package('u');
        //제목 변경
        var wecondeo_modal_new_title = jQuery('.wecondeo_modal_new_title').val();
        wecandeo_admin.wecandeo_set_title(wecondeo_modal_new_title);
        //빠른 처리로 인해 조금 기다리자...
        setTimeout(function() {
            wecandeo_admin.wecandeo_get_publishInfo('u');
        }, 1000);

    });

    //닫기버튼
    jQuery('#wecandeo_upload_top_close').on('click', function() {
        jQuery("#wecandeo_modal_div").css('display', 'none');
    });
    //닫기버튼
    jQuery('#wecandeo_upload_close').on('click', function() {
        jQuery("#wecandeo_modal_div").css('display', 'none');
    });

    //select box change start
    jQuery(".wecondeo_folder_select").on('change', function() {
        //wecandeo_admin_vars.folder_id = jQuery(".wecondeo_folder_select option:selected").val();
        wecandeo_admin_vars.folder_id = jQuery(this).val();
    });
    jQuery(".wecondeo_package_select").on('change', function() {
        //wecandeo_admin_vars.package_id = jQuery(".wecondeo_package_select option:selected").val();
        wecandeo_admin_vars.package_id = jQuery(this).val();
    });

    //배포 패키지 셀렉트 박스에서 선택
    jQuery(".wecondeo_package_select_view").on('change', function() {
        //wecandeo_admin_vars.select_package_id = jQuery(".wecondeo_package_select_view option:selected").val();
        wecandeo_admin_vars.select_package_id = jQuery(this).val();
        jQuery.ajax({
            url: "http://" + WC_API_DOMAIN + "/info/v1/videos.jsonp",
            data: {"key": wecandeo_admin_vars.api_key, "pkg": wecandeo_admin_vars.select_package_id, "pagesize" : '1000'},
            dataType: "jsonp", //외부 데이터를 받으려면 jsonp 형식이어야 한다
            jsonp: "callback",
            success: function(data) {
                if (data.videoInfoList.errorInfo.errorCode != "None")
                {
                    alert(""+wecandeo_admin_lang_vars.LANG_FailedToImportPackage+"!\n[" + data.videoPublishInfo.errorInfo.errorMessage + "]");
                    return false;
                }

                 jQuery(".wecandeo_thumbnail_select_package ul").html('');
                for (var i in data.videoInfoList.videoInfoList) {

                    jQuery(".wecandeo_thumbnail_select_package ul").append("<li class='wecandeo_thumbnail_li'><label class='wecandeo_thumbnail_label' for='pkg_thumbnail" + (parseInt(i) + 1) + "'>\
                                                                            <img class='wecandeo_thumbnail_image' src='" + data.videoInfoList.videoInfoList[i].thumbnail_url + "' />\
                                                                            <input class='wecandeo_thumbnail_radio' type='radio' id='pkg_thumbnail" + (parseInt(i) + 1) + "' name='thumbnailUrl' imgurl='" + data.videoInfoList.videoInfoList[i].thumbnail_url + "' value='" + data.videoInfoList.videoInfoList[i].access_key + "' />\
                                                                            <div class='library-name'>"+ data.videoInfoList.videoInfoList[i].title +"</div>\
                                                                            </label></li>");
                }
            }
        });

    });
    //select box change end
    jQuery('.wecandeo_thumbnail_radio').on('click', function() {
        var imgurl = jQuery(this).attr('imgurl');
    });

});

var wecandeo_admin = {
    wecandeo_file_queued: function(obj) {//파일선택시
        jQuery("#wecandeo_file_title").html(obj.name);
        wecandeo_admin_vars.file_selected = 'y';
        jQuery(".wecandeo_state-text").html("Pending...");
    },
    wecandeo_file_queue_error: function(file, errorCode, message) {
        try {
            if (errorCode === wecandeo_swfu.QUEUE_ERROR.QUEUE_LIMIT_EXCEEDED) {
                alert("You have attempted to queue too many files.\n" + (message === 0 ? "You have reached the upload limit." : "You may select " + (message > 1 ? "up to " + message + " files." : "one file.")));
                return;
            }

            switch (errorCode) {
                case wecandeo_swfu.QUEUE_ERROR.FILE_EXCEEDS_SIZE_LIMIT:
                    alert(wecandeo_admin_lang_vars.LANG_FilesAreAvailableOnlyUploadFilesLessThan2GB);
                    break;
                case wecandeo_swfu.QUEUE_ERROR.ZERO_BYTE_FILE:
                    alert(wecandeo_admin_lang_vars.LANG_CannotUploadZeroByteFiles);
                    break;
                case wecandeo_swfu.QUEUE_ERROR.INVALID_FILETYPE:
                    alert(wecandeo_admin_lang_vars.LANG_InvalidFileType);
                    break;
                default:
                    if (file !== null) {
                        alert(wecandeo_admin_lang_vars.LANG_InvalidFileType);
                    }
                    break;
            }
        } catch (ex) {
            alert(ex);
        }
    },
    wecandeo_upload_progress: function(obj, complete, total) {
        jQuery(".wecandeo_state-text").html(wecandeo_admin_lang_vars.LANG_Uploading+' '+ parseInt((complete / total) * 100) + "%");
        jQuery(".wecandeo_inner_bar").css("width", parseInt((complete / total) * 100) + "%");
    },
    wecandeo_upload_error: function(file, errorCode, message) {
        try {
           alert(message);
        } catch (ex) {
            alert(ex);
        }
    },
    wecandeo_upload_success: function(uf, rdata) {
        var jsonData = eval("(" + rdata + ")");
        if (jsonData.uploadInfo.errorInfo.errorCode != "None")
        {
            alert(jsonData.uploadInfo.errorInfo.errorMessage);
            return false;
        }

        wecandeo_admin_vars.access_key = jsonData.uploadInfo.uploadDetail.access_key;
        cid = jsonData.uploadInfo.uploadDetail.cid;
        duration = jsonData.uploadInfo.uploadDetail.duration;

        jQuery(".wecandeo_state-text").html(wecandeo_admin_lang_vars.LANG_Complete);

        jQuery(".wecandeo_thumbnail_select ul").empty();
        for (var i in jsonData.uploadInfo.thumbnails) {
            var checkedSetting = (i == 2) ? " checked" : "";
            jQuery(".wecandeo_thumbnail_select ul").append("<li class='wecandeo_thumbnail_li'><label class='wecandeo_thumbnail_label' for='pkg_up_thumbnail" + (parseInt(i) + 1) + "'>\
                                                                            <img class='wecandeo_thumbnail_image' src='" + jsonData.uploadInfo.thumbnails[i].url + "' />\
                                                                            <input class='wecandeo_thumbnail_radio' type='radio' id='pkg_up_thumbnail" + (parseInt(i) + 1) + "' name='thumbnailUrl' value='" + (parseInt(i) + 1) + "' " + checkedSetting + "' />\
                                                                            </label></li>");
        }
        wecandeo_admin.wecandeo_blind('none');
        wecandeo_admin_vars.file_upload = 1;
    },
    wecandeo_set_thumbnail: function(selectedSeq) {

        jQuery.ajax({
            url: "http://" + WC_API_DOMAIN + "/info/v1/video/set/thumbnail.jsonp",
            data: {"key": wecandeo_admin_vars.api_key, "access_key": wecandeo_admin_vars.access_key, "seq": selectedSeq},
            dataType: "jsonp", //외부 데이터를 받으려면 jsonp 형식이어야 한다
            jsonp: "callback",
            success: function(data) {
                if (data.setThumbnail.status != "Success")
                {
                    alert(wecandeo_admin_lang_vars.LANG_Complete+"!\n[" + data.setThumbnail.errorInfo.errorMessage + "]");
                    return false;
                }
            }
        });
    },
    wecandeo_set_package: function() {
        jQuery.ajax({
            url: "http://" + WC_API_DOMAIN + "/info/v1/video/set/package.jsonp",
            data: {"key": wecandeo_admin_vars.api_key, "access_key": wecandeo_admin_vars.access_key, "pkg": wecandeo_admin_vars.package_id},
            dataType: "jsonp", //외부 데이터를 받으려면 jsonp 형식이어야 한다
            jsonp: "callback",
            success: function(data) {
                if (data.setPackage.errorInfo.errorCode != "None")
                {
                    alert(wecandeo_admin_lang_vars.LANG_Complete+"!\n[" + data.videoPublishInfo.errorInfo.errorMessage + "]");
                    return false;
                }
            }
        });
    },
    //mode : u 업로드, s 기존
    wecandeo_get_publishInfo: function(mode) {
        var iframecode, flashcode, iframecode_url;
        var access_key, pkg;

        if (mode == 'u') {
            access_key = wecandeo_admin_vars.access_key;
            pkg = wecandeo_admin_vars.package_id;
        } else {
            access_key = wecandeo_admin_vars.select_access_key
            pkg = wecandeo_admin_vars.select_package_id;
        }

        jQuery.ajax({
            url: "http://" + WC_API_DOMAIN + "/info/v1/video/publishInfo.jsonp",
            data: {"key": wecandeo_admin_vars.api_key, "access_key": access_key, "pkg": pkg},
            dataType: "jsonp", //외부 데이터를 받으려면 jsonp 형식이어야 한다
            jsonp: "callback",
            success: function(data) {
                if (data.videoPublishInfo.errorInfo.errorCode != "None")
                {
                    alert(wecandeo_admin_lang_vars.LANG_VideoLoadingFailure+"영상 로딩 실패!\n[오류내용:" + data.videoPublishInfo.errorInfo.errorMessage + "]");
                    return false;
                }
                jQuery("#wecandeo_modal_div").css('display', 'none');

				iframecode_url = "http://" + WC_PLAY_DOMAIN + "/video/v/?key=" + data.videoPublishInfo.videoKey;

				//비주얼, 텍스트 구분
                if (typeof tinyMCE != 'undefined' && (ed = tinyMCE.activeEditor) && !ed.isHidden()) {
                    ed.focus();
                    if (tinymce.isIE) {
                        ed.selection.moveToBookmark(tinymce.EditorManager.activeEditor.windowManager.bookmark);
                    }

                    tinyMCE.activeEditor.execCommand('mceInsertContent', false, iframecode_url);
					console.log("요기1 -> " + iframecode_url);
                } else {
                    edInsertContent(edCanvas, iframecode_url);
					console.log("요기2~");
                }
            }
        });

    },
    //배포 패키지중 동영상 선택 완료 후 글에 삽입
    wecandeo_selection_completed: function() {
        wecandeo_admin_vars.select_access_key = jQuery(".wecandeo_thumbnail_select_package input:radio:checked").val();
        if (typeof(wecandeo_admin_vars.select_access_key) == "undefined" || wecandeo_admin_vars.select_access_key.length == 0 || wecandeo_admin_vars.select_access_key.length == '')
        {
            alert(wecandeo_admin_lang_vars.LANG_NoVideoSelected);
            return false;
        }
        wecandeo_admin.wecandeo_get_publishInfo('s');
    },
    wecandeo_upload_folder_package_check: function() {
        if (wecandeo_admin_vars.folder_id != '' && wecandeo_admin_vars.package_id != '') {
            return true;
        } else {
            return false;
        }
    },
    wecandeo_upoad_reset: function() {
        wecandeo_admin.wecandeo_blind('none');
        jQuery(".wecandeo_inner_bar").css("width", '0');
        jQuery("#wecandeo_file_title").html(wecandeo_admin_lang_vars.LANG_SelectedFileNameIsDisplayed);
        jQuery(".wecandeo_state-text").html(wecandeo_admin_lang_vars.LANG_Waiting+"...");
        jQuery(".wecandeo_thumbnail_select ul").html(jQuery("#wecandeo_thumbnail_ul").html());
        jQuery(".wecandeo_thumbnail_select_package ul").html('');
        jQuery("[class=wecondeo_folder_select] > option[value='']").attr("selected", "true");   //select box
        jQuery("[class=wecondeo_package_select] > option[value='']").attr("selected", "true");   //select box
        jQuery("[class=wecondeo_package_select_view] > option[value='']").attr("selected", "true");   //select box

        wecandeo_admin_vars.file_upload = '';
        wecandeo_admin_vars.package_id = '';
        wecandeo_admin_vars.folder_id = '';
        wecandeo_admin_vars.access_key = '';
        wecandeo_admin_vars.select_package_id = '';
        wecandeo_admin_vars.select_access_key = '';
        wecandeo_admin_vars.file_selected = '';
    },
    wecandeo_blind: function(type) {
        jQuery(".wecandeo_modal_form_blind").css('display', type);
    },
    wecandeo_set_title: function(title) {
        if (title) {
            jQuery.ajax({
                url: "http://" + WC_API_DOMAIN + "/info/v1/video/set/detail.jsonp",
                data: {"key": wecandeo_admin_vars.api_key, "access_key": wecandeo_admin_vars.access_key, "title": title},
                dataType: "jsonp", //외부 데이터를 받으려면 jsonp 형식이어야 한다
                jsonp: "callback",
                success: function(data) {
                    if (data.setDetail.errorInfo.errorCode != "None")
                    {
                        alert(wecandeo_admin_lang_vars.LANG_FailedToSetTheTitle+"!\n[:" + data.setDetail.errorInfo.errorMessage + "]");
                        return false;
                    }
                }
            });
        }
    }

}