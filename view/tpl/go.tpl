<h2>{{$title}}</h2>

<div class="descriptive-text">
	<p>{{$m}}</p>
	<p>{{$m1}}</p>
</div>

{{if $options}}
<ul class="nav nav-pills flex-column">
{{foreach $options as $k => $v}}
	<li class="nav-item"><a href="{{$k}}" class="nav-link">{{$v}}</a></li>
{{/foreach}}
</ul>
{{/if}}


