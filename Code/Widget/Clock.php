<?php

namespace Code\Widget;

class Clock implements WidgetInterface
{

    public function widget(array $arguments): string
    {
        $miltime = ((isset($arguments['military']) && $arguments['military']) ? intval($arguments['military']) : false);
        return <<< EOT
<div class="widget">
<h3 class="clockface"></h3>
<script>
let timerID = null
let timerRunning = false

function stopclock(){
    if(timerRunning)
        clearTimeout(timerID)
    timerRunning = false
}

function startclock(){
    stopclock()
    showtime()
}

function showtime() {
    let now = new Date()
    let hours = now.getHours()
    let minutes = now.getMinutes()
    let seconds = now.getSeconds()
	let military = $miltime
    let timeValue = ""
	if(military)
		timeValue = hours
	else
		timeValue = ((hours > 12) ? hours - 12 : hours)
    timeValue  += ((minutes < 10) ? ":0" : ":") + minutes
//    timeValue  += ((seconds < 10) ? ":0" : ":") + seconds
	if(! military)
	    timeValue  += (hours >= 12) ? " P.M." : " A.M."
    $('.clockface').html(timeValue)
    timerID = setTimeout("showtime()",1000)
    timerRunning = true
}

$(document).ready(function() {
	startclock();
});

</script>
</div>
EOT;

    }
}
