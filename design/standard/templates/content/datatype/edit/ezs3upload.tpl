{default attribute_base='ContentObjectAttribute'
         html_class='full'}

{def $aws_access_key_id=ezini( 'S3Settings', 'Key', 's3.ini' )
     $aws_access_secret_key_id=ezini( 'S3Settings', 'SecretKey', 's3.ini' )
     $aws_s3_bucket=ezini( 'S3Settings', 'Bucket', 's3.ini' )
     $upload_file_size_limit=ezini( 'S3Settings', 'FileSizeLimit', 's3.ini' )
     $is_mac_user=is_mac_user()
     $policyDoc64=aws_s3_policydoc64()
     $sigPolicyDoc=aws_s3_sigpolicydoc( $policyDoc64 )}

{ezcss_load( 'ezs3upload.css' )}

{* ezscript_load( array( 'ezjsc::jquery', 'ezjsc::jqueryio' ) )}
<script type="text/javascript" src="http://www.google.com/jsapi"></script>
<script type="text/javascript">
    google.load("jquery", "1.3");
</script>
{ezscript_load( array( 'swfupload.js' ) )}
{ezscript_load( array( 'fileprogress.js' ) )}
{ezscript_load( array( 'jquery.swfupload.js' ) ) *}

{ezscript_require( 'swfupload.js' )}
{ezscript_require( 'fileprogress.js' )}
{ezscript_require( 'jquery.swfupload.js' )}

<script type="text/javascript">
{literal}
var trackFiles = [];
var trackFilesCount = 0;
var trackSentURL = false;
var forceDone = false;
var forceFile = null;
var master = null;
var MacMinSizeUpload = 150000; // 150k, this is not cool :(
var MacDelay = 10000; // 10 secs.
var isMacUser = false;
var isMacUser = {/literal}{if $is_mac_user|eq( true() )}'true'{else}'false'{/if}{literal};
var successURL = null;

$(function(){

var txtezpFieldRootName = document.getElementById("{/literal}ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}0_{$attribute.contentclass_attribute_identifier}{literal}");
// var txtezpFieldFileName = document.getElementById("{/literal}ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}{literal}");

    $('#swfupload-control').swfupload({
{/literal}
        upload_url: "http://{$aws_s3_bucket}.s3.amazonaws.com/",
        post_params: {literal}{{/literal}"AWSAccessKeyId":"{$aws_access_key_id}", "key":{literal}txtezpFieldRootName.value + "${filename}"{/literal}, "acl":"public-read", "policy":"{$policyDoc64}", "signature":"{$sigPolicyDoc}","success_action_status":"201", "content-type":"image/"{literal}}{/literal},

        http_success : [201],
        assume_success_timeout : {if $is_mac_user|eq( true() )}5{else}0{/if},

        // File Upload Settings
        file_post_name: 'file',
        file_size_limit : "{$upload_file_size_limit}",    // 20 MB
        file_types : "*.*",            // or you could use something like: "*.doc;*.wpd;*.pdf",
        file_types_description : "All Files",
        file_upload_limit : "0",
        file_queue_limit : "1",
{literal}
        button_image_url : "/extension/ezs3upload/design/standard/images/XPButtonSelectText_61x22.png",
        button_placeholder_id : 'mybutton',
        button_placeholder : $('#mybutton'),
        button_width: 61,
        button_height: 22,

        button_window_mode: SWFUpload.WINDOW_MODE.TRANSPARENT,
        button_cursor: SWFUpload.CURSOR.HAND,
        moving_average_history_size: 10,

        // Flash Settings
        flash_url : "/extension/ezs3upload/design/standard/flash/swfupload.swf",
        custom_settings : {
          progressTarget : "fsUploadProgress",
         /* cancelButtonId : "btnCancel"*/
          upload_successful : false
        },
        // Debug Settings
        debug: false
    })
    .bind('fileDialogStart', function(event, file){
        var swfu = $.swfupload.getInstance('#swfupload-control');
        var txtFileName = document.getElementById("txtFileName");
        txtFileName.value = "";
        swfu.cancelUpload();
    })

    .bind('uploadError', function(event, file, errorCode, message){
        var swfu = $.swfupload.getInstance('#swfupload-control');
        try {

            if (errorCode === SWFUpload.UPLOAD_ERROR.FILE_CANCELLED) {
                // Don't show cancelled error boxes
                return;
            }
            var txtFileName = document.getElementById("txtFileName");
            txtFileName.value = "";
            // validateForm();

            //file.id = "singlefile";
            var progress = new FileProgress(file, swfu.customSettings.progressTarget);
            progress.setError();
            progress.toggleCancel(false);

            switch (errorCode) {
            case SWFUpload.UPLOAD_ERROR.HTTP_ERROR:
                progress.setStatus("Upload Error: " + message);
                swfu.debug("Error Code: HTTP Error, File name: " + file.name + ", Message: " + message);
                break;
            case SWFUpload.UPLOAD_ERROR.UPLOAD_FAILED:
                progress.setStatus("Upload Failed.");
                swfu.debug("Error Code: Upload Failed, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
                break;
            case SWFUpload.UPLOAD_ERROR.IO_ERROR:
                progress.setStatus("Server (IO) Error");
                swfu.debug("Error Code: IO Error, File name: " + file.name + ", Message: " + message);
                break;
            case SWFUpload.UPLOAD_ERROR.SECURITY_ERROR:
                progress.setStatus("Security Error");
                swfu.debug("Error Code: Security Error, File name: " + file.name + ", Message: " + message);
                break;
            case SWFUpload.UPLOAD_ERROR.UPLOAD_LIMIT_EXCEEDED:
                progress.setStatus("Upload limit exceeded.");
                swfu.debug("Error Code: Upload Limit Exceeded, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
                break;
            case SWFUpload.UPLOAD_ERROR.FILE_VALIDATION_FAILED:
                progress.setStatus("Failed Validation.  Upload skipped.");
                swfu.debug("Error Code: File Validation Failed, File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
                break;
            case SWFUpload.UPLOAD_ERROR.FILE_CANCELLED:
                // If there aren't any files left (they were all cancelled) disable the cancel button
                if (this.getStats().files_queued === 0) {
                    document.getElementById(swfu.customSettings.cancelButtonId).disabled = true;
                }
                progress.setStatus("Cancelled");
                progress.setCancelled();
                break;
            case SWFUpload.UPLOAD_ERROR.UPLOAD_STOPPED:
                progress.setStatus("Stopped");
                break;
            default:
                progress.setStatus("Unhandled Error: " + errorCode);
                swfu.debug("Error Code: " + errorCode + ", File name: " + file.name + ", File size: " + file.size + ", Message: " + message);
                break;
            }
        } catch (ex) {
            swfu.debug(ex);
        }
    })

    .bind('fileQueued', function(event, file){
        try {
            var txtFileName = document.getElementById("txtFileName");
            txtFileName.value = file.name;
        } catch (e) {
        }
    })
    .bind('fileQueueError', function(event, file, errorCode, message){
        alert('Size of the file '+file.name+' is greater than limit');
    })
    .bind('fileDialogComplete', function(event, numFilesSelected, numFilesQueued){
        var swfu = $.swfupload.getInstance('#swfupload-control');
        var btnSubmit=$('#btnSubmit');
        btnSubmit.click(function(){
            try {
                swfu.startUpload();
            } catch (ex) {

            }
            return false;
        });
        // validateForm();
    })

    .bind('uploadStart', function(event, file){
        var swfu = $.swfupload.getInstance('#swfupload-control');

        /*
        try {
            var progress = new FileProgress(file, swfu.customSettings.progressTarget);
            progress.setStatus("Uploading...");
            progress.toggleCancel(true, this);
            trackFiles[trackFilesCount++] = file.name;
            updateDisplay.call(swfu,file);
        }
        catch (ex) {}
        */
        return true;
    })

    .bind('uploadProgress', function(event, file, bytesLoaded, bytesTotal){
        var swfu = $.swfupload.getInstance('#swfupload-control');
        try {
            var percent = Math.ceil((bytesLoaded / bytesTotal) * 100);
            //file.id = "singlefile";
            var progress = new FileProgress(file, swfu.customSettings.progressTarget);
            var animPic = document.getElementById("loadanim");
            if (animPic != null) {
              animPic.style.display = 'block';
            }
            progress.setStatus("Uploading..."+(isMacUser && file.size < MacMinSizeUpload ? ' ...Finishing up, 10 second delay' : ''));
            progress.setProgress(percent);
            $('#fsUploadProgress2').text(percent+'%  ');
            updateDisplay.call(swfu, file);
        } catch (ex) {
            swfu.debug(ex);
        }
    })
    .bind('uploadSuccess', function(event, file, serverData){
        var swfu = $.swfupload.getInstance('#swfupload-control');
        try {
            //file.id = "singlefile";
            var progress = new FileProgress(file, swfu.customSettings.progressTarget);
            progress.setComplete();
            progress.setStatus("Complete.");
            progress.toggleCancel(false);

            if (serverData === " ") {
                swfu.customSettings.upload_successful = false;
            } else {
                swfu.customSettings.upload_successful = true;
                document.getElementById("hidFileID").value = serverData;
            }
        } catch (ex) {
            swfu.debug(ex);
        }
    })

    .bind('uploadComplete', function(event, file){
        // upload has completed, try the next one in the queue
        //$(this).swfupload('startUpload');
        var swfu = $.swfupload.getInstance('#swfupload-control');
        try {
            if (swfu.customSettings.upload_successful) {
                // leave but enabled for multiple uploads
                // swfu.setButtonDisabled(true);

                //CALL BACK uploadDone(); OR
                //FORM SUBMIT document.forms[0].submit();

                var txtezpFieldFileName = document.getElementById("{/literal}ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}{literal}");
                var txtezpFieldRootName = document.getElementById("{/literal}ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}0_{$attribute.contentclass_attribute_identifier}{literal}");
                txtezpFieldFileName.value = txtezpFieldRootName.value + document.getElementById("txtFileName").value;

                var txtFileName = document.getElementById("txtFileName");
                txtFileName.value = '';

                // disable display is not enough, must remove space as well
                // document.getElementById("SWFUpload_0_0").style.visibility = 'hidden';

                // We do not need to "over" alert the user the file was uploaded successfully
                // alert('Congratutlaions Your File Has Been Uploaded!!');
            } else {
              //  file.id = "singlefile";    // This makes it so FileProgress only makes a single UI element, instead of one for each file
                var progress = new FileProgress(file, swfu.customSettings.progress_target);
                progress.setError();
                progress.setStatus("File rejected");
                progress.toggleCancel(false);

                var txtFileName = document.getElementById("txtFileName");
                txtFileName.value = "";
                // validateForm();
                alert("There was a problem with the upload.\nThe server did not accept it.");
            }
        } catch (e) {
        }
    })

/// END
});

function updateDisplay(swfu,file) {
  // isMacUser Patch Begin
  if ( isMacUser ) {
    if (file == null && forceDone) {
      master.cancelUpload(forceFile.id,false);
      pauseProcess(500); // allow flash? to update itself
      master.uploadSuccess(forceFile,null);
      master.uploadComplete(forceFile);
      forceDone = false;
      return;
    }
    // check for small files less < 150k
    // note: dialup users will get bad results.
    if (file.size < MacMinSizeUpload && !forceDone) {
      master = swfu;
      if (!forceDone) {
        forceFile = file;
        // wait <n> seconds before enforcing upload done!
        setTimeout("updateDisplay("+null+","+null+")",MacDelay);
        forceDone = true;
      }
    }
  } // isMacUser Patch End
}
{/literal}
</script>

{def $parentNode=fetch( 'content', 'node', hash( 'node_id', $attribute.object.current.temp_main_node.parent_node_id ) )}

File Path: <input readonly id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}{$attribute.contentclassattribute_id}_{$attribute.contentclass_attribute_identifier}" class="{eq( $html_class, 'half' )|choose( 'box', 'halfbox' )} ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}" type="text" size="70" name="{$attribute_base}_ezstring_data_text_{$attribute.id}" value="{if or( $attribute.data_text|eq(''), $attribute.data_text|contains('/')|not )}{concat( $parentNode.url, '/', $attribute.data_text|wash( xhtml ) )}{else}{$attribute.data_text|wash( xhtml )}{/if}" />

Root Path: <input id="ezcoa-{if ne( $attribute_base, 'ContentObjectAttribute' )}{$attribute_base}-{/if}0_{$attribute.contentclass_attribute_identifier}" class="{eq( $html_class, 'half' )|choose( 'box', 'halfbox' )} ezcc-{$attribute.object.content_class.identifier} ezcca-{$attribute.object.content_class.identifier}_{$attribute.contentclass_attribute_identifier}" type="text" size="70" name="ParentPath" readonly value="{concat( $parentNode.url, '/')}" />

<div id="content">
    {* <form id="s3FileUploadForm" action="#" enctype="multipart/form-data" method="post"> *}
        <div class="fieldset">
            <table>
                <tr>
                    <td>
                        <div>
                            <div>
                                <div id="swfupload-control"><input type="button" id="mybutton" /></div>
                                <div id="fsUploadProgress2"></div>
                                <input type="text" id="txtFileName" disabled="true" />
                                <input type="submit" value="Upload" id="btnSubmit" />
                            </div>
                            {* <!-- This is the container that the upload progress elements will be added to --> *}

                            <div id="uploadProgressContainer">
                            <div class="flash" id="fsUploadProgress"></div>
                            </div>
                            <input type="hidden" name="hidFileID" id="hidFileID" value="" />
                            {* <!-- This is where the file ID is stored after SWFUpload uploads the file and gets the ID back from upload.php --> *}
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    {* </form> *}
</div>

{/default}
