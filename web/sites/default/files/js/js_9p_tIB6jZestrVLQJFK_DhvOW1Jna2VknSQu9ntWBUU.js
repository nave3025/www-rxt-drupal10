/* @license GNU-GPL-2.0-or-later https://www.drupal.org/licensing/faq */
(function($,Drupal,drupalSettings,window){"use strict";window.onload=function(){let bootstrapVersion=false;const bs3bs4=window.jQuery?.fn?.popover?.Constructor?.VERSION;const bs5=window.bootstrap?.Popover?.VERSION;if(bs3bs4)bootstrapVersion=bs3bs4.charAt(0);else{if(bs5)bootstrapVersion=bs5.charAt(0);}if(!bootstrapVersion){const messages=new Drupal.Message();const message=Drupal.t("The DXPR Builder depends on Bootstrap framework to work. "+"Please enable Bootstrap in "+"the <a href='@dxpr_builder_settings'>DXPR Builder settings form</a>.",{"@dxpr_builder_settings":Drupal.url("admin/dxpr_studio/dxpr_builder/settings")});messages.add(message,{type:"error"});}};window.dxprBuilder={};window.dxprBuilder.dxpr_editable=["h1","h2","h3","h4","h5","h6","img:not(.not-editable)","a:not(.not-editable)","i:not(.not-editable)"];window.dxprBuilder.dxpr_styleable=[];window.dxprBuilder.dxpr_textareas=[];window.dxprBuilder.dxpr_formats=[];function hideImageStyleControls(input){input.siblings("label:first, .chosen-container:first").hide();}function getUrlsFromInput(input,delimiter){if(delimiter)return input.val().split(delimiter).filter((el)=>Boolean(el.length));return [input.val().trim()];}function showImageStyleControls(input){input.siblings("label:first, .chosen-container:first").show();}function createFileUploadButton(input,type){switch(type){case "image":input.parent().parent().prepend($("<button/>",{class:"ac-select-image btn btn-default"}).text(Drupal.t("Select Image")).click(function(e){e.preventDefault();$(this).siblings(".ac-select-image__content-container").find(".image_upload:first").click();}));break;case "video":input.parent().prepend($("<button/>",{class:"ac-select-video btn btn-default"}).text(Drupal.t("Select Video")).click(function(e){e.preventDefault();$(this).siblings(".video_upload:first").click();}));break;default:}}function createEntityBrowserButton(input){const ACSelectImage=input[0].closest(".ac-select-image");const ACSelectImageButton=document.createElement("button");ACSelectImageButton.classList.add(...["ac-select-image","btn","btn-default"]);ACSelectImageButton.innerText=Drupal.t("Select Image");ACSelectImage.insertAdjacentElement("afterbegin",ACSelectImageButton);ACSelectImageButton.addEventListener("click",(e)=>{e.preventDefault();const {mediaBrowser}=drupalSettings.dxprBuilder;let eb="dxprBuilderSingle";if(input.hasClass("dxpr-builder-multi-image-input"))eb="dxprBuilderMulti";input[0].setAttribute("data-uuid",eb);let mediaBrowserHTML=document.getElementById("az-media-modal");if(mediaBrowserHTML)mediaBrowserHTML.remove();mediaBrowserHTML=`
      <div id="az-media-modal" class="modal dxpr-builder-ui" style="display:none">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
              <div class="modal-header">
                <span class="close" ${getModalDismissValue()} aria-hidden="true">&times;</span>
                <h4 class="modal-title">${Drupal.t("Media Browser")}</h4>
              </div>
              <div class="modal-body">
              <iframe 
                data-uuid="${eb}"
                src="${drupalSettings.path.baseUrl}entity-browser/modal/${mediaBrowser}?uuid=${eb}"
                frameborder="0">
              </iframe>
              </div>
            </div>
        </div>
      </div>
      `;$(mediaBrowserHTML).modal("show");});}function getFileNameFromUrl(url){const parts=url.split("/");return parts[parts.length-1];}function getImageStyleUrl(url,imageStyle){const filesUrl=drupalSettings.dxprBuilder.publicFilesFolder;if(url.indexOf(filesUrl)!==-1&&url.indexOf("svg")===-1){const isPrivate=url.indexOf("/system/files/")!==-1;if(isPrivate){if(url.indexOf("/private/")===-1)return url.replace(filesUrl,`${filesUrl}styles/${imageStyle}/private/`);if(imageStyle==="original")return url.replace(/ styles\/[^/]+\/private\/ /,"");return url.replace(/ \/styles\/[^/]+ /,`/styles/${imageStyle}`);}if(url.indexOf("/public/")===-1)return url.replace(filesUrl,`${filesUrl}styles/${imageStyle}/public/`);if(imageStyle==="original")return url.replace(/ styles\/[^/]+\/public\/ /,"");return url.replace(/ \/styles\/[^/]+ /,`/styles/${imageStyle}`);}return url;}function sortFilenames(imageList,delimiter){const imageInput=imageList.siblings(".form-control:first");const urls=getUrlsFromInput(imageInput,delimiter);const fileNames=[];imageList.children("li").each(function(){const filename=$(this).children(":first").attr("data-filename");if(filename&&filename.length)fileNames.push(filename);});const sorted=[];$.each(fileNames,(index)=>{$.each(urls,(index2)=>{if(urls[index2].endsWith(fileNames[index])){sorted.push(urls[index2]);return false;}});});imageInput.val(sorted.join(delimiter));}function thumbnailCloseButtonClickHandler(e){e.preventDefault();const thumbnailContainer=$(this).parent().parent();const imageList=thumbnailContainer.parent();const selectElement=thumbnailContainer.parent().siblings("select:first");selectElement.find("option[selected='selected']").removeAttr("selected");selectElement.find("option[value='original']").attr("selected","selected").trigger("chosen:updated");thumbnailContainer.remove();if(!imageList.children("li:first").length)hideImageStyleControls(imageList.siblings(".form-control:first"));sortFilenames(imageList,",");liveEditingManager.update();}function insertImageThumbnail(fileUrl,input,delimiter,fileLocation){const imageContainer=$("<div/>",{class:"image-preview","data-filename":getFileNameFromUrl(fileUrl)});const image=fileLocation?$("<img/>",{src:fileLocation}):$("<img/>",{src:getImageStyleUrl(fileUrl,"thumbnail")});const closeButton=$("<a/>",{class:"glyphicon glyphicon-remove",href:"#"}).click(thumbnailCloseButtonClickHandler);imageContainer.append(image).append(closeButton);let imageList=input.siblings(".preview:first");if(!imageList.length){imageList=$("<ul/>",{class:"preview ui-sortable"}).insertBefore(input.siblings(".chosen-container-single"));window.Sortable.create(imageList[0],{forceFallback:true,onEnd:()=>{sortFilenames($(imageList),delimiter);liveEditingManager.update();}});}if(!delimiter)imageList.empty();$("<li/>",{class:"added"}).append(imageContainer).appendTo(imageList);showImageStyleControls(input);}function dxpr_builder_get_image_style_url(imageStyle,fileId,callback){$.ajax({type:"get",url:drupalSettings.dxprBuilder.dxprCsrfUrl,dataType:"json",cache:false,context:this}).done((data)=>{$.ajax({type:"POST",url:data,data:{action:"dxpr_builder_get_image_style_url",imageStyle,fileId},cache:false}).done((res)=>{if(typeof callback==="function")callback(res);liveEditingManager.update();}).fail(()=>{callback("");});});}function dxpr_builder_insert_image(updateThumb,imageStyle,fileUrl,fileId,input,delimiter,newImages,fileLocation){if(!fileLocation)fileLocation=fileUrl;if(updateThumb)insertImageThumbnail(fileUrl,input,delimiter,fileLocation);else if(delimiter){newImages.push(fileLocation);input.val(newImages.join(delimiter));}else input.val(fileLocation);}function dxpr_builder_get_images(updateThumb,imageStyle,fileUrl,input,delimiter,newImages){const isProtectedFiles=fileUrl.indexOf("/system/files/")!==-1;const isPublicFiles=fileUrl.indexOf("/sites/default/files/")!==-1;if(isPublicFiles||isProtectedFiles){let fileId="";const idPosition=fileUrl.indexOf("fid=");if(idPosition>-1)fileId=fileUrl.substr(idPosition+4);if(fileId.length>0)dxpr_builder_get_image_style_url(imageStyle,fileId,(fileLocation)=>{dxpr_builder_insert_image(updateThumb,imageStyle,fileUrl,fileId,input,delimiter,newImages,fileLocation);});else{const fileLocation=getImageStyleUrl(fileUrl,imageStyle);dxpr_builder_insert_image(updateThumb,imageStyle,fileUrl,fileId,input,delimiter,newImages,fileLocation);}}else dxpr_builder_insert_image(updateThumb,imageStyle,fileUrl,null,input,delimiter,newImages,null);}function createFileUploadElement(input,delimiter,type){switch(type){case "image":$("<input/>",{type:"file",class:"image_upload",accept:".gif,.jpg,.jpeg,.png,.svg","data-url":drupalSettings.dxprBuilder.fileUploadUrl}).insertBefore(input).fileupload({dataType:"json",acceptFileTypes:/(\.|\/)(gif|jpe?g|png|svg)$/i,formData:{type:"image"},done(e,data){const imageStyle=input.siblings("select:first").val();$.each(data.result.files,(index)=>{let url;if(imageStyle==="original")url=`${data.result.files[index].fileUrl}?fid=${data.result.files[index].fileId}`;else url=getImageStyleUrl(data.result.files[index].fileUrl,imageStyle);if(delimiter){const currentImages=getUrlsFromInput(input,delimiter);currentImages.push(url);input.val(currentImages.join(delimiter));}else input.val(url);dxpr_builder_get_images(true,"thumbnail",url,input,delimiter);});}});break;case "video":$("<input/>",{type:"file",class:"video_upload",accept:".webm,.ogv,.ogg,.mp4","data-url":drupalSettings.dxprBuilder.fileUploadUrl}).insertBefore(input).fileupload({dataType:"json",acceptFileTypes:/(\.|\/)(webm|ogv|ogg|mp4)$/i,formData:{type:"video"},done(e,data){input.next(".alert-danger").remove();$.each(data.result.files,(index)=>{const url=`${data.result.files[index].fileUrl}?fid=${data.result.files[index].fileId}`;if(delimiter){const currentVideos=getUrlsFromInput(input,delimiter);currentVideos.push(url);input.val(currentVideos.join(delimiter));}else input.val(url);});if(data.result.error)input.after(`<div class="alert alert-danger" role="alert">${data.result.error}</div>`);liveEditingManager.update();},fail(e,data){if(data.jqXHR.status===413)dxpr_builder_alert(`The uploaded video is too large. Max size is ${FILE_UPLOAD_MAX_SIZE}MB`,{type:"danger"});else dxpr_builder_alert(data.jqXHR.statusText,{type:"danger"});}});break;default:}}function imageStyleChangeHandler(selectElement,delimiter){const imageStyle=selectElement.val();const imageInput=selectElement.siblings(".form-control:first");if(delimiter){const currentImages=getUrlsFromInput(imageInput,delimiter);const newImages=[];$.each(currentImages,(index)=>{const fileUrl=currentImages[index];dxpr_builder_get_images(false,imageStyle,fileUrl,imageInput,delimiter,newImages);});}else{const fileUrl=imageInput.val();dxpr_builder_get_images(false,imageStyle,fileUrl,imageInput);}}function createImageStyleInput(input,delimiter){let label;const imageStyleSelect=$('<select class="dxpr-builder-image-styles"/>').change(function(){imageStyleChangeHandler($(this),delimiter);});$.each(drupalSettings.dxprBuilder.imageStyles,(key)=>{imageStyleSelect.append($("<option/>",{value:key}).text(drupalSettings.dxprBuilder.imageStyles[key]));});const matches=input.val().match(/styles\/([^/]+)\/(public|private)/);if(matches&&matches[1])imageStyleSelect.find(`option[value='${matches[1]}']`).attr("selected","selected");input.before(imageStyleSelect).prepend(label);imageStyleSelect.chosen({search_contains:true,allow_single_deselect:true});hideImageStyleControls(input);}function createThumbailFromDefault(input,delimiter){let currentImages;if(input.val().length){currentImages=getUrlsFromInput(input,delimiter);$.each(currentImages,(index)=>{const fileUrl=currentImages[index];dxpr_builder_get_images(true,"thumbnail",fileUrl,input,delimiter);});showImageStyleControls(input);}}window.dxprBuilder.backend_images_select=function(input,delimiter){const $input=$(input);$input.css("display","block").wrap($("<div/>",{class:"ac-select-image"})).wrap($("<div/>",{class:"ac-select-image__content-container"}));if(drupalSettings.dxprBuilder.mediaBrowser.length>0)createEntityBrowserButton($input);else{createFileUploadElement($input,delimiter,"image");createFileUploadButton($input,"image");}createImageStyleInput($input,delimiter);createThumbailFromDefault($input,delimiter);$input.change({input:$input,delimiter},(event)=>{$input.siblings(".preview:first").empty();createThumbailFromDefault(input,delimiter);});};window.dxprBuilder.backend_videos_select=function(input,delimiter){const $input=$(input);$input.css("display","block").wrap($("<div/>",{class:"ac-select-video"}));createFileUploadElement($input,delimiter,"video");createFileUploadButton($input,"video");};})(jQuery,Drupal,drupalSettings,window);;