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
	<span class="range-value">{{$cmax}}</span>
	</div>
	<div id="profile-jot-text-loading" class="spinner-wrapper">
		<div class="spinner m"></div>
	</div>
</div>

<script>
$(document).ready(function() {

	var old_cmin = 0;
	var old_cmax = {{$cmax}};
	var slideTimer = null;

	$("#main-range").on('input', function() { sliderUpdate(); });
	$("#main-range").on('change', function() { sliderUpdate(); });

	function sliderUpdate() {
		bParam_cmax = $("#main-range").val();
		if(bParam_cmax == old_cmax) 
			return;
		old_cmax = bParam_cmax;
		$("#main-range").attr('title',bParam_cmax);
		$(".range-value").html(bParam_cmax);
		networkRefresh();
	}

	// "de-bounce" circuit
	// when a change occurs, indicate "busy", but wait (2 seconds) 
	// before loading fresh content. This allows the slider value to
	// change further during that time and avoids a network fetch for 
	// every individual integer value change. 

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
