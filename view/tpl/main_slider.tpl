<div id="main-slider" class="slider" >
	<div id="slider-container">
	<i class="fa fa-fw fa-user range-icon"></i>
	<input id="main-range" title="{{$cmax}}" type="range" min="0" max="99" name="cmax" value="{{$cmax}}" list="affinity_labels" >
	<datalist id="affinity_labels">
	{{foreach $labels as $k => $v}}
		<option value={{$k}} label="{{$v}}">
	{{/foreach}}
	</datalist>
	<i class="fa fa-fw fa-users range-icon"></i>
	</div>
	<div id="profile-jot-text-loading" class="spinner-wrapper">
		<div class="spinner m"></div>
	</div>
</div>

<script>
$(document).ready(function() {

	var old_cmin = 0;
	var old_cmax = 99;
	var slideTimer = null;

	$("#main-range").change(function() { 
		bParam_cmax = $("#main-range").val();
		$("#main-range").attr('title',bParam_cmax);
		networkRefresh();
	});

	function networkRefresh() {
		if(slideTimer !== null)
			return;
		$("#profile-jot-text-loading").show();
		slideTimer = setTimeout(networkTimerRefresh,2000);
	}

	function networkTimerRefresh() {
		slideTimer = null;
		page_load = true;
		liveUpdate();
	}
});
</script>
