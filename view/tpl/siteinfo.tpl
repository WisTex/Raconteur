<div class="generic-content-wrapper-styled">
<h2>{{$title}}</h2>

<img src="{{$prj_icon}}" alt="project icon" /><br><br>

<h3>{{$sitenametxt}}</h3>

<div><a href="{{$url}}">{{$sitename}}</a></div><br>

<h3>{{$headline}}</h3>

<div>{{if $site_about}}{{$site_about}}{{else}}--{{/if}}</div><br>

<h3>{{$admin_headline}}</h3>

<div>{{if $admin_about}}{{$admin_about}}{{else}}--{{/if}}</div><br>

<div><a href="help/TermsOfService">{{$terms}}</a></div>

<hr>

<h2>{{$prj_header}}</h2>

<div>{{$prj_name}}</div>

{{if $prj_version}}
<div>{{$prj_version}}</div>
{{/if}}
<br>

<h3>{{$prj_linktxt}}</h3>

<div><a href="{{$prj_link}}">{{$prj_link}}</a></div><br>

<h3>{{$prj_srctxt}}</h3>

<div><a href="{{$prj_src}}">{{$prj_src}}</a></div><br>

{{if $additional_fed}}
<div>{{$additional_text}} {{$additional_fed}}</div>
{{/if}}

</div>
