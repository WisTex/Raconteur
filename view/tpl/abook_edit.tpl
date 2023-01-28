<div class="generic-content-wrapper">
	<div class="section-title-wrapper">
		{{if $notself}}
		<div class="float-end">
			<div class="btn-group">
				<button id="connection-dropdown" class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
					<i class="fa fa-cog"></i>&nbsp;{{$tools_label}}
				</button>
				<div class="dropdown-menu dropdown-menu-right" aria-labelledby="dLabel">
					<a class="dropdown-item" href="{{$tools.view.url}}" title="{{$tools.view.title}}">{{$tools.view.label}}</a>
					<a class="dropdown-item" href="{{$tools.recent.url}}" title="{{$tools.recent.title}}">{{$tools.recent.label}}</a>
					<a class="dropdown-item" href="#" title="{{$tools.refresh.title}}" onclick="window.location.href='{{$tools.refresh.url}}'; return false;">{{$tools.refresh.label}}</a>
					<a class="dropdown-item" href="#" title="{{$tools.rephoto.title}}" onclick="window.location.href='{{$tools.rephoto.url}}'; return false;">{{$tools.rephoto.label}}</a>
					<div class="dropdown-divider"></div>
					<a class="dropdown-item" href="#" title="{{$tools.block.title}}" onclick="window.location.href='{{$tools.block.url}}'; return false;">{{$tools.block.label}}</a>
					<a class="dropdown-item" href="#" title="{{$tools.ignore.title}}" onclick="window.location.href='{{$tools.ignore.url}}'; return false;">{{$tools.ignore.label}}</a>
					<a class="dropdown-item" href="#" title="{{$tools.censor.title}}" onclick="window.location.href='{{$tools.censor.url}}'; return false;">{{$tools.censor.label}}</a>
					<a class="dropdown-item" href="#" title="{{$tools.archive.title}}" onclick="window.location.href='{{$tools.archive.url}}'; return false;">{{$tools.archive.label}}</a>					<a class="dropdown-item" href="#" title="{{$tools.hide.title}}" onclick="window.location.href='{{$tools.hide.url}}'; return false;">{{$tools.hide.label}}</a>
					<a class="dropdown-item" href="#" title="{{$tools.delete.title}}" onclick="window.location.href='{{$tools.delete.url}}'; return false;">{{$tools.delete.label}}</a>
				</div>
			</div>
			{{if $abook_prev || $abook_next}}
			<div class="btn-group">
				<a href="connedit/{{$abook_prev}}{{if $section}}?f=&section={{$section}}{{/if}}" class="btn btn-outline-secondary btn-sm{{if ! $abook_prev}} disabled{{/if}}" ><i class="fa fa-backward"></i></a>
				<div class="btn-group" >
					<button class="btn btn-outline-secondary btn-sm{{if $is_pending}} disabled{{/if}}" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fa fa-bars"></i></button>
					<div class="dropdown-menu dropdown-menu-right" aria-labelledby="dLabel">
						{{foreach $sections as $s}}
						<a class="dropdown-item" href="{{$s.url}}" title="{{$s.title}}">{{$s.label}}</a>
						{{/foreach}}
					</div>
				</div>
				<a href="connedit/{{$abook_next}}{{if $section}}?f=&section={{$section}}{{/if}}" class="btn btn-outline-secondary btn-sm{{if ! $abook_next}} disabled{{/if}}" ><i class="fa fa-forward"></i></a>
			</div>
			{{/if}}
		</div>
		{{/if}}
		<h2>{{$header}}</h2>
	</div>
	<div class="section-content-wrapper-np">
		{{if $notself}}
		{{foreach $tools as $tool}}
		{{if $tool.info}}
		<div class="section-content-danger-wrapper">
			<div>
				{{$tool.info}}
			</div>
		</div>
		{{/if}}
		{{/foreach}}
		<div class="section-content-info-wrapper">
			<div>
				{{$addr_text}} <strong>'{{if $addr}}{{$addr}}{{else}}{{$primeurl}}{{/if}}'</strong>			
			</div>
			{{if $locstr}}
			<div>
				{{$loc_text}} {{$locstr}}
			</div>
			{{/if}}
			{{if $unclonable}}
			<div>
				<br>{{$unclonable}}
			</div>
			<br>
			{{/if}}
			{{if $last_update}}
			<div>
				{{$lastupdtext}} {{$last_update}}
			</div>
			{{/if}}
		</div>
		{{/if}}

		<form id="abook-edit-form" action="connedit/{{$contact_id}}" method="post" >

		<input type="hidden" id="contact_id" name="contact_id" value="{{$contact_id}}">
		<input type="hidden" name="section" value="{{$section}}">

		<div class="panel-group" id="contact-edit-tools" role="tablist" aria-multiselectable="true">

			<div class="panel">
				<div class="section-subtitle-wrapper" role="tab" id="alias-tool">
					<h3>
						<a data-toggle="collapse" data-parent="#contact-edit-tools" href="#alias-tool-collapse" aria-expanded="true" aria-controls="alias-tool-collapse">
							{{$alias_label}}
						</a>
					</h3>
				</div>
				<div id="alias-tool-collapse" class="panel-collapse collapse show" role="tabpanel" aria-labelledby="alias-tool">
					<div class="section-content-tools-wrapper">
						{{include file="field_input.tpl" field=$alias}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>

			{{if $notself}}

			{{if $is_pending}}
			<div class="panel">
				<div class="section-subtitle-wrapper" role="tab" id="pending-tool">
					<h3>
						<a data-toggle="collapse" data-parent="#contact-edit-tools" href="#pending-tool-collapse" aria-expanded="true" aria-controls="pending-tool-collapse">
							{{$pending_label}}
						</a>
					</h3>
				</div>
				<div id="pending-tool-collapse" class="panel-collapse collapse show" role="tabpanel" aria-labelledby="pending-tool">
					<div class="section-content-tools-wrapper">
						{{include file="field_checkbox.tpl" field=$unapproved}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
			{{/if}}

			{{if $affinity}}
			<div class="panel">
				<div class="section-subtitle-wrapper" role="tab" id="affinity-tool">
					<h3>
						<a data-toggle="collapse" data-parent="#contact-edit-tools" href="#affinity-tool-collapse" aria-expanded="true" aria-controls="affinity-tool-collapse">
							{{$affinity}}
						</a>
					</h3>
				</div>
				<div id="affinity-tool-collapse" class="panel-collapse collapse{{if $section == 'affinity'}} show{{/if}}" role="tabpanel" aria-labelledby="affinity-tool">
					<div class="section-content-tools-wrapper">
						{{if $slide}}
						<div class="form-group"><strong>{{$lbl_slider}}</strong></div>
							<div id="contact-slider" class="slider form-group">
							{{$slide}}
								<div id="slider-container">
								<i class="fa fa-fw fa-user range-icon"></i>
								<input id="contact-range" title="{{$close}}" type="range" min="0" max="99" name="closeness" value="{{$close}}" list="affinity_labels" >
								<datalist id="affinity_labels">
								{{foreach $labels as $k => $v}}
								<option value={{$k}} label="{{$v}}">
								{{/foreach}}
								</datalist>
								<i class="fa fa-fw fa-users range-icon"></i>
								<span class="range-value">{{$close}}</span>
								</div>
							</div>
						</div>
						{{/if}}

						{{if $multiprofs}}
						<div class="form-group">
							<strong>{{$lbl_vis2}}</strong>
							{{$profile_select}}
						</div>
						{{/if}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
			{{/if}}

			{{if $connfilter}}
			<div class="panel">
				<div class="section-subtitle-wrapper" role="tab" id="fitert-tool">
					<h3>
						<a data-toggle="collapse" data-parent="#contact-edit-tools" href="#fitert-tool-collapse" aria-expanded="true" aria-controls="fitert-tool-collapse">
							{{$connfilter_label}}
						</a>
					</h3>
				</div>
				<div id="fitert-tool-collapse" class="panel-collapse collapse{{if $section == 'filter' }} show{{/if}}" role="tabpanel" aria-labelledby="fitert-tool">
					<div class="section-content-tools-wrapper">
						{{include file="field_textarea.tpl" field=$incl}}
						{{include file="field_textarea.tpl" field=$excl}}
						<div class="settings-submit-wrapper" >
							<button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
			{{else}}
			<input type="hidden" name="{{$incl.0}}" value="{{$incl.2}}" />
			<input type="hidden" name="{{$excl.0}}" value="{{$excl.2}}" />
			{{/if}}

			{{/if}}

			{{if ! $is_pending}}
			<div class="panel">
				{{if $notself}}
				<div class="section-subtitle-wrapper" role="tab" id="perms-tool">
					<h3>
						<a data-toggle="collapse" data-parent="#contact-edit-tools" href="#perms-tool-collapse" aria-expanded="true" aria-controls="perms-tool-collapse">
							{{$permlbl}}
						</a>
					</h3>
				</div>
				{{/if}}
				<div id="perms-tool-collapse" class="panel-collapse collapse{{if $self || $section === 'perms'}} show{{/if}}" role="tabpanel" aria-labelledby="perms-tool">
					<div class="section-content-tools-wrapper">
						{{include file="field_checkbox.tpl" field=$block_announce}}
						<div class="section-content-warning-wrapper">
						{{if $notself}}{{$permnote}}{{/if}}
						{{if $self}}{{$permnote_self}}{{/if}}
						</div>

						{{if $permcat_enable}}
						<a href="settings/permcats" class="float-end"><i class="fa fa-plus"></i>&nbsp;{{$permcat_new}}</a>
						{{include file="field_select.tpl" field=$permcat}}
						{{/if}}

						<table id="perms-tool-table" class="form-group">
							<tr>
								<td></td>
								{{if $notself}}
								<td class="abook-them">{{$them}}</td>
								{{/if}}
								<td colspan="2" class="abook-me">{{$me}}</td>
							</tr>
							{{foreach $perms as $prm}}
							{{include file="field_acheckbox.tpl" field=$prm}}
							{{/foreach}}
						</table>

						{{if $self}}
						<div>
							<div class="section-content-info-wrapper">
								{{$autolbl}}
							</div>
							{{include file="field_checkbox.tpl" field=$autoperms}}
						</div>
						{{/if}}

						<div class="settings-submit-wrapper" >
							<button type="submit" name="done" value="{{$submit}}" class="btn btn-primary">{{$submit}}</button>
						</div>
					</div>
				</div>
			</div>
			{{/if}}
		</div>
		</form>
	</div>
</div>
