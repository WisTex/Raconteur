
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
                <div class="float-start"><a href="{{$identity.1}}" >{{$identity.0}}</a> {{if $identity.2}}verified{{else}}not verified{{/if}}</div>
                <div class="float-end"><a href="settings/identities/{{$key}}"><i class="fa fa-pencil"></i></a></div>
                <div class="clear"></div>
            </div>
        {{/foreach}}
        </div>
    {{/if}}
    <br>
    <div>
    <form action="settings/identities" method="POST">
        {{include file="field_input.tpl" field=$description}}
        {{include file="field_input.tpl" field=$url}}
        <div class="settings-submit-wrapper" >
            <button type="submit" name="submit" class="btn btn-primary">{{$submit}}</button>
        </div>
    </form>
    </div>
</div>
