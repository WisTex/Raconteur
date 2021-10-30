<div class="generic-content-wrapper">
<div class="section-title-wrapper"><h2>{{$banner}}</h2></div>
<div class="section-content-wrapper">
<div id="failed_updates_desc">{{$desc}}</div>

{{if $failed}}
{{foreach $failed as $f}}

<h4>{{$f}}</h4>
<ul>
<li><a href="{{$base}}/admin/dbsync/mark/{{$f}}">{{$mark}}</a></li>
<li><a href="{{$base}}/admin/dbsync/verify/{{$f}}">{{$verify}}</a></li>
<li><a href="{{$base}}/admin/dbsync/{{$f}}">{{$apply}}</a></li>
</ul>

<hr />
{{/foreach}}
{{/if}}
</div>
</div>
