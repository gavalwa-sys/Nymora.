<?php
require __DIR__.'/lib.php';
foreach(['mac','ip','router'] as $k)if(isset($_GET[$k]))$_SESSION['h_'.$k]=$_GET[$k];
if(isset($_GET['link-login-only']))$_SESSION['h_login']=$_GET['link-login-only'];
$mac=$_SESSION['h_mac']??'';$ip=$_SESSION['h_ip']??'';$rid=(int)($_SESSION['h_router']??0)?:null;
$loginUrl=$_SESSION['h_login']??setting('login_url');
$c=setting('color','#0ea5e9');$biz=setting('biz_name');$msg='';$view='home';
function go($sid){global $loginUrl;$s=row('select * from sessions where id=?',[$sid]);
 echo "<!doctype html><meta name=viewport content='width=device-width,initial-scale=1'><body style='font:16px system-ui;text-align:center;padding:40px'><h3>✅ You're connected</h3><p>Redirecting…</p><form id=f method=post action='".e($loginUrl)."'><input type=hidden name=username value='".e($s['username'])."'><input type=hidden name=password value='".e($s['password'])."'><input type=hidden name=dst value='http://www.google.com'><noscript><button>Continue</button></noscript></form><script>setTimeout(()=>f.submit(),800)</script>";exit;}
function page($body){global $c,$biz;$logo=setting('logo');
 echo "<!doctype html><html><head><meta charset=utf-8><meta name=viewport content='width=device-width,initial-scale=1'><title>".e($biz)."</title><style>
*{box-sizing:border-box}body{margin:0;font:16px system-ui,sans-serif;background:linear-gradient(160deg,$c,#0f172a);min-height:100vh;color:#0f172a}.w{max-width:460px;margin:0 auto;padding:18px}
header{text-align:center;color:#fff;padding:14px 0}header img{max-height:70px;border-radius:12px}h1{margin:6px 0;font-size:24px}header p{margin:0;opacity:.9}
.card{background:#fff;border-radius:16px;padding:16px;margin-bottom:12px}.pk{display:flex;justify-content:space-between;align-items:center;padding:12px;border:2px solid #e2e8f0;border-radius:12px;margin-bottom:8px;gap:10px}
.pk b{font-size:17px}.pk small{color:#64748b}.pr{font-size:18px;font-weight:700;color:$c}input{width:100%;padding:13px;border:1px solid #cbd5e1;border-radius:10px;font:inherit;margin:5px 0}
button{background:$c;color:#fff;border:0;border-radius:10px;padding:12px 18px;font:inherit;cursor:pointer;width:100%}button.s{width:auto;padding:9px 16px}.e{background:#fee2e2;color:#991b1b;padding:10px;border-radius:10px;margin-bottom:10px}.i{background:#e0f2fe;padding:10px;border-radius:10px}a{color:$c}
</style></head><body><div class=w><header>".($logo?"<img src='".e($logo)."'>":'<div style=font-size:44px>📡</div>')."<h1>".e($biz)."</h1><p>".e(setting('welcome'))."</p></header>$body<p style='text-align:center;color:#cbd5e1;font-size:12px'>Powered by Nymora</p></div></body></html>";exit;}
// --- payment status screen
if(isset($_GET['pay'])){$p=row('select * from payments where id=? and mac=?',[(int)$_GET['pay'],$mac]);if(!$p)page('<div class=card>Payment not found. <a href=portal.php>Back</a></div>');
 if($p['status']=='paid')go($p['session_id']);
 page("<div class=card><meta http-equiv=refresh content=5><h3>Waiting for payment</h3><p>Reference: <b style='font-size:20px'>".e($p['ref'])."</b></p><p>Amount: <b>".money($p['amount'])."</b></p><div class=i>".nl2br(e(setting('pay_info')))."</div><p style='color:#64748b'>This page refreshes automatically. You'll be connected as soon as payment is confirmed.</p><a href=portal.php>Cancel</a></div>");}
if($_SERVER['REQUEST_METHOD']=='POST'){csrf_check();$a=$_POST['a']??'';
 if($a=='voucher'){$code=strtoupper(trim($_POST['code']));$v=row("select * from vouchers where code=? and status='new'",[$code]);
  if(!$v)$msg='Invalid or already used voucher code';else{$pk=row('select * from packages where id=?',[$v['package_id']]);
   $sid=activate($pk,$code,null,$v['id'],$mac,$ip,$rid);q("update vouchers set status='used',used_at=? where id=?",[now(),$v['id']]);
   q("insert into payments(customer_id,package_id,amount,method,ref,status,created,paid_at,session_id,mac,router_id) values(null,?,?,?,?,?,?,?,?,?,?)",[$pk['id'],$pk['price'],'voucher','V'.$code,'paid',now(),now(),$sid,$mac,$rid]);go($sid);}}
 if($a=='login'||$a=='buy'){$ph=preg_replace('/[^0-9+]/','',$_POST['phone']);$pin=$_POST['pin'];
  if(strlen($ph)<7||strlen($pin)<4)$msg='Enter your phone number and a PIN of at least 4 digits';
  else{$cu=row('select * from customers where phone=?',[$ph]);
   if(!$cu&&$a=='login')$msg='No account for that number. Buy a package to register automatically.';
   elseif($cu&&!password_verify($pin,$cu['pin_hash']))$msg='Wrong PIN for this number';
   elseif($cu&&$cu['status']!='active')$msg='This account is suspended. Please contact the operator.';
   else{if(!$cu){q('insert into customers(name,phone,pin_hash,created) values(?,?,?,?)',[trim($_POST['name']??''),$ph,password_hash($pin,PASSWORD_DEFAULT),now()]);$cu=row('select * from customers where phone=?',[$ph]);}
    if($a=='login'){$s=row("select id from sessions where customer_id=? and status='active' and expires>? order by id desc",[$cu['id'],now()]);if($s)go($s['id']);$msg='No active package. Choose one below.';}
    else{$pk=row('select * from packages where id=? and active=1',[(int)$_POST['package_id']]);
     if($pk){$pid=new_payment($cu['id'],$pk,'mobile money',$mac,$rid);if(setting('auto_confirm')=='1')complete_payment($pid);header('Location: portal.php?pay='.$pid);exit;}}}}}
}
$b=$msg?"<div class=e>".e($msg)."</div>":'';
$b.="<div class=card><h3 style=margin-top:0>🎟 Have a voucher?</h3><form method=post><input type=hidden name=csrf value='".csrf()."'><input type=hidden name=a value=voucher><input name=code placeholder='Enter voucher code' autocapitalize=characters required><button>Connect</button></form></div>";
$b.="<div class=card><h3 style=margin-top:0>📶 Choose a package</h3><form method=post><input type=hidden name=csrf value='".csrf()."'><input type=hidden name=a value=buy>";
foreach(rows('select * from packages where active=1 order by price') as $i=>$k)$b.="<label class=pk><span><input type=radio name=package_id value={$k['id']} ".($i?'':'checked')." style='width:auto;margin:0 8px 0 0'><b>".e($k['name'])."</b><br><small>".dur($k['minutes']).($k['speed']?' · '.e($k['speed']):'').($k['data_mb']?' · '.$k['data_mb'].'MB':'')."</small></span><span class=pr>".money($k['price'])."</span></label>";
$b.="<input name=name placeholder='Your name (optional)'><input name=phone type=tel placeholder='Phone number' required><input name=pin type=password inputmode=numeric placeholder='Create / enter 4+ digit PIN' required><button>Buy &amp; connect</button><small style=color:#64748b>New here? You're registered automatically with your first purchase.</small></form></div>";
$b.="<div class=card><h3 style=margin-top:0>🔑 Already bought?</h3><form method=post><input type=hidden name=csrf value='".csrf()."'><input type=hidden name=a value=login><input name=phone type=tel placeholder='Phone number' required><input name=pin type=password inputmode=numeric placeholder=PIN required><button class=s>Log in</button></form></div>";
page($b);
