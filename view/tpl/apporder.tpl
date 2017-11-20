{{if $navbar_apps}}
<h2>{{$header.0}}</h2>
<div class="descriptive-text">{{$desc.0}}</div>
<br><br>
{{foreach $navbar_apps as $navbar_app}}
{{$navbar_app}}
{{/foreach}}
<br><br>
{{/if}}
<h2>{{$header.1}}</h2>
<div class="descriptive-text">{{$desc.1}}</div>
<br><br>
{{foreach $nav_apps as $nav_app}}
{{$nav_app}}
{{/foreach}}
