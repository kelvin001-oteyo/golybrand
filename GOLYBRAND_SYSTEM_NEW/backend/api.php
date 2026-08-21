<?php
declare(strict_types=1);
require __DIR__.'/config.php';

$a=$_GET['action'] ?? '';
$d=input();
ensure_content_tables();

try {
    switch ($a) {
        case 'session':
            $pending=$_SESSION['pending'] ?? null;
            if ($pending) {
                $pendingUser=user((string)$pending);
                if ($pendingUser && (int)$pendingUser['activated']===1) {
                    $_SESSION['user']=$pendingUser['username'];
                    unset($_SESSION['pending']);
                    out(['success'=>true,'user'=>$pendingUser,'pending'=>false]);
                }
                out([
                    'success'=>true,
                    'user'=>null,
                    'pending'=>true,
                    'pending_username'=>$pendingUser['username'] ?? $pending,
                    'payment_submitted'=>!empty($pendingUser['mpesa_code'])
                ]);
            }
            out(['success'=>true,'user'=>isset($_SESSION['user']) ? user((string)$_SESSION['user']) : null,'pending'=>false]);

        case 'register':
            $u=username($d['username'] ?? '');
            $n=trim((string)($d['fullName'] ?? ''));
            $p=trim((string)($d['phone'] ?? ''));
            $pw=(string)($d['password'] ?? '');
            $s=username($d['referral'] ?? '');

            if (!preg_match('/^[a-z0-9_]{3,30}$/',$u)) out(['success'=>false,'message'=>'Username must contain 3-30 letters, numbers or underscores.'],422);
            if ($n==='' || $p==='' || strlen($pw)<6) out(['success'=>false,'message'=>'Please complete all required fields.'],422);

            $q=db()->prepare('SELECT username FROM users WHERE username=?');
            $q->execute([$u]);
            if ($q->fetch()) out(['success'=>false,'message'=>'Username already exists.'],409);

            if ($s) {
                $q=db()->prepare('SELECT username FROM users WHERE username=? AND activated=1');
                $q->execute([$s]);
                if (!$q->fetch()) out(['success'=>false,'message'=>'Referral username does not exist or is not active.'],422);
                if ($s===$u) out(['success'=>false,'message'=>'You cannot refer yourself.'],422);
            }

            $q=db()->prepare('INSERT INTO users(id,username,full_name,phone,password_hash,sponsor,activated,registration_expense) VALUES(UUID(),?,?,?,?,?,0,1000)');
            $q->execute([$u,$n,$p,password_hash($pw,PASSWORD_DEFAULT),$s ?: null]);
            $_SESSION['pending']=$u;
            unset($_SESSION['user']);
            out(['success'=>true,'username'=>$u,'payment_submitted'=>false]);

        case 'submit_payment':
            $u=$_SESSION['pending'] ?? null;
            if (!$u) out(['success'=>false,'message'=>'No pending registration found. Please register or log in again.'],422);
            $c=strtoupper(trim((string)($d['mpesaCode'] ?? '')));
            if ($c==='') out(['success'=>false,'message'=>'Enter your payment reference.'],422);
            if (strlen($c)>60) out(['success'=>false,'message'=>'Payment reference is too long.'],422);

            $q=db()->prepare('SELECT activated FROM users WHERE username=?');
            $q->execute([$u]);
            $r=$q->fetch();
            if (!$r) out(['success'=>false,'message'=>'Registration could not be found.'],404);
            if ((int)$r['activated']===1) {
                $_SESSION['user']=$u;
                unset($_SESSION['pending']);
                out(['success'=>true,'status'=>'active']);
            }

            $q=db()->prepare('UPDATE users SET mpesa_code=? WHERE username=? AND activated=0');
            $q->execute([$c,$u]);
            out(['success'=>true,'status'=>'pending']);

        case 'login':
            $u=username($d['username'] ?? '');
            $pw=(string)($d['password'] ?? '');
            $q=db()->prepare('SELECT username,password_hash,activated,mpesa_code FROM users WHERE username=?');
            $q->execute([$u]);
            $r=$q->fetch();
            if (!$r || !password_verify($pw,$r['password_hash'])) out(['success'=>false,'message'=>'Incorrect username or password.'],401);

            if (!(int)$r['activated']) {
                $_SESSION['pending']=$u;
                unset($_SESSION['user']);
                out(['success'=>true,'status'=>'pending','payment_submitted'=>!empty($r['mpesa_code'])]);
            }

            $_SESSION['user']=$u;
            unset($_SESSION['pending']);
            out(['success'=>true,'status'=>'active','user'=>user($u)]);

        case 'cancel_pending':
            unset($_SESSION['pending']);
            out(['success'=>true]);

        case 'logout':
            $_SESSION=[];
            if (ini_get('session.use_cookies')) {
                $params=session_get_cookie_params();
                setcookie(session_name(),'','time()-42000',$params['path'],$params['domain'],$params['secure'],$params['httponly']);
            }
            session_destroy();
            out(['success'=>true]);

        case 'content':
            $u=need_user();
            $items=db()->query("SELECT id,content_type,title,description,url,sort_order FROM content_items WHERE active=1 ORDER BY content_type,sort_order ASC,id DESC")->fetchAll();
            $questions=db()->query("SELECT id,question,option_a,option_b,option_c,option_d,correct_option,explanation FROM trivia_questions WHERE active=1 ORDER BY id DESC")->fetchAll();
            out(['success'=>true,'items'=>$items,'trivia'=>$questions]);

        case 'team':
            $u=need_user();
            $all=db()->query('SELECT username,full_name,phone,sponsor,activated FROM users WHERE activated=1')->fetchAll();
            $l1=array_values(array_filter($all,fn($x)=>$x['sponsor']===$u));
            $s1=array_column($l1,'username');
            $l2=array_values(array_filter($all,fn($x)=>in_array($x['sponsor'],$s1,true)));
            $s2=array_column($l2,'username');
            $l3=array_values(array_filter($all,fn($x)=>in_array($x['sponsor'],$s2,true)));
            out(['success'=>true,'level1'=>$l1,'level2'=>$l2,'level3'=>$l3,'balance'=>calculate_balance($u)]);

        case 'balance':
            $u=need_user();
            out(['success'=>true,'balance'=>calculate_balance($u)]);

        case 'withdrawals':
            $u=need_user();
            $q=db()->prepare('SELECT id,phone,amount,status,created_at FROM withdrawals WHERE username=? ORDER BY created_at DESC');
            $q->execute([$u]);
            out(['success'=>true,'withdrawals'=>$q->fetchAll(),'balance'=>calculate_balance($u)]);

        case 'withdraw':
            $u=need_user();
            $phone=trim((string)($d['phone'] ?? ''));
            $amt=(float)($d['amount'] ?? 0);
            if ($phone==='') out(['success'=>false,'message'=>'Enter the M-Pesa phone number.'],422);
            if ($amt<500) out(['success'=>false,'message'=>'Minimum withdrawal is Ksh 500.'],422);
            if (floor($amt)!==$amt) out(['success'=>false,'message'=>'Withdrawal amount must be a whole number.'],422);

            $pdo=db();
            $pdo->beginTransaction();
            try {
                $q=$pdo->prepare('SELECT username FROM users WHERE username=? AND activated=1 FOR UPDATE');
                $q->execute([$u]);
                if (!$q->fetch()) throw new RuntimeException('Account is not active.');

                $balance=calculate_balance($u);
                if ($amt>$balance['available']) throw new RuntimeException('Insufficient available balance.');

                $q=$pdo->prepare("INSERT INTO withdrawals(id,username,phone,amount,status) VALUES(UUID(),?,?,?,'pending')");
                $q->execute([$u,$phone,$amt]);
                $pdo->commit();
                out(['success'=>true,'message'=>'Withdrawal request submitted successfully.']);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                out(['success'=>false,'message'=>$e->getMessage()],422);
            }

        default:
            out(['success'=>false,'message'=>'Unknown action.'],404);
    }
} catch (Throwable $e) {
    error_log('GOLYBRAND API error: '.$e->getMessage());
    out(['success'=>false,'message'=>'Server error. Check MySQL and PHP configuration.'],500);
}
