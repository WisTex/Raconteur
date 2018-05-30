<div class="widget">
	<h3>{{$title}}</h3>
	<ul class="nav nav-pills flex-column">
		{{foreach $tabs as $tab}}
		<li class="nav-item"{{if $tab.id}} id="{{$tab.id}}"{{/if}}><a class="nav-link{{if $tab.sel}} {{$tab.sel}}{{/if}}" href="{{$tab.url}}"{{if $tab.title}} title="{{$tab.title}}"{{/if}}>{{if $tab.icon}}<i class="fa fa-fw fa-{{$tab.icon}}"></i> {{/if}}{{$tab.label}}</a></li>
		{{/foreach}}
	</ul>
</div>
