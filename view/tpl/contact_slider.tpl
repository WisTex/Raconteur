<script>
$(document).ready(function() {

	$("#contact-range").on('input', function() { csliderUpdate(); });
	$("#contact-range").on('change', function() { csliderUpdate(); });

	function csliderUpdate() {
		$(".range-value").html($("#contact-range").val());
	}

});
</script>
