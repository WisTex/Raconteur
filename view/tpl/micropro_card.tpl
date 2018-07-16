<a class="list-group-item{{if $class}} {{$class}}{{/if}} fakelink" href="{{if $click}}#{{else}}{{$url}}{{/if}}" {{if $click}}onclick="{{$click}}"{{/if}}>
	<img class="menu-img-3" src="{{$photo}}" title="{{$title}}" alt="" />{{if $oneway}}<i class="fa fa-fw fa-minus-circle oneway-overlay text-danger"></i>{{/if}}
	<span class="contactname">{{$name}}</span>
	<span class="dropdown-sub-text">{{$addr}}<br>{{$network}}</span>
</a>
