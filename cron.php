<?php
// Run every minute:  * * * * * php /path/to/cron.php
require __DIR__.'/lib.php';
foreach(rows("select * from routers where active=1") as $r){$a=new RouterOS;$ok=$a->connect($r['host'],$r['port'],$r['username'],$r['password']);
 q('update routers set status=?,last_seen=? where id=?',[$ok?'online':'offline',$ok?now():$r['last_seen'],$r['id']]);
 if($ok){foreach($a->cmd(['/ip/hotspot/user/print']) as $u)if(isset($u['name']))q('update sessions set bytes=? where username=? and status=\'active\'',[(int)($u['bytes-in']??0)+(int)($u['bytes-out']??0),$u['name']]);
  foreach($a->cmd(['/ip/hotspot/active/print']) as $u)if(isset($u['user']))q('update sessions set mac=?,ip=? where username=? and status=\'active\'',[$u['mac-address']??'',$u['address']??'',$u['user']]);$a->close();}}
expire_sessions();
echo "done\n";
