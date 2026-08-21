<?php
declare(strict_types=1);
require __DIR__.'/config.php';

$a=$_GET['action'] ?? '';
$d=input();
ensure_content_tables();

function admin_need(): void {
    if (!isset($_SESSION['admin'])) out(['success'=>false,'message'=>'Admin login required.'],401);
}

try {
    switch ($a) {
        case 'register':
            $u=username($d['username'] ?? '');
            $p=(string)($d['password'] ?? '');
            $c=(string)($d['confirm_password'] ?? '');
            $k=(string)($d['setup_key'] ?? '');
            if (!preg_match('/^[a-z0-9_]{3,30}$/',$u)) out(['success'=>false,'message'=>'Username must be 3-30 characters and contain only letters, numbers, or underscores.'],422);
            if (strlen($p)<8) out(['success'=>false,'message'=>'Password must be at least 8 characters.'],422);
            if ($p!==$c) out(['success'=>false,'message'=>'Passwords do not match.'],422);
            $adminCount=(int)db()->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if ($adminCount>0 && !isset($_SESSION['admin'])) out(['success'=>false,'message'=>'Administrator registration is closed. An existing administrator must log in before another admin can be created.'],403);
            if (!hash_equals(ADMIN_REGISTRATION_KEY,$k)) out(['success'=>false,'message'=>'Invalid administrator registration key.'],403);
            $q=db()->prepare('SELECT id FROM admins WHERE username=?');
            $q->execute([$u]);
            if ($q->fetch()) out(['success'=>false,'message'=>'That admin username already exists.'],409);
            $q=db()->prepare('INSERT INTO admins(username,password_hash) VALUES(?,?)');
            $q->execute([$u,password_hash($p,PASSWORD_DEFAULT)]);
            out(['success'=>true,'message'=>'Administrator account created successfully.']);

        case 'login':
            $u=username($d['username'] ?? '');
            $p=(string)($d['password'] ?? '');
            $q=db()->prepare('SELECT username,password_hash FROM admins WHERE username=?');
            $q->execute([$u]);
            $r=$q->fetch();
            if (!$r || !password_verify($p,$r['password_hash'])) out(['success'=>false,'message'=>'Invalid admin credentials.'],401);
            $_SESSION['admin']=$u;
            out(['success'=>true]);

        case 'session':
            admin_need();
            out(['success'=>true,'admin'=>$_SESSION['admin']]);

        case 'logout':
            unset($_SESSION['admin']);
            out(['success'=>true]);

        case 'stats':
            admin_need();
            $t=(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $ac=(int)db()->query('SELECT COUNT(*) FROM users WHERE activated=1')->fetchColumn();
            $pe=(int)db()->query('SELECT COUNT(*) FROM users WHERE activated=0 AND mpesa_code IS NOT NULL')->fetchColumn();
            $pw=(int)db()->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
            out(['success'=>true,'total_users'=>$t,'active_users'=>$ac,'pending_users'=>$pe,'pending_withdrawals'=>$pw]);

        case 'pending':
            admin_need();
            $r=db()->query('SELECT username,full_name,phone,sponsor,mpesa_code,created_at FROM users WHERE activated=0 AND mpesa_code IS NOT NULL ORDER BY created_at ASC')->fetchAll();
            out(['success'=>true,'users'=>$r]);

        case 'users':
            admin_need();
            $r=db()->query('SELECT username,full_name,phone,sponsor,mpesa_code,activated,created_at,activated_at FROM users ORDER BY created_at DESC')->fetchAll();
            out(['success'=>true,'users'=>$r]);

        case 'approve':
            admin_need();
            $u=username($d['username'] ?? '');
            $q=db()->prepare('UPDATE users SET activated=1,activated_at=NOW() WHERE username=? AND activated=0 AND mpesa_code IS NOT NULL');
            $q->execute([$u]);
            if (!$q->rowCount()) out(['success'=>false,'message'=>'User is not pending or has no payment reference.'],422);
            out(['success'=>true,'message'=>'User activated successfully.']);

        case 'reject':
            admin_need();
            $u=username($d['username'] ?? '');
            $q=db()->prepare('UPDATE users SET mpesa_code=NULL WHERE username=? AND activated=0');
            $q->execute([$u]);
            out(['success'=>true,'message'=>'Payment reference rejected. The user may submit a new reference.']);

        case 'content_list':
            admin_need();
            $type=trim((string)($d['type'] ?? ''));
            if ($type==='trivia') {
                $r=db()->query('SELECT id,question,option_a,option_b,option_c,option_d,correct_option,explanation,active,created_at FROM trivia_questions ORDER BY id DESC')->fetchAll();
                out(['success'=>true,'type'=>'trivia','items'=>$r]);
            }
            if (!in_array($type,['forex','ebook','tiktok','award'],true)) out(['success'=>false,'message'=>'Invalid content type.'],422);
            $q=db()->prepare('SELECT id,content_type,title,description,url,sort_order,active,created_at,updated_at FROM content_items WHERE content_type=? ORDER BY sort_order ASC,id DESC');
            $q->execute([$type]);
            out(['success'=>true,'type'=>$type,'items'=>$q->fetchAll()]);

        case 'content_save':
            admin_need();
            $type=trim((string)($d['type'] ?? ''));
            $id=(int)($d['id'] ?? 0);
            if ($type==='trivia') {
                $question=trim((string)($d['question'] ?? ''));
                $opts=[]; foreach(['A','B','C','D'] as $o) $opts[$o]=trim((string)($d['option_'.strtolower($o)] ?? ''));
                $correct=strtoupper(trim((string)($d['correct_option'] ?? '')));
                $explanation=trim((string)($d['explanation'] ?? ''));
                $active=!empty($d['active']) ? 1 : 0;
                if ($question==='' || in_array('', $opts, true) || !in_array($correct,['A','B','C','D'],true)) out(['success'=>false,'message'=>'Complete the question, all four options and the correct answer.'],422);
                if ($id>0) {
                    $q=db()->prepare('UPDATE trivia_questions SET question=?,option_a=?,option_b=?,option_c=?,option_d=?,correct_option=?,explanation=?,active=? WHERE id=?');
                    $q->execute([$question,$opts['A'],$opts['B'],$opts['C'],$opts['D'],$correct,$explanation,$active,$id]);
                } else {
                    $q=db()->prepare('INSERT INTO trivia_questions(question,option_a,option_b,option_c,option_d,correct_option,explanation,active) VALUES(?,?,?,?,?,?,?,?)');
                    $q->execute([$question,$opts['A'],$opts['B'],$opts['C'],$opts['D'],$correct,$explanation,$active]);
                }
                out(['success'=>true,'message'=>'Trivia question saved.']);
            }
            if (!in_array($type,['forex','ebook','tiktok','award'],true)) out(['success'=>false,'message'=>'Invalid content type.'],422);
            $title=trim((string)($d['title'] ?? ''));
            $description=trim((string)($d['description'] ?? ''));
            $url=trim((string)($d['url'] ?? ''));
            $sort=(int)($d['sort_order'] ?? 0);
            $active=!empty($d['active']) ? 1 : 0;
            if ($title==='') out(['success'=>false,'message'=>'Title is required.'],422);
            if ($url!=='' && !preg_match('#^https?://#i',$url)) out(['success'=>false,'message'=>'URL must start with http:// or https://.'],422);
            if ($id>0) {
                $q=db()->prepare('UPDATE content_items SET content_type=?,title=?,description=?,url=?,sort_order=?,active=? WHERE id=?');
                $q->execute([$type,$title,$description,$url,$sort,$active,$id]);
            } else {
                $q=db()->prepare('INSERT INTO content_items(content_type,title,description,url,sort_order,active) VALUES(?,?,?,?,?,?)');
                $q->execute([$type,$title,$description,$url,$sort,$active]);
            }
            out(['success'=>true,'message'=>'Content saved.']);

        case 'content_delete':
            admin_need();
            $type=trim((string)($d['type'] ?? ''));
            $id=(int)($d['id'] ?? 0);
            if ($id<1) out(['success'=>false,'message'=>'Invalid content item.'],422);
            if ($type==='trivia') {
                $q=db()->prepare('DELETE FROM trivia_questions WHERE id=?'); $q->execute([$id]);
            } else {
                if (!in_array($type,['forex','ebook','tiktok','award'],true)) out(['success'=>false,'message'=>'Invalid content type.'],422);
                $q=db()->prepare('DELETE FROM content_items WHERE id=? AND content_type=?'); $q->execute([$id,$type]);
            }
            out(['success'=>true,'message'=>'Content deleted.']);

        case 'withdrawals':
            admin_need();
            $r=db()->query('SELECT id,username,phone,amount,status,created_at FROM withdrawals ORDER BY created_at DESC')->fetchAll();
            out(['success'=>true,'withdrawals'=>$r]);

        case 'withdraw_action':
            admin_need();
            $id=(string)($d['id'] ?? '');
            $action=$d['action'] ?? '';
            if (!in_array($action,['approve','reject'],true)) out(['success'=>false,'message'=>'Invalid action.'],422);
            $status=$action==='approve'?'approved':'rejected';
            $q=db()->prepare('UPDATE withdrawals SET status=? WHERE id=? AND status="pending"');
            $q->execute([$status,$id]);
            if (!$q->rowCount()) out(['success'=>false,'message'=>'Withdrawal is no longer pending.'],422);
            out(['success'=>true]);

        default:
            out(['success'=>false,'message'=>'Unknown admin action.'],404);
    }
} catch (Throwable $e) {
    error_log('GOLYBRAND ADMIN API error: '.$e->getMessage());
    out(['success'=>false,'message'=>'Admin server error. Check MySQL/PHP configuration.'],500);
}
