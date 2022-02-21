<div class = "generic-content-wrapper" id='adminpage'>
	<div class="section-title-wrapper"><h1>{{$title}} - {{$page}}</h1></div>
	<div class="section-content-wrapper">

	<p>{{if ! $info.disabled}}<i class='toggleplugin fa {{if $status==on}}fa-check-square-o{{else}}fa-square-o{{/if}} admin-icons'></i>{{else}}<i class='fa fa-stop admin-icons'></i>{{/if}} {{$info.name}} - {{$info.version}}{{if ! $info.disabled}} : <a href="{{$baseurl}}/admin/{{$function}}/{{$plugin}}/?a=t&amp;t={{$form_security_token}}">{{$action}}</a>{{/if}}</p>

	{{if $info.disabled}}
	<p>{{$disabled}}</p>
	{{/if}}

	<p>{{$info.description}}</p>
    {{if is_array($info.author) }}	
	{{foreach $info.author as $a}}
	<p class="author">{{$str_author}}
		{{$a}}
	</p>
	{{/foreach}}
    {{else}}
    <p class="author">{{$str_author}}
		{{$info.author}}
	</p>
    {{/if}}

	{{if $info.minversion}}
	<p class="versionlimit">{{$str_minversion}}{{$info.minversion}}</p>
	{{/if}}
	{{if $info.maxversion}}
	<p class="versionlimit">{{$str_maxversion}}{{$info.maxversion}}</p>
	{{/if}}
	{{if $info.minphpversion}}
	<p class="versionlimit">{{$str_minphpversion}}{{$info.minphpversion}}</p>
	{{/if}}
	{{if $info.serverroles}}
	<p class="versionlimit">{{$str_serverroles}}{{$info.serverroles}}</p>
	{{/if}}
	{{if $info.requires}}
	<p class="versionlimit">{{$str_requires}}{{$info.requires}}</p>
	{{/if}}

    {{if is_array($info.maintainer) }}
	{{foreach $info.maintainer as $a}}
	<p class="maintainer">{{$str_maintainer}}
		{{$a}}
	</p>
	{{/foreach}}
    {{else}}
	<p class="maintainer">{{$str_maintainer}}
		{{$info.maintainer}}
	</p>
    {{/if}}    
	
	{{if $screenshot}}
	<a href="{{$screenshot.0}}" class='screenshot'><img src="{{$screenshot.0}}" alt="{{$screenshot.1}}" /></a>
	{{/if}}

	{{if $admin_form}}
	<h3>{{$settings}}</h3>
	<form method="post" action="{{$baseurl}}/admin/{{$function}}/{{$plugin}}/">
		{{$admin_form}}
	</form>
	{{/if}}

	{{if $readme}}
	<h3>Readme</h3>
	<div id="plugin_readme">
		{{$readme}}
	</div>
	{{/if}}
	</div>
</div>
