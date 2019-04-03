<div id="affinity-slider" class="slider" >
	<div id="slider-container">
	<i class="fa fa-fw fa-user range-icon"></i>
	<input id="affinity-range" title="{{$cmax}}" type="range" min="0" max="99" name="cmax" value="{{$cmax}}" list="affinity_labels" >
	<datalist id="affinity_labels">
	{{foreach $labels as $k => $v}}
		<option value={{$k}} label="{{$v}}">
	{{/foreach}}
	</datalist>
	<i class="fa fa-fw fa-users range-icon"></i>
	<input type="hidden" class="range-value" name="affinity_cmax" value="{{$cmax}}" >
	<span class="range-value">{{$cmax}}</span>
	</div>
</div>
<br><br>
<script>
$(document).ready(function() {

	var old_cmin = 0;
	var old_cmax = {{$cmax}};
	var slideTimer = null;
	var cmax = {{$cmax}};

	$("#affinity-range").on('input', function() { sliderUpdate(); });
	$("#affinity-range").on('change', function() { sliderUpdate(); });

	function sliderUpdate() {
		var cmax = $("#affinity-range").val();
		if(cmax == old_cmax) 
			return;
		old_cmax = cmax;
		$("#affinity-range").attr('title',cmax);
		$("input[name=affinity_cmax]").val(cmax);
		$(".range-value").html(cmax);
	}


});
</script>
