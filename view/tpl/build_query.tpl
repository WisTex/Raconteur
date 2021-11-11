<script> 

	let bParam_cmd = "{{$baseurl}}/update/{{$pgtype}}";


	let bParam_uid = {{$uid}};
	// lists can be either type string (virtual lists) or integer (normal accesslists)
	let bParam_gid = "{{$gid}}";
	let bParam_cid = {{$cid}};
	let bParam_cmin = {{$cmin}};
	let bParam_cmax = {{$cmax}};
	let bParam_star = {{$star}};
	let bParam_liked = {{$liked}};
	let bParam_conv = {{$conv}};
	let bParam_spam = {{$spam}};
	let bParam_new = {{$nouveau}};
	let bParam_page = {{$page}};
	let bParam_wall = {{$wall}};
	let bParam_draft = {{$draft}};
	let bParam_list = {{$list}};
	let bParam_fh = {{$fh}};
	let bParam_dm = {{$dm}};
	let bParam_static = {{$static}};

	let bParam_search = "{{$search}}";
	let bParam_xchan = "{{$xchan}}";
	let bParam_order = "{{$order}}";
	let bParam_file = "{{$file}}";
	let bParam_cats = "{{$cats}}";
	let bParam_tags = "{{$tags}}";
	let bParam_dend = "{{$dend}}";
	let bParam_dbegin = "{{$dbegin}}";
	let bParam_mid = "{{$mid}}";
	let bParam_verb = "{{$verb}}";
	let bParam_net = "{{$net}}";
	let bParam_pf = "{{$pf}}";

	function buildCmd() {
		let udargs = ((page_load) ? "/load" : "");
		let bCmd = bParam_cmd + udargs + "?f=" ;
		if(bParam_uid) bCmd = bCmd + "&p=" + bParam_uid;
		if(bParam_cmin != (-1)) bCmd = bCmd + "&cmin=" + bParam_cmin;
		if(bParam_cmax != (-1)) bCmd = bCmd + "&cmax=" + bParam_cmax;
		if(bParam_gid != 0) { bCmd = bCmd + "&gid=" + bParam_gid; } else
		if(bParam_cid != 0) { bCmd = bCmd + "&cid=" + bParam_cid; }
		if(bParam_static != 0) { bCmd = bCmd + "&static=" + bParam_static; }
		if(bParam_star != 0) bCmd = bCmd + "&star=" + bParam_star;
		if(bParam_liked != 0) bCmd = bCmd + "&liked=" + bParam_liked;
		if(bParam_conv!= 0) bCmd = bCmd + "&conv=" + bParam_conv;
		if(bParam_spam != 0) bCmd = bCmd + "&spam=" + bParam_spam;
		if(bParam_new != 0) bCmd = bCmd + "&new=" + bParam_new;
		if(bParam_wall != 0) bCmd = bCmd + "&wall=" + bParam_wall;
		if(bParam_draft != 0) bCmd = bCmd + "&draft=" + bParam_draft;
		if(bParam_list != 0) bCmd = bCmd + "&list=" + bParam_list;
		if(bParam_fh != 0) bCmd = bCmd + "&fh=" + bParam_fh;
		if(bParam_dm != 0) bCmd = bCmd + "&dm=" + bParam_dm;
		if(bParam_search != "") bCmd = bCmd + "&search=" + bParam_search;
		if(bParam_xchan != "") bCmd = bCmd + "&xchan=" + bParam_xchan;
		if(bParam_order != "") bCmd = bCmd + "&order=" + bParam_order;
		if(bParam_file != "") bCmd = bCmd + "&file=" + bParam_file;
		if(bParam_cats != "") bCmd = bCmd + "&cat=" + bParam_cats;
		if(bParam_tags != "") bCmd = bCmd + "&tag=" + bParam_tags;
		if(bParam_dend != "") bCmd = bCmd + "&dend=" + bParam_dend;
		if(bParam_dbegin != "") bCmd = bCmd + "&dbegin=" + bParam_dbegin;
		if(bParam_mid != "") bCmd = bCmd + "&mid=" + bParam_mid;
		if(bParam_verb != "") bCmd = bCmd + "&verb=" + bParam_verb;
		if(bParam_net != "") bCmd = bCmd + "&net=" + bParam_net;
		if(bParam_page != 1) bCmd = bCmd + "&page=" + bParam_page;
		if(bParam_pf != 0) bCmd = bCmd + "&pf=" + bParam_pf;
		return(bCmd);
	}

</script>

