<script src="library/blueimp_upload/js/vendor/jquery.ui.widget.js"></script>
<script src="library/blueimp_upload/js/jquery.iframe-transport.js"></script>
<script src="library/blueimp_upload/js/jquery.fileupload.js"></script>
<input id="invisible-cloud-file-upload" type="file" name="files" style="visibility:hidden;position:absolute;top:-50;left:-50;width:0;height:0;" multiple>

<div class="generic-content-wrapper">
{{include file="cloud_header.tpl"}}
{{include file="cloud_directory.tpl"}}
</div>
