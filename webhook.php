<?php
// Payment gateway callback: POST ref=NYMXXXXXX&status=success (form or JSON) to webhook.php?key=YOUR_KEY
require __DIR__.'/lib.php';
if(!hash_equals(setting('webhook_key'),$_GET['key']??'')){http_response_code(403);exit('forbidden');}
$d=$_POST?:(json_decode(file_get_contents('php://input'),true)?:[]);
$p=row('select * from payments where ref=?',[$d['ref']??'']);
if($p&&in_array(strtolower($d['status']??''),['success','paid','successful','completed'])&&$p['status']=='pending'){complete_payment($p['id']);echo 'ok';}else echo 'ignored';
