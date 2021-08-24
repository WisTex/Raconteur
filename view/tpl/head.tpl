<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
<base href="{{$baseurl}}/" />
<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1, user-scalable={{$user_scalable}}" />
{{$metas}}
{{$head_css}}
{{$js_strings}}
{{$head_js}}
{{$linkrel}}
{{$plugins}}
<script>
	let updateInterval = {{$update_interval}};
	let alertsInterval = {{$alerts_interval}};
	let localUser = {{if $local_channel}}{{$local_channel}}{{else}}false{{/if}};
	let zid = {{if $zid}}'{{$zid}}'{{else}}null{{/if}};
	let justifiedGalleryActive = false;
	{{if $channel_hash}}let channelHash = '{{$channel_hash}}';{{/if}}
	{{if $channel_id}}let channelId = '{{$channel_id}}';{{/if}}{{* Used in e.g. autocomplete *}}
	let preloadImages = {{$preload_images}};
</script>



