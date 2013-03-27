<?php

/*
	This is Textpattern

	Copyright 2004 by Dean Allen
	www.textpattern.com
	All rights reserved

	Use of this software indicates acceptance of the Textpattern license agreement 
*/

	check_privs(1,2,3);

	if(!$step or !function_exists($step)){
		discuss_list();
	} else $step();

//-------------------------------------------------------------
	function discuss_delete()
	{
		$discussid = ps('discussid');
		safe_delete("txp_discuss","discussid = $discussid");	
		discuss_list(messenger('message',$discussid,'deleted'));
	}

//-------------------------------------------------------------
	function discuss_save()
	{
		extract(doSlash(gpsa(array('email','name','web','message','discussid','ip','visible'))));
		safe_update("txp_discuss",
			"email   = '$email',
			 name    = '$name',
			 web     = '$web',
			 message = '$message',
			 visible = '$visible'",
			"discussid = $discussid");
		discuss_list(messenger('message',$discussid,'updated'));
	}

//-------------------------------------------------------------
	function discuss_list($message='') 
	{
		global $timeoffset;
		pagetop(gTxt('list_discussions'),$message);

		extract(doSlash(gpsa(array('page','crit'))));

		extract(get_prefs());

		$total = safe_count('txp_discuss',"1");  
		$limit = $comment_list_pageby;
		$numPages = ceil($total/$limit);  
		$page = (!$page) ? 1 : $page;
		$offset = ($page - 1) * $limit;

		$nav[] = ($page > 1)
		?	PrevNextLink("discuss",$page-1,gTxt('prev'),'prev') : '';

		$nav[] = sp.small($page. '/'.$numPages).sp;

		$nav[] = ($page != $numPages) 
		?	PrevNextLink("discuss",$page+1,gTxt('next'),'next') : '';

		$criteria = ($crit) ? "message like '%$crit%'" : '1'; 

		$rs = safe_rows(
			"*, unix_timestamp(posted) as uPosted", 
			"txp_discuss",
			"$criteria order by posted desc limit $offset,$limit"
		);

		echo pageby_form('discuss',$comment_list_pageby);

		if($rs) {	

			echo '<form action="index.php" method="post" onsubmit="return verify(\''.gTxt('are_you_sure').'\')">',

			startTable('list'),
			assHead('date','name','message','parent','');
	
			foreach ($rs as $a) {
				extract($a);
				$dmessage = $message;
				$name = (!$visible) ? '<span style="color:red">'.$name.'</span>' : $name;
				$date = "".date("M d, g:ia",($uPosted + $timeoffset))."";
				$editlink = eLink('discuss','discuss_edit','discussid',$discussid,$date);
				$cbox = fInput('checkbox','selected[]',$discussid);
	
				$tq = fetch('Title','textpattern','ID',$parentid);
				$parent = (!$tq) ? gTxt('article_deleted') : $tq;
									
				echo assRow(array(
					$editlink   => 100,
					$name       => 100,
					$dmessage   => 250,
					$parent     => 100,
					$cbox       => 20
				));
			}
			
			echo tr(tda(discuss_multiedit_form(),' colspan="5" style="text-align:right;border:0px"'));
			
			echo endTable().'</form>';

			echo startTable('edit'),
				tr(
					tda(
					graf('<a href="index.php?event=discuss'.a.'step=ipban_list">'.
						gTxt('list_banned_ips').'</a>'),' valign="middle"'
					).
					td(
						form(
							fInput('text','crit','','edit').
							fInput('submit','search',gTxt('search'),'smallbox').
							eInput("discuss").
							sInput("list")
						)
					).
					tdcs(graf(join('',$nav)),2))
				,endTable();

		} else echo graf(gTxt('no_comments_recorded'), ' align="center"');
	}

//-------------------------------------------------------------
	function discuss_edit()
	{
		$discussid=gps('discussid');
		extract(safe_row("*", "txp_discuss", "discussid='$discussid'"));
		$ta = '<textarea name="message" cols="60" rows="15">'.
			htmlspecialchars($message).'</textarea>';

		if (fetch('ip','txp_discuss_ipban','ip',$ip)) {
			$banstep = 'ipban_unban'; $bantext = gTxt('unban');
		} else {
			$banstep = 'ipban_add'; $bantext = gTxt('ban');
		}
		
		$banlink = '[<a href="?event=discuss'.a.'step='.$banstep.a.'ip='.$ip.a.
			'name='.urlencode($name).a.'discussid='.$discussid.'">'.$bantext.'</a>]';
	
		pagetop(gTxt('edit_comment'));
		echo 
			form(
			startTable('edit').
				stackRows(
					fLabelCell('name') . fInputCell('name',$name),
					fLabelCell('email') . fInputCell('email',$email),
					fLabelCell('website') . fInputCell('web',$web),
					td() . td($ta),
					fLabelCell('visible') . td(checkbox('visible', 1,$visible)),
					fLabelCell('IP') . td($ip.sp.$banlink),
					td() . td(fInput('submit','step',gTxt('save'),'publish')),
				hInput("discussid", $discussid).hInput('ip',$ip).
				eInput('discuss').sInput('discuss_save')
			).
			endTable()
		);
	}

// -------------------------------------------------------------
	function ipban_add() 
	{
		extract(doSlash(gpsa(array('ip','name','discussid'))));
		
		if (!$ip) exit(ipban_list(gTxt("cant_ban_blank_ip")));
		
		$chk = fetch('ip','txp_discuss_ipban','ip',$ip);
		
		if (!$chk) {
			$rs = safe_insert("txp_discuss_ipban",
				"ip = '$ip',
				 name_used = '$name',
				 banned_on_message = '$discussid',
				 date_banned = now()
			");
			if ($rs) ipban_list(messenger('ip',$ip,'banned'));
		} else ipban_list(messenger('ip',$ip,'already_banned'));
		
	}

// -------------------------------------------------------------
	function ipban_unban() 
	{
		$ip = ps('ip');
		
		$rs = safe_delete("txp_discuss_ipban","ip='$ip'");
		
		if($rs) ipban_list(messenger('ip',$ip,'unbanned'));
	}

// -------------------------------------------------------------
	function ipban_list($message='')
	{
		pageTop(gTxt('list_banned_ips'),$message);
		$rs = safe_rows(
				"*", 
				"txp_discuss_ipban", 
				"1 order by date_banned desc"
			);
		if ($rs) {
			echo startTable('list'),
			tr(
				hCell('Date banned') .
				hCell('ip')          .
				hCell('Name used')   .
				hCell('Banned for')  .
				td()
			);

			foreach($rs as $a) {
				extract($a);
				
				$unbanlink = '<a href="?event=discuss'.a.'step=ipban_unban'.a.
					'ip='.$ip.'">unban</a>';
				$datebanned = date("Y-m-d",strtotime($date_banned));
				$messagelink = '<a href="?event=discuss'.a.'step=discuss_edit'.a.'discussid='.$banned_on_message.'">'.$banned_on_message.'</a>';
				echo
				tr(
					td($datebanned)  .
					td($ip)          .
					td($name_used)   .
					td($messagelink) .
					td($unbanlink)
				);
				
			}
			echo endTable();
		} else echo graf(gTxt('no_ips_banned'),' align="center"');
	}

// -------------------------------------------------------------
	function discuss_change_pageby() 
	{
		$qty = gps('qty');
		safe_update('txp_prefs',"val=$qty","name='comment_list_pageby'");
		discuss_list();
	}

// -------------------------------------------------------------
	function discuss_multiedit_form() 
	{
		$method = ps('method');
		$methods = array('delete'=>'delete');
		return
			gTxt('with_selected').sp.selectInput('method',$methods,$method,1).
			eInput('discuss').sInput('discuss_multi_edit').fInput('submit','',gTxt('go'),'smallerbox');
	}

// -------------------------------------------------------------
	function discuss_multi_edit() 
	{
		$method = ps('method');
		$things = ps('selected');
		if ($things) {
			if ($method == 'delete') {
				foreach($things as $discussid) {
					if (safe_delete('txp_discuss',"discussid='$discussid'")) {
						$ids[] = $discussid;
					}
				}
				discuss_list(messenger('comment',join(', ',$ids),'deleted'));
			} else discuss_list();
		} else discuss_list();
	}


?>
