<div id="cloud-drag-area" class="section-content-wrapper-np">
{{if $tiles}}
	<table id="cloud-index">
		<tr id="new-upload-progress-bar-1"></tr> {{* this is needed to append the upload files in the right order *}}
	</table>

	{{if $parentpath}}
	<div class="cloud-container" >

	<div class="cloud-icon tiles"><a href="{{$parentpath.path}}">
	<div class="cloud-icon-container"><i class="fa fa-fw fa-level-up" ></i></div>
	</a>
	</div>
	<div class="cloud-title"><a href="{{$parentpath.path}}">..</a>
	</div>
	</div>
	{{/if}}

	{{foreach $entries as $item}}
	<div class="cloud-container">
	<div class="cloud-icon tiles"><a href="{{$item.fullPath}}">
	{{if $item.photo_icon}}
	<img src="{{$item.photo_icon}}" title="{{$item.type}}" >
	{{else}}
	<div class="cloud-icon-container"><i class="fa fa-fw {{$item.iconFromType}}" title="{{$item.type}}"></i></div>
	{{/if}}
	</a>
	</div>
	<div class="cloud-title"><a href="{{$item.fullPath}}">
	{{$item.displayName}}
	</a>
	</div>
	{{if $item.is_owner}}

	{{/if}}
	</div>
	{{/foreach}}
	<div class="clear"></div>
{{else}}
	<table id="cloud-index">
		<tr>
			<th width="1%"></th>
			<th width="92%">{{$name}}</th>
			<th width="1%"></th><th width="1%"></th><th width="1%"></th><th width="1%"></th>
			<th width="1%">{{*{{$type}}*}}</th>
			<th width="1%" class="d-none d-md-table-cell">{{$size}}</th>
			<th width="1%" class="d-none d-md-table-cell">{{$lastmod}}</th>
		</tr>
	{{if $parentpath}}
		<tr>
			<td><i class="fa fa-level-up"></i>{{*{{$parentpath.icon}}*}}</td>
			<td><a href="{{$parentpath.path}}" title="{{$parent}}">..</a></td>
			<td></td><td></td><td></td><td></td>
			<td>{{*[{{$parent}}]*}}</td>
			<td class="d-none d-md-table-cell"></td>
			<td class="d-none d-md-table-cell"></td>
		</tr>
	{{/if}}
		<tr id="new-upload-progress-bar-1"></tr> {{* this is needed to append the upload files in the right order *}}
	{{foreach $entries as $item}}
		<tr id="cloud-index-{{$item.attachId}}">
			<td><i class="fa {{$item.iconFromType}}" title="{{$item.type}}"></i></td>
			<td><a href="{{$item.fullPath}}">{{$item.displayName}}</a></td>
	{{if $item.is_owner}}
			<td class="cloud-index-tool">{{$item.attachIcon}}</td>
			<td class="cloud-index-tool"><div id="file-edit-{{$item.attachId}}" class="spinner-wrapper"><div class="spinner s"></div></div></td>
			<td class="cloud-index-tool"><i class="fakelink fa fa-pencil" onclick="filestorage(event, '{{$nick}}', {{$item.attachId}});"></i></td>
			<td class="cloud-index-tool"><a href="#" title="{{$delete}}" onclick="dropItem('{{$item.fileStorageUrl}}/{{$item.attachId}}/delete', '#cloud-index-{{$item.attachId}},#cloud-tools-{{$item.attachId}}'); return false;"><i class="fa fa-trash-o drop-icons"></i></a></td>

	{{else}}
			<td></td><td></td><td></td><td></td>
	{{/if}}
			<td>{{*{{$item.type}}*}}</td>
			<td class="d-none d-md-table-cell">{{$item.sizeFormatted}}</td>
			<td class="d-none d-md-table-cell">{{$item.lastmodified}}</td>
		</tr>
		<tr id="cloud-tools-{{$item.attachId}}">
			<td id="perms-panel-{{$item.attachId}}" colspan="9"></td>
		</tr>

	{{/foreach}}
	</table>
{{/if}}
</div>
