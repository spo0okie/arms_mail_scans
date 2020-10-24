#!/usr/bin/php -q
<?php
date_default_timezone_set('Asia/Yekaterinburg');

//подгружаем либу
include_once 'php-imap.php';

//настройки
include_once 'settings.php';

//цепляем папку входящие в ящике
$mailbox = new Mailbox($imap_account['imap_string'], $imap_account['user'], $imap_account['pass'], $workdir);

//обходим там все письма
$mailsIds = $mailbox->searchMailBox('ALL');
if(!$mailsIds) {
	msg('Mailbox is empty');	//если они там есть конечно
} else {
	foreach ($mailsIds as $id) {
		$mail = $mailbox->getMail($id); //в этот момент письмо загружается в переменную, а вложения в $workdir
		$mailbox->deleteMail($id); //удаляем письмо из ящика

		$attachments=scandir($workdir);
		foreach ($attachments as $attach) if (array_search($attach,['.','..'])===false){
			msg ("Got $attach to upload");
			msg ('Creating new contract object ...');
			$curl=curl_init();
			curl_setopt($curl, CURLOPT_URL, $inventory_url.'api/contracts/create'); //новый док-т
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curl, CURLOPT_POST, 1);
			curl_setopt($curl, CURLOPT_POSTFIELDS, [
				'name'=>"Входящий документ от ".date('Y-m-d H:i:s'),
				'date'=>date('Y-m-d')
			]);
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
			$output = curl_exec($curl);
			if ($output === FALSE) {
				msg( "cURL Error: " . curl_error($curl));
				curl_close($curl);
				continue;
			} else {
				curl_close($curl);

				msg("Parsing :$output ...");
				if (is_array($model=json_decode($output,true))) {
					if (isset($model['id']) && ($contracts_id=$model['id'])) {
						msg ("Attaching file $workdir/$attach to contract #$contracts_id ...");
						$curl=curl_init();
						curl_setopt($curl, CURLOPT_URL, $inventory_url.'api/scans/upload'); //новый скан
						curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($curl, CURLOPT_POST, 1);
						//curl_setopt($curl, CURLOPT_VERBOSE, true);
						curl_setopt($curl, CURLOPT_POSTFIELDS, [
							'contracts_id'=>$contracts_id,
							'scanFile'=>new CURLFile("$workdir/$attach")
						]);
						curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
						$output = curl_exec($curl);
						if ($output === FALSE) {
							msg( "cURL Error: " . curl_error($curl));
						} else {
							msg("Complete: $output");
							unlink("$workdir/$attach");
						}
						curl_close($curl);
					} else msg ("Can not determine new contract id");
				} else msg ("JSON parse error");
			}
		}
	}
}
