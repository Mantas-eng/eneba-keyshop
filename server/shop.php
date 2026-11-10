<?php
declare(strict_types=1);
header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__.'/TursoDatabase.php';
function respond($data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_THROW_ON_ERROR);
    exit;
}
function fail(string $message,int $status=400): never { respond(['error'=>$message],$status); }
function query(string $sql,array $params=[]): PDOStatement|TursoResult {
    global $db;
    if ($db instanceof TursoDatabase) return $db->query($sql,$params);
    $statement=$db->prepare($sql);
    $statement->execute($params);
    return $statement;
}
function matches(string $name,string $term): bool {
    $name=strtolower($name);
    foreach (preg_split('/\s+/',strtolower(trim($term))) as $word) {
        if (str_contains($name,$word)) continue;
        $limit=strlen($word)>=6 ? 2 : 1;
        foreach (explode(' ',$name) as $part) {
            if (strlen($word)>=3 && levenshtein($word,$part)<=$limit) continue 2;
        }
        return false;
    }
    return true;
}
try {
    $url=getenv('TURSO_DATABASE_URL');
    if ($url || getenv('VERCEL')) {
        if (!$url || !getenv('TURSO_AUTH_TOKEN')) throw new RuntimeException('Missing Turso configuration.');
        $db=new TursoDatabase($url,getenv('TURSO_AUTH_TOKEN'));
    } else {
        $db=new PDO('sqlite:'.(getenv('DB_PATH') ?: __DIR__.'/shop.sqlite'),null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $db->exec('PRAGMA busy_timeout=5000; PRAGMA foreign_keys=ON');
    }
    if ((int)$db->query('PRAGMA user_version')->fetchColumn()<2) {
        $db->beginTransaction();
        $db->exec(file_get_contents(__DIR__.'/schema.sql'));
        $db->exec('PRAGMA user_version=2');
        $db->commit();
    }
    $cookie=$_COOKIE['keyshop_session'] ?? '';
    $hash=hash('sha256',$cookie);
    $session=$cookie ? query('SELECT users.id,users.email FROM sessions JOIN users ON users.id=sessions.user WHERE token=? AND expires>?',[$hash,time()])->fetch() : null;
    $options=['path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>(bool)getenv('VERCEL') || isset($_SERVER['HTTPS'])];
    $route=$_GET['r'] ?? 'catalog';
    $catalog='SELECT g.*,min(o.cents) AS price FROM games g JOIN offers o ON o.game=g.id';
    $user=$session['id'] ?? null;
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        switch($route) {
            case 'session': respond(['email'=>$session['email'] ?? null]);
            case 'banner': respond(query('SELECT * FROM banner LIMIT 1')->fetch());
            case 'shelves':
                $shelves=query('SELECT * FROM shelves ORDER BY id')->fetchAll();
                foreach ($shelves as &$s) $s['games']=query("$catalog JOIN picks p ON p.game=g.id WHERE p.shelf=? GROUP BY g.id ORDER BY p.position",[$s['id']])->fetchAll();
                respond($shelves);
            case 'catalog':
                $games=query("$catalog GROUP BY g.id ORDER BY g.name")->fetchAll();
                respond(array_values(array_filter($games,fn($g)=>matches($g['name'],substr($_GET['q'] ?? '',0,128)))));
            case 'game':
                $game=query('SELECT * FROM games WHERE id=?',[$_GET['id'] ?? 0])->fetch();
                if (!$game) fail('Game not found.',404);
                $game['offers']=query('SELECT * FROM offers WHERE game=? ORDER BY cents',[$game['id']])->fetchAll();
                respond($game);
            case 'orders':
                if (!$user) fail('Please sign in.',401);
                respond(query('SELECT orders.id,created,name,seller,paid,license FROM orders JOIN items ON items.order_id=orders.id JOIN offers ON offers.id=items.offer JOIN games ON games.id=offers.game WHERE user=? ORDER BY orders.id DESC,items.id',[$user])->fetchAll());
        }
    } elseif ($_SERVER['REQUEST_METHOD']==='POST') {
        if (explode(';',$_SERVER['CONTENT_TYPE'] ?? '')[0]!=='application/json') fail('Expected JSON.',415);
        $input=json_decode(file_get_contents('php://input'),true);
        if (!is_array($input)) fail('Invalid request.');
        switch($route) {
            case 'register':
            case 'login':
                $email=strtolower(trim($input['email'] ?? ''));
                $password=$input['password'] ?? '';
                if (!filter_var($email,FILTER_VALIDATE_EMAIL) || !is_string($password) || strlen($password)>72) fail('Check your email and password (up to 72 bytes).');
                if ($route==='register') {
                    if (strlen($password)<8) fail('Use at least eight characters.');
                    if (!query('INSERT OR IGNORE INTO users(email,password) VALUES(?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)])->rowCount()) fail('Email already registered.',409);
                }
                $account=query('SELECT * FROM users WHERE email=?',[$email])->fetch();
                if (!$account || !password_verify($password,$account['password'])) fail('Incorrect email or password.',401);
                $token=bin2hex(random_bytes(32));
                $expires=time()+7*86400;
                query('DELETE FROM sessions WHERE expires<=? OR token=?',[time(),$hash]);
                query('INSERT INTO sessions(token,user,expires) VALUES(?,?,?)',[hash('sha256',$token),$account['id'],$expires]);
                setcookie('keyshop_session',$token,$options+['expires'=>$expires]);
                respond(['email'=>$email]);
            case 'logout':
                query('DELETE FROM sessions WHERE token=?',[$hash]);
                setcookie('keyshop_session','',$options+['expires'=>1]); respond(['email'=>null]);
            case 'checkout':
                if (!$user) fail('Please sign in.',401);
                $ids=$input['offers'] ?? [];
                $token=$input['token'] ?? '';
                if (!is_array($ids) || !$ids || count($ids)>50 || !preg_match('/^[a-zA-Z0-9-]{16,64}$/D',$token)) fail('Invalid cart.');
                $db->beginTransaction();
                $new=query('INSERT OR IGNORE INTO orders(user,token) VALUES(?,?)',[$user,$token])->rowCount();
                $order=query('SELECT id FROM orders WHERE user=? AND token=?',[$user,$token])->fetchColumn();
                if ($new) foreach ($ids as $id) {
                    $price=query('SELECT cents FROM offers WHERE id=?',[$id])->fetchColumn();
                    if ($price===false) { $db->rollBack(); fail('Offer unavailable.'); }
                    query('INSERT INTO items(order_id,offer,paid,license) VALUES(?,?,?,?)',[$order,$id,$price,'DEMO-'.strtoupper(bin2hex(random_bytes(10)))]);
                }
                $db->commit();
                respond(['order'=>$order]);
        }
    }
    fail('Unknown endpoint.',404);
} catch(Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log($e->getMessage());
    fail('The shop could not complete this request.',500);
}




