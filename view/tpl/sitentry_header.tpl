<div class="generic-content-wrapper">
	<div class="section-title-wrapper clearfix">
		<h2>{{$dirlbl}}</h2>
	</div>
	<div class="descriptive-text" style="margin: 5px;">{{$desc}}</div>
	{{foreach $entries as $entry}}
		{{include file="sitentry.tpl"}}
	{{/foreach}}
	<div id="page-end"></div>
</div>
<script>$(document).ready(function() { loadingPage = false;});</script>
<div id="page-spinner" class="spinner-wrapper">
	<div class="spinner m"></div>
</div>
