
<div class="generic-content-wrapper">
    <div class="section-title-wrapper">
        <h2>
            {{$title}}
        </h2>
    </div>
    <div>
    {{if $identities}}
        <div class="section-content-tools-wrapper">
        {{foreach $identities as $key => $identity}}
            <div class="">
                <div class="float-start" ><a href="{{$identity.1}}" >{{$identity.0}}</a> <i class="fa {{if $identity.2}}_green fa-check-square-o{{else}}_red fa-square-o{{/if}}"></i></div>
                <div class="float-end" ><a href="settings/identities/{{$key}}?drop=1" class="btn btn-default btn-outline-secondary"><i class="fa fa-remove" title="{{$drop}}"></i></a></div>
                <div class="float-end" ><a href="settings/identities/{{$key}}" class="btn btn-default btn-outline-secondary" style="margin-right:5px;"><i class="fa fa-pencil" title="{{$edit}}"></i></a></div>
                <div class="clear" ></div>
            </div>
        {{/foreach}}
        </div>
    {{/if}}
    <div class="section-content-tools-wrapper">
    <form action="settings/identities" method="POST">
        {{include file="field_input.tpl" field=$description}}
        {{include file="field_input.tpl" field=$url}}
        <div class="settings-submit-wrapper" >
            <button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
        </div>
    </form>
    </div>
</div>
