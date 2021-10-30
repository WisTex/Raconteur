<div class="generic-content-wrapper">
<div class="section-title-wrapper clearfix"><h2>{{$arrange}}</h2></div>
<div class="section-content-wrapper clearfix">
{{if $navbar_apps}}
<h3>{{$header.0}}</h3>
<div class="descriptive-text">{{$desc.0}}</div>
<br><br>
{{foreach $navbar_apps as $navbar_app}}
{{$navbar_app}}
{{/foreach}}
<br><br>
{{/if}}
<h3>{{$header.1}}</h3>
<div class="descriptive-text">{{$desc.1}}</div>
<br><br>
{{foreach $nav_apps as $nav_app}}
{{$nav_app}}
{{/foreach}}
</div>
</div>