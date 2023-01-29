<script>
    $(function() {
        $("#cnftheme").colorbox({
            width: 800,
            onLoad: function(){
                var theme = $("#id_theme :selected").val();
                $("#cnftheme").attr('href',"{{$baseurl}}/admin/themes/"+theme);
            },
            onComplete: function(){
                $(this).colorbox.resize();
                $("#colorbox form").submit(function(e){
                    var url = $(this).attr('action');
                    // can't get .serialize() to work...
                    var data={};
                    $(this).find("input").each(function(){
                        data[$(this).attr('name')] = $(this).val();
                    });
                    $(this).find("select").each(function(){
                        data[$(this).attr('name')] = $(this).children(":selected").val();
                    });
                    console.log(":)", url, data);

                    $.post(url, data, function(data) {
                        if(timer) clearTimeout(timer);
                        updateInit();
                        $.colorbox.close();
                    })

                    return false;
                });

            }
        });
    });
</script>
<div id="adminpage" class="generic-content-wrapper">

    <div class="section-title-wrapper">
        <h2>{{$title}} - {{$page}}</h2>
        <div class="clear"></div>
    </div>

    <form action="{{$baseurl}}/admin/site" method="post">
    <input type='hidden' name='form_security_token' value='{{$form_security_token}}'>

    <div class="panel-group" id="settings" role="tablist" aria-multiselectable="true">
        <div class="panel">
            <div class="section-subtitle-wrapper" role="tab" id="basic-settings">
                <h3>
                <a data-bs-toggle="collapse" data-bs-target="#basic-settings-collapse" href="#">
                {{$h_basic}}
                </a>
                </h3>
            </div>
            <div id="basic-settings-collapse" class="collapse show" role="tabpanel" aria-labelledby="basic-settings" data-parent="#settings">
                <div class="section-content-tools-wrapper">
                    {{include file="field_input.tpl" field=$sitename}}
                    {{include file="field_textarea.tpl" field=$siteinfo}}
                    {{include file="field_textarea.tpl" field=$legal}}
                    {{include file="field_input.tpl" field=$reply_address}}
                    {{include file="field_input.tpl" field=$from_email}}
                    {{include file="field_input.tpl" field=$from_email_name}}
                    {{include file="field_select.tpl" field=$language}}
                    {{include file="field_select.tpl" field=$theme}}
                    {{include file="field_input.tpl" field=$frontpage}}
                    {{include file="field_checkbox.tpl" field=$mirror_frontpage}}
                    {{include file="field_checkbox.tpl" field=$login_on_homepage}}
                    <div class="settings-submit-wrapper" >
                        <button type="submit" name="page_site" class="btn btn-primary" value="1" >{{$submit}}</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="section-subtitle-wrapper" role="tab" id="reg-settings">
                <h3>
                <a data-bs-toggle="collapse" data-bs-target="#reg-settings-collapse" href="#">
                {{$registration}}
                </a>
                </h3>
            </div>
            <div id="reg-settings-collapse" class="collapse" role="tabpanel" aria-labelledby="reg-settings" data-parent="#settings">
                <div class="section-content-tools-wrapper">
                    {{include file="field_select.tpl" field=$register_policy}}
                    {{if $invite_working}}{{include file="field_checkbox.tpl" field=$invite_only}}{{/if}}
                    {{include file="field_select.tpl" field=$access_policy}}
                    {{include file="field_input.tpl" field=$register_text}}
                    {{include file="field_select_grouped.tpl" field=$role}}
                    {{include file="field_checkbox.tpl" field=$tos_required}}
                    {{include file="field_input.tpl" field=$minimum_age}}
                    {{include file="field_input.tpl" field=$location}}
                    {{include file="field_input.tpl" field=$sellpage}}
                    {{include file="field_input.tpl" field=$first_page}}
                    <div class="settings-submit-wrapper" >
                        <button type="submit" name="page_site" class="btn btn-primary" value="1" >{{$submit}}</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="section-subtitle-wrapper" role="tab" id="corp-settings">
                <h3>
                <a data-bs-toggle="collapse" data-bs-target="#corp-settings-collapse" href="#">
                {{$corporate}}
                </a>
                </h3>
            </div>
            <div id="corp-settings-collapse" class="collapse" role="tabpanel" aria-labelledby="corp-settings" data-parent="#settings">
                <div class="section-content-tools-wrapper">
                    {{include file="field_checkbox.tpl" field=$verify_email}}
                    {{include file="field_checkbox.tpl" field=$show_like_counts}}
                    {{include file="field_checkbox.tpl" field=$ap_contacts}}
                    {{include file="field_checkbox.tpl" field=$animations}}
                    {{include file="field_checkbox.tpl" field=$force_publish}}
                    {{include file="field_select.tpl" field=$public_stream_mode}}
                    {{include file="field_checkbox.tpl" field=$open_pubstream}}
                    {{include file="field_textarea.tpl" field=$incl}}
                    {{include file="field_textarea.tpl" field=$excl}}
                    <div class="settings-submit-wrapper" >
                        <button type="submit" name="page_site" class="btn btn-primary" value="1" >{{$submit}}</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="panel">
            <div class="section-subtitle-wrapper" role="tab" id="adv-settings">
                <h3>
                <a data-bs-toggle="collapse" data-bs-target="#adv-settings-collapse" href="#">
                {{$advanced}}
                </a>
                </h3>
            </div>
            <div id="adv-settings-collapse" class="collapse" role="tabpanel" aria-labelledby="adv-settings" data-parent="#settings">
                <div class="section-content-tools-wrapper">
                    {{include file="field_input.tpl" field=$imagick_path}}
                    {{include file="field_checkbox.tpl" field=$cache_images}}
                    {{include file="field_input.tpl" field=$max_imported_follow}}
                    {{include file="field_input.tpl" field=$proxy}}
                    {{include file="field_input.tpl" field=$proxyuser}}
                    {{include file="field_input.tpl" field=$timeout}}
                    {{include file="field_input.tpl" field=$post_timeout}}
                    {{include file="field_input.tpl" field=$delivery_interval}}
                    {{include file="field_input.tpl" field=$delivery_batch_count}}
                    {{include file="field_input.tpl" field=$force_queue}}
                    {{include file="field_input.tpl" field=$poll_interval}}
                    {{include file="field_input.tpl" field=$maxloadavg}}
                    {{include file="field_input.tpl" field=$abandon_days}}
                    {{include file="field_input.tpl" field=$default_expire_days}}
                    {{include file="field_input.tpl" field=$active_expire_days}}
                    <div class="settings-submit-wrapper" >
                        <button type="submit" name="page_site" class="btn btn-primary" value="1" >{{$submit}}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </form>
</div>
