<?php
// Nymora Hotspot - core library
date_default_timezone_set('Africa/Lusaka');
session_start();
class Raw{function __construct(public $h){}function __toString(){return (string)$this->h;}}
function raw($h){return new Raw($h);}
function db(){static $p;if($p)return $p;@mkdir(__DIR__.'/data',0775,true);
 $p=new PDO('sqlite:'.__DIR__.'/data/nymora.sqlite');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);schema($p);return $p;}
function q($s,$a=[]){$t=db()->prepare($s);$t->execute($a);return $t;}
function rows($s,$a=[]){return q($s,$a)->fetchAll();}
function row($s,$a=[]){return q($s,$a)->fetch();}
function val($s,$a=[]){$r=q($s,$a)->fetch(PDO::FETCH_NUM);return $r?$r[0]:null;}
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function now(){return date('Y-m-d H:i:s');}
function setting($k,$d=''){$v=val('select v from settings where k=?',[$k]);return $v===null?$d:$v;}
function setset($k,$v){q('insert into settings(k,v) values(?,?) on conflict(k) do update set v=excluded.v',[$k,$v]);}
function money($n){return e(setting('currency','K')).' '.number_format((float)$n,2);}
function fmtb($b){$u=['B','KB','MB','GB','TB'];$i=0;$b=(float)$b;while($b>=1024&&$i<4){$b/=1024;$i++;}return round($b,1).' '.$u[$i];}
function dur($m){if($m%10080==0)return ($m/10080).' week(s)';if($m%1440==0)return ($m/1440).' day(s)';if($m%60==0)return ($m/60).' hour(s)';return $m.' min';}
function left($t){$s=strtotime($t)-time();if($s<=0)return 'expired';$d=floor($s/86400);$h=floor($s%86400/3600);$m=floor($s%3600/60);return ($d?$d.'d ':'').$h.'h '.$m.'m';}
function csrf(){return $_SESSION['csrf']??($_SESSION['csrf']=bin2hex(random_bytes(16)));}
function csrf_check(){if(!hash_equals(csrf(),$_POST['csrf']??''))die('Bad request (CSRF)');}
function log_act($a,$d=''){q('insert into activity(staff,action,detail,created) values(?,?,?,?)',[$_SESSION['staff']['username']??'system',$a,$d,now()]);}
function schema($p){
$p->exec("
create table if not exists settings(k text primary key,v text);
create table if not exists locations(id integer primary key,name text not null);
create table if not exists routers(id integer primary key,name text,location_id integer,host text,port integer default 8728,username text,password text,active integer default 1,status text default 'unknown',last_seen text);
create table if not exists packages(id integer primary key,name text not null,price real,minutes integer,profile text default 'default',speed text,data_mb integer default 0,active integer default 1);
create table if not exists customers(id integer primary key,name text,phone text unique,pin_hash text,status text default 'active',created text);
create table if not exists vouchers(id integer primary key,code text unique,package_id integer,batch text,status text default 'new',created text,used_at text,created_by text);
create table if not exists sessions(id integer primary key,customer_id integer,voucher_id integer,package_id integer,router_id integer,username text,password text,mac text,ip text,start text,expires text,status text default 'active',bytes integer default 0);
create table if not exists payments(id integer primary key,customer_id integer,package_id integer,amount real,method text,ref text unique,status text default 'pending',created text,paid_at text,session_id integer,mac text,router_id integer);
create table if not exists staff(id integer primary key,name text,username text unique,pass_hash text,role text default 'cashier',active integer default 1);
create table if not exists activity(id integer primary key,staff text,action text,detail text,created text);
create index if not exists i_s on sessions(status,expires);create index if not exists i_p on payments(created,status);");
if(!$p->query('select count(*) from staff')->fetchColumn()){
 $p->prepare('insert into staff(name,username,pass_hash,role) values(?,?,?,?)')->execute(['Administrator','admin',password_hash('admin123',PASSWORD_DEFAULT),'admin']);
 foreach([['1 Hour',5,60,'1M/2M'],['3 Hours',10,180,'2M/4M'],['1 Day',20,1440,'2M/5M'],['1 Week',100,10080,'3M/6M'],['1 Month',300,43200,'5M/10M']] as $x)
  $p->prepare("insert into packages(name,price,minutes,speed) values(?,?,?,?)")->execute($x);
 $p->exec("insert into locations(name) values('Main Site')");
 foreach(['biz_name'=>'Nymora Wi-Fi','currency'=>'K','color'=>'#0ea5e9','welcome'=>'Connect. Choose a package. Get online.','pay_info'=>'Send the exact amount by mobile money to 0970 000 000 and use your payment reference.','login_url'=>'http://192.168.88.1/login','auto_confirm'=>'0','default_pw'=>'1','webhook_key'=>bin2hex(random_bytes(12)),'dns_name'=>'wifi.local'] as $k=>$v)
  $p->prepare('insert into settings values(?,?)')->execute([$k,$v]);}
}
// ---- permissions
function can($page){$r=$_SESSION['staff']['role']??'';if($r=='admin')return true;
 $m=['manager'=>['dash','packages','vouchers','sessions','customers','payments','reports','routers','activity'],'cashier'=>['dash','vouchers','sessions','customers','payments']];
 return in_array($page,$m[$r]??[]);}
// ---- MikroTik RouterOS API client
class RouterOS{private $s;
 function connect($h,$p,$u,$pw){$this->s=@fsockopen($h,$p,$en,$es,3);if(!$this->s)return false;stream_set_timeout($this->s,4);
  $r=$this->cmd(['/login','=name='.$u,'=password='.$pw]);return $r&&end($r)['type']=='!done';}
 private function w($w){$l=strlen($w);$o=$l<0x80?chr($l):($l<0x4000?chr(($l>>8)|0x80).chr($l&255):chr(($l>>16)|0xC0).chr(($l>>8)&255).chr($l&255));fwrite($this->s,$o.$w);}
 private function rl(){$c=fread($this->s,1);if($c===''||$c===false)return -1;$c=ord($c);if($c<0x80)return $c;
  if(($c&0xC0)==0x80)return (($c&0x3F)<<8)+ord(fread($this->s,1));$b=fread($this->s,2);return (($c&0x1F)<<16)+(ord($b[0])<<8)+ord($b[1]);}
 function cmd($words){foreach($words as $x)$this->w($x);$this->w('');$out=[];$cur=[];
  while(true){$len=$this->rl();if($len<0)return $out;$w='';while(strlen($w)<$len){$d=fread($this->s,$len-strlen($w));if($d===''||$d===false)return $out;$w.=$d;}
   if($w===''){if($cur){$out[]=$cur;if(in_array($cur['type'],['!done','!trap','!fatal']))return $out;}$cur=[];continue;}
   if($w[0]=='!')$cur['type']=$w;elseif($w[0]=='='){$kv=explode('=',substr($w,1),2);$cur[$kv[0]]=$kv[1]??'';}}}
 function close(){if($this->s)fclose($this->s);}}
function router_do($rid,$op,$u,$pw='',$pkg=null){
 $rs=$rid?rows('select * from routers where id=? and active=1',[$rid]):rows('select * from routers where active=1');
 foreach($rs as $r){$a=new RouterOS;if(!$a->connect($r['host'],$r['port'],$r['username'],$r['password']))continue;
  if($op=='add')$a->cmd(['/ip/hotspot/user/add','=name='.$u,'=password='.$pw,'=profile='.(($pkg['profile']??'')?:'default'),'=comment=nymora']);
  else{foreach($a->cmd(['/ip/hotspot/active/print','?user='.$u]) as $x)if(isset($x['.id']))$a->cmd(['/ip/hotspot/active/remove','=.id='.$x['.id']]);
   foreach($a->cmd(['/ip/hotspot/user/print','?name='.$u]) as $x)if(isset($x['.id']))$a->cmd(['/ip/hotspot/user/remove','=.id='.$x['.id']]);}
  $a->close();}}
// ---- business logic
function activate($pkg,$username,$cust=null,$vid=null,$mac='',$ip='',$router=null){
 $pw=bin2hex(random_bytes(4));$exp=date('Y-m-d H:i:s',time()+$pkg['minutes']*60);
 q("insert into sessions(customer_id,voucher_id,package_id,router_id,username,password,mac,ip,start,expires,status) values(?,?,?,?,?,?,?,?,?,?,'active')",[$cust,$vid,$pkg['id'],$router,$username,$pw,$mac,$ip,now(),$exp]);
 $sid=db()->lastInsertId();router_do($router,'add',$username.'-'.$sid,$pw,$pkg);
 q('update sessions set username=? where id=?',[$username.'-'.$sid,$sid]);return $sid;}
function end_session($sid,$status){$s=row('select * from sessions where id=?',[$sid]);if(!$s)return;
 q('update sessions set status=? where id=?',[$status,$sid]);router_do($s['router_id'],'remove',$s['username']);}
function resume_session($sid){$s=row('select s.*,p.profile from sessions s join packages p on p.id=s.package_id where s.id=?',[$sid]);
 if($s&&$s['status']=='suspended'&&strtotime($s['expires'])>time()){q("update sessions set status='active' where id=?",[$sid]);router_do($s['router_id'],'add',$s['username'],$s['password'],$s);}}
function new_payment($cust,$pkg,$method,$mac,$router){$ref='NYM'.strtoupper(bin2hex(random_bytes(3)));
 q('insert into payments(customer_id,package_id,amount,method,ref,status,created,mac,router_id) values(?,?,?,?,?,?,?,?,?)',[$cust,$pkg['id'],$pkg['price'],$method,$ref,'pending',now(),$mac,$router]);return db()->lastInsertId();}
function complete_payment($id){$p=row("select * from payments where id=? and status='pending'",[$id]);if(!$p)return false;
 $pkg=row('select * from packages where id=?',[$p['package_id']]);$c=row('select * from customers where id=?',[$p['customer_id']]);
 $sid=activate($pkg,$c['phone'],$c['id'],null,$p['mac'],'',$p['router_id']);
 q("update payments set status='paid',paid_at=?,session_id=? where id=?",[now(),$sid,$id]);return $sid;}
function expire_sessions(){foreach(rows("select id from sessions where status='active' and expires<?",[now()]) as $s)end_session($s['id'],'expired');
 foreach(rows("select s.id from sessions s join packages p on p.id=s.package_id where s.status='active' and p.data_mb>0 and s.bytes>=p.data_mb*1048576") as $s)end_session($s['id'],'expired');
 q("update payments set status='expired' where status='pending' and created<?",[date('Y-m-d H:i:s',time()-86400)]);}
// ---- admin layout
function layout($title,$body,$nav=true){$c=setting('color','#0ea5e9');$n=setting('biz_name','Nymora');
 echo "<!doctype html><html><head><meta charset=utf-8><meta name=viewport content='width=device-width,initial-scale=1'><title>".e($title)." - Nymora</title><style>
:root{--c:$c;--bg:#f1f5f9;--card:#fff;--tx:#0f172a;--mu:#64748b;--bd:#e2e8f0}@media(prefers-color-scheme:dark){:root{--bg:#0b1220;--card:#131c2e;--tx:#e5edf8;--mu:#8da0bd;--bd:#22304a}}
*{box-sizing:border-box}body{margin:0;font:15px system-ui,sans-serif;background:var(--bg);color:var(--tx)}a{color:var(--c)}
.top{background:var(--c);color:#fff;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:5}.top a{color:#fff}
nav{display:flex;gap:4px;overflow-x:auto;padding:8px 12px;background:var(--card);border-bottom:1px solid var(--bd)}nav a{padding:7px 12px;border-radius:8px;text-decoration:none;color:var(--tx);white-space:nowrap}nav a.on{background:var(--c);color:#fff}
main{max-width:1150px;margin:0 auto;padding:14px}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:14px}
.card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px;margin-bottom:14px}.k b{display:block;font-size:22px;margin-top:4px}.k span{color:var(--mu);font-size:13px}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px;border-bottom:1px solid var(--bd);font-size:14px}th{color:var(--mu);font-weight:600}.sc{overflow-x:auto}
input,select,textarea{padding:9px;border:1px solid var(--bd);border-radius:8px;background:var(--bg);color:var(--tx);font:inherit;max-width:100%}
button,.btn{background:var(--c);color:#fff;border:0;border-radius:8px;padding:9px 14px;font:inherit;cursor:pointer;text-decoration:none;display:inline-block}button.s{padding:4px 9px;font-size:13px}button.d{background:#dc2626}button.g{background:#64748b}
.f{display:flex;gap:8px;flex-wrap:wrap;align-items:end}.f label{display:flex;flex-direction:column;font-size:12px;color:var(--mu);gap:3px}
.b{padding:2px 8px;border-radius:99px;font-size:12px;background:var(--bd)}.ok{background:#16a34a;color:#fff}.bad{background:#dc2626;color:#fff}.wn{background:#f59e0b;color:#fff}
.bar{height:10px;background:var(--c);border-radius:5px}.msg{background:#16a34a;color:#fff;padding:10px;border-radius:8px;margin-bottom:12px}
</style></head><body><div class=top><b>📡 ".e($n)." <small style='opacity:.8'>by Nymora</small></b><span>".e($_SESSION['staff']['name']??'')." · <a href='admin.php?logout=1'>Logout</a></span></div>";
 if($nav){global $PAGES;echo '<nav>';foreach($PAGES as $k=>$l)if(can($k))echo "<a class='".(($_GET['p']??'dash')==$k?'on':'')."' href='admin.php?p=$k'>$l</a>";echo '</nav>';}
 echo "<main>$body</main></body></html>";}
function table($h,$rs){$o="<div class=sc><table><tr>";foreach($h as $x)$o.='<th>'.e($x).'</th>';$o.='</tr>';
 foreach($rs as $r){$o.='<tr>';foreach($r as $c)$o.='<td>'.($c instanceof Raw?$c->h:e($c)).'</td>';$o.='</tr>';}return $o.(count($rs)?'':'<tr><td colspan=99 style="color:var(--mu)">Nothing here yet</td></tr>').'</table></div>';}
function pform($act,$fields,$btn='Save',$cls=''){$o="<form method=post class='f $cls'><input type=hidden name=csrf value='".csrf()."'><input type=hidden name=act value='$act'>$fields<button>$btn</button></form>";return $o;}
function mini($act,$id,$label,$cls='s',$confirm=''){return raw("<form method=post style='display:inline'><input type=hidden name=csrf value='".csrf()."'><input type=hidden name=act value='$act'><input type=hidden name=id value='$id'><button class='$cls s' ".($confirm?"onclick=\"return confirm('".e($confirm)."')\"":'').">$label</button></form> ");}
