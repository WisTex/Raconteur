<div class="generic-content-wrapper-styled">
<h2>{{$title}}</h2>

<img src="{{$prj_icon}}" alt="project icon" style="max-height: 150px; max-width: 150px;"/><br><br>

<h3>{{$sitenametxt}}</h3>

<div><a href="{{$url}}">{{$sitename}}</a></div><br>

<h3>{{$headline}}</h3>

<div>{{if $site_about}}{{$site_about}}{{else}}--{{/if}}</div><br>

<h3>{{$admin_headline}}</h3>

<div>{{if $admin_about}}{{$admin_about}}{{else}}--{{/if}}</div><br>

<div><i class="fa fa-fw fa-legal"></i>&nbsp;<a href="help/TermsOfService">{{$terms}}</a></div>


</div>
