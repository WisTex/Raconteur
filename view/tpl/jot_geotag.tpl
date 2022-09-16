	if(navigator.geolocation) {
		navigator.geolocation.getCurrentPosition(function(position) {
			$('#jot-lat').val(position.coords.latitude);
			$('#jot-lon').val(position.coords.longitude);
			jotLocateStatus();
		});
	}

