<div class="generic-content-wrapper">
    <div class="section-title-wrapper">
        <a title="{{$removechannel}}" class="btn btn-danger btn-sm float-end" href="removeme"><i class="fa fa-trash-o"></i>&nbsp;{{$removeme}}</a>
        <h2>{{$ptitle}}</h2>
        <div class="clear"></div>
    </div>
    {{$nickname_block}}
    <form action="settings" id="settings-form" method="post" autocomplete="off" class="acl-form" data-form_id="settings-form" data-allow_cid='{{$allow_cid}}' data-allow_gid='{{$allow_gid}}' data-deny_cid='{{$deny_cid}}' data-deny_gid='{{$deny_gid}}'>
        <input type='hidden' name='form_security_token' value='{{$form_security_token}}' />
        <div class="panel-group" id="settings" role="tablist" aria-multiselectable="true">
            <div class="panel">
                <div class="section-subtitle-wrapper" role="tab" id="basic-settings">
                    <h3>
                        <a data-bs-toggle="collapse" data-bs-target="#basic-settings-collapse" href="#">
                            {{$h_basic}}
                        </a>
                    </h3>
                </div>
                <div id="basic-settings-collapse" class="collapse show" role="tabpanel" aria-labelledby="basic-settings" data-bs-parent="#settings">
                    <div class="section-content-tools-wrapper">
                        {{include file="field_input.tpl" field=$channel_name}}
                        {{include file="field_input.tpl" field=$defloc}}
                        {{include file="field_checkbox.tpl" field=$allowloc}}
                        {{include file="field_input.tpl" field=$set_location}}
                        {{include file="field_checkbox.tpl" field=$adult}}
                        {{include file="field_input.tpl" field=$photo_path}}
                        {{include file="field_input.tpl" field=$attach_path}}
                        {{if $basic_addon}}
                            {{$basic_addon}}
                        {{/if}}
                        <div class="settings-submit-wrapper" >
                            <button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel">
                <div class="section-subtitle-wrapper" role="tab" id="privacy-settings">
                    <h3>
                        <a data-bs-toggle="collapse" data-bs-target="#privacy-settings-collapse" href="#">
                            {{$h_prv}}
                        </a>
                    </h3>
                </div>
                <div id="privacy-settings-collapse" class="collapse" role="tabpanel" aria-labelledby="privacy-settings" data-bs-parent="#settings">
                    <div class="section-content-tools-wrapper">
                        {{if $can_change_role}}
                            {{include file="field_select_grouped.tpl" field=$role}}
                            <div class="descriptive-text" id="channel_role_text"></div>
                        {{else}}
                            <input type="hidden" name="permissions_role" value="{{$permissions_role}}" >
                        {{/if}}
                        {{$autoperms}}
                        {{$anymention}}
                        {{include file="field_select.tpl" field=$comment_perms}}
                        {{include file="field_checkbox.tpl" field=$permit_moderated_comments}}
                        {{include file="field_input.tpl" field=$close_comments}}
                        {{include file="field_select.tpl" field=$mail_perms}}
                        {{include file="field_select.tpl" field=$view_contact_perms}}
                        {{include file="field_select.tpl" field=$search_perms}}
                        {{include file="field_checkbox.tpl" field=$permit_all_likes}}
                        {{include file="field_checkbox.tpl" field=$permit_all_mentions}}
                        {{include file="field_input.tpl" field=$unless_mention_count}}
                        {{include file="field_input.tpl" field=$followed_tags}}
                        {{include file="field_input.tpl" field=$unless_tag_count}}
                        {{include file="field_checkbox.tpl" field=$preview_outbox}}
                        {{include file="field_checkbox.tpl" field=$nomadic_ids_in_profile}}
                        <div id="advanced-perm" style="display:{{if $permissions_set}}none{{else}}block{{/if}};">
                            <div class="form-group">
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#apsModal">{{$lbl_p2macro}}</button>
                            </div>
                            <div class="modal" id="apsModal">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h4 class="modal-title">{{$lbl_p2macro}}</h4>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            {{foreach $permiss_arr as $permit}}
                                                {{include file="field_select.tpl" field=$permit}}
                                            {{/foreach}}
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{$close}}</button>
                                        </div>
                                    </div><!-- /.modal-content -->
                                </div><!-- /.modal-dialog -->
                            </div><!-- /.modal -->

                            <div id="settings-default-perms" class="form-group" >
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#aclModal"><i id="jot-perms-icon" class="fa"></i>&nbsp;{{$permissions}}</button>
                            </div>
                            {{$group_select}}
                            {{include file="field_checkbox.tpl" field=$hide_presence}}
                        </div>
                        <div class="settings-common-perms">
                            {{$profile_in_dir}}
                            {{include file="field_checkbox.tpl" field=$noindex}}
                            {{$suggestme}}
                            {{include file="field_input.tpl" field=$expire}}
                            {{include file="field_checkbox.tpl" field=$hyperdrive}}
                        </div>
                        {{if $permcat_enable}}
                            {{include file="field_select.tpl" field=$defpermcat}}
                        {{/if}}

                        {{if $sec_addon}}
                            {{$sec_addon}}
                        {{/if}}
                        <div class="settings-submit-wrapper" >
                            <button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="section-subtitle-wrapper" role="tab" id="notification-settings">
                    <h3>
                        <a data-bs-toggle="collapse" data-bs-target="#notification-settings-collapse" href="#">
                            {{$h_not}}
                        </a>
                    </h3>
                </div>
                <div id="notification-settings-collapse" class="collapse" role="tabpanel" aria-labelledby="notification-settings" data-bs-parent="#settings">
                    <div class="section-content-tools-wrapper">
                        <div id="settings-notifications">

                            <div id="desktop-notifications-info" class="section-content-warning-wrapper" style="display: none;">
                                {{$desktop_notifications_info}}<br>
                                <a id="desktop-notifications-request" href="#">{{$desktop_notifications_request}}</a>
                            </div>

                            {{include file="field_input.tpl" field=$mailhost}}

                            <h3>{{$activity_options}}</h3>
                            <div class="group">
                                {{*not yet implemented *}}
                                {{*include file="field_checkbox.tpl" field=$post_joingroup*}}
                                {{include file="field_checkbox.tpl" field=$post_newfriend}}
                                {{include file="field_checkbox.tpl" field=$post_profilechange}}
                            </div>
                            <h3>{{$lbl_not}}</h3>
                            <div class="group">
                                {{include file="field_intcheckbox.tpl" field=$notify1}}
                                {{*include file="field_intcheckbox.tpl" field=$notify2*}}
                                {{include file="field_intcheckbox.tpl" field=$notify3}}
                                {{include file="field_intcheckbox.tpl" field=$notify4}}
                                {{include file="field_intcheckbox.tpl" field=$notify10}}
                                {{*include file="field_intcheckbox.tpl" field=$notify9*}}
                                {{include file="field_intcheckbox.tpl" field=$notify5}}
                                {{*include file="field_intcheckbox.tpl" field=$notify6*}}
                                {{include file="field_intcheckbox.tpl" field=$notify7}}
                                {{*include file="field_intcheckbox.tpl" field=$notify8*}}
                            </div>
                            <h3>{{$lbl_vnot}}</h3>
                            <div class="group">
                                {{include file="field_intcheckbox.tpl" field=$vnotify1}}
                                {{include file="field_intcheckbox.tpl" field=$vnotify2}}
                                {{include file="field_intcheckbox.tpl" field=$vnotify3}}
                                {{include file="field_intcheckbox.tpl" field=$vnotify4}}
                                {{include file="field_intcheckbox.tpl" field=$vnotify5}}
                                {{include file="field_intcheckbox.tpl" field=$vnotify6}}
                                {{include file="field_intcheckbox.tpl" field=$vnotify10}}
                                {{include file="field_intcheckbox.tpl" field=$vnotify7}}
                                {{include file="field_intcheckbox.tpl" field=$vnotify8}}
                                {{include file="field_intcheckbox.tpl" field=$vnotify9}}
                                {{if $vnotify11}}
                                    {{include file="field_intcheckbox.tpl" field=$vnotify11}}
                                {{/if}}
                                {{*include file="field_intcheckbox.tpl" field=$vnotify12*}}
                                {{if $vnotify13}}
                                    {{include file="field_intcheckbox.tpl" field=$vnotify13}}
                                {{/if}}
                                {{include file="field_intcheckbox.tpl" field=$vnotify14}}
                                {{*include file="field_intcheckbox.tpl" field=$vnotify15*}}
                                {{include file="field_intcheckbox.tpl" field=$vnotify17}}
                                {{if $vnotify16}}
                                    {{include file="field_intcheckbox.tpl" field=$vnotify16}}
                                {{/if}}
                            </div>
                        </div>
                        {{if $notify_addon}}
                            {{$notify_addon}}
                        {{/if}}
                        <div class="settings-submit-wrapper" >
                            <button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="panel">
                <div class="section-subtitle-wrapper" role="tab" id="time-settings">
                    <h3>
                        <a data-bs-toggle="collapse" data-bs-target="#time-settings-collapse" href="#" aria-expanded="true" aria-controls="time-settings-collapse">
                            {{$lbl_time}}
                        </a>
                    </h3>
                </div>
                <div id="time-settings-collapse" class="collapse" role="tabpanel" aria-labelledby="time-settings" data-bs-parent="#settings" >
                    <div class="section-content-tools-wrapper">

                        {{include file="field_select_grouped.tpl" field=$timezone}}
                        {{include file="field_select.tpl" field=$cal_first_day}}
                        {{include file="field_input.tpl" field=$evdays}}

                        <div class="settings-submit-wrapper" >
                            <button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
                        </div>
                    </div>
                </div>
            </div>



            <div class="panel">
                <div class="section-subtitle-wrapper" role="tab" id="miscellaneous-settings">
                    <h3>
                        <a data-bs-toggle="collapse" data-bs-target="#miscellaneous-settings-collapse" href="#" aria-expanded="true" aria-controls="miscellaneous-settings-collapse">
                            {{$lbl_misc}}
                        </a>
                    </h3>
                </div>
                <div id="miscellaneous-settings-collapse" class="collapse" role="tabpanel" aria-labelledby="miscellaneous-settings" data-bs-parent="#settings" >
                    <div class="section-content-tools-wrapper">

                        {{$activitypub}}
                        {{include file="field_select.tpl" field=$tag_username}}

                        {{if $profselect}}
                            <label for="contact-profile-selector">{{$profseltxt}}</label>
                            {{$profselect}}
                        {{/if}}
                        {{if $menus}}
                            <div class="form-group channel-menu">
                                <label for="channel_menu">{{$menu_desc}}</label>
                                <select name="channel_menu" class="form-control">
                                    {{foreach $menus as $menu }}
                                        <option value="{{$menu.name}}" {{$menu.selected}} >{{$menu.name}} </option>
                                    {{/foreach}}
                                </select>
                            </div>
                        {{/if}}
                        {{if $misc_addon}}
                            {{$misc_addon}}
                        {{/if}}

                        <div class="settings-submit-wrapper" >
                            <button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    {{$aclselect}}
</div>
