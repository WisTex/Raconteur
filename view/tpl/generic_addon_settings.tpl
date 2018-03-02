<div class="panel">
	<div class="section-subtitle-wrapper" role="tab" id="{{$addon.0}}-settings">
		<h3>
			<a title="{{$addon.2}}" data-toggle="collapse" data-target="#{{$addon.0}}-settings-content" href="#" aria-controls="{{$addon.0}}-settings-content">
				{{if $addon.1|substr:0:1 === '<'}}
				{{$addon.1}}
				{{else}}
				<i class="fa fa-gear"></i> {{$addon.1}}
				{{/if}}
			</a>
		</h3>
	</div>
	<div id="{{$addon.0}}-settings-content" class="panel-collapse collapse" role="tabpanel" aria-labelledby="{{$addon.0}}-settings" data-parent="#settings">
		<div class="section-content-tools-wrapper">
			{{$content}}
			{{if $addon.0}}
			<div class="settings-submit-wrapper" >
				<button id="{{$addon.0}}-submit" type="submit" name="{{$addon.0}}-submit" class="btn btn-primary" value="{{$addon.3}}">{{$addon.3}}</button>
			</div>
			{{/if}}
		</div>
	</div>
</div>
