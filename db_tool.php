<?php
// Helpful debug output while using this temporary tool
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// Simple DB admin tool (TEMPORARY). DELETE after use. A minimal ORM-ish browser for a single DB.
// Usage: upload to webroot and visit /db_tool.php?token=YOURTOKEN
// Protect with strong token and/or IP restrictions. Do NOT leave enabled in production.

// --- Embedded DB credentials (provided) ---
$DB_HOST = 'localhost';
$DB_USER = 'i9808830_dlk11';
$DB_PASS = 'P.ovRJ03xjX1GN6MrHh51';
$DB_NAME = 'i9808830_dlk11';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
  die("Database connection failed: " . $conn->connect_error);
}

// === Configuration - already preconfigured for you ===
// A long random token is set so you can upload and use the tool without editing.
// If you want to restrict access to specific IPs, add them to $ALLOWED_IPS.
$TOOL_TOKEN = 'Z8xvY3GmQp7Lr9Nc2JtK0bHf5Uu4Ew1S6aRzPqY2';
$ALLOWED_IPS = []; // keep empty to allow any IP (recommended to fill in when possible)
// =======================================

// Basic auth by token (GET or POST token)
$token = $_REQUEST['token'] ?? '';
if ($token !== $TOOL_TOKEN) {
    http_response_code(403);
    echo "<h2>Forbidden</h2><p>Invalid token. Supply ?token=... in the URL.</p>";
    exit;
}

if (!empty($ALLOWED_IPS) && !in_array($_SERVER['REMOTE_ADDR'], $ALLOWED_IPS, true)) {
    http_response_code(403);
    echo "<h2>Forbidden</h2><p>Your IP ({$_SERVER['REMOTE_ADDR']}) is not allowed.</p>";
    exit;
}

// helpers
function h($s){ return htmlspecialchars((string)$s); }
function backtick($s){ return "`".str_replace("`","``", $s)."`"; }

// get list of tables
function get_tables($conn){
    $res = $conn->query("SHOW TABLES");
    $tables = [];
    while ($r = $res->fetch_row()) $tables[] = $r[0];
    return $tables;
}

function get_columns($conn, $table){
    $cols = [];
    $res = $conn->query("SHOW COLUMNS FROM ".backtick($table));
    while ($r = $res->fetch_assoc()) $cols[] = $r;
    return $cols;
}

function get_primary_key($conn,$table){
    $res = $conn->query("SHOW KEYS FROM ".backtick($table)." WHERE Key_name='PRIMARY'");
    $keys = [];
    while ($r = $res->fetch_assoc()) $keys[] = $r['Column_name'];
    return $keys; // array (possibly composite)
}

function fetch_rows($conn,$table,$limit=25,$offset=0){
    $t = backtick($table);
    $sql = "SELECT * FROM $t LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii',$limit,$offset);
    $stmt->execute();
    return $stmt->get_result();
}

// handle actions: raw SQL, insert, update, delete
$action = $_REQUEST['action'] ?? '';
$msg = '';
$sql_results = [];
$sql_executed = false;

// Run SQL once, capture all result sets and messages so we don't re-run the query later
if ($action === 'sql' && $_SERVER['REQUEST_METHOD'] === 'POST'){
  $sql = $_POST['sql'] ?? '';
  if (!trim($sql)) {
    $msg = 'No SQL provided';
  } else {
    // run multi query and capture results safely
    if ($conn->multi_query($sql)){
      do {
        if ($res = $conn->store_result()){
          $rows = [];
          while ($r = $res->fetch_assoc()) $rows[] = $r;
          $fields = [];
          foreach ($res->fetch_fields() as $f) $fields[] = $f->name;
          $sql_results[] = ['type'=>'rows','fields'=>$fields,'rows'=>$rows];
          $res->free();
        } else {
          if ($conn->errno) {
            $sql_results[] = ['type'=>'error','error'=>$conn->error];
          } else {
            $sql_results[] = ['type'=>'ok','affected'=>$conn->affected_rows];
          }
        }
      } while ($conn->more_results() && $conn->next_result());
      $msg = 'SQL executed. Review results below.';
      $sql_executed = true;
    } else {
      $msg = 'SQL error: '.h($conn->error);
      $sql_results[] = ['type'=>'error','error'=>$conn->error];
      $sql_executed = true;
    }
  }
}

if (($action === 'delete' || $action === 'update' || $action === 'insert') && $_SERVER['REQUEST_METHOD'] === 'POST'){
    $table = $_POST['table'] ?? '';
    if (!$table) { $msg = 'Missing table'; }
    else {
        $cols = get_columns($conn,$table);
        $pk = get_primary_key($conn,$table);

        if ($action === 'delete'){
            // expect pk[]=... in POST
            if (empty($pk)) { $msg='No primary key on table; abort delete.'; }
            else {
                $where = [];
                $types = '';
                $values = [];
                foreach ($pk as $kcol){
                    $val = $_POST["pk_$kcol"] ?? null;
                    $where[] = backtick($kcol).' = ?';
                    $types .= 's';
                    $values[] = $val;
                }
                $sql = "DELETE FROM ".backtick($table).' WHERE '.implode(' AND ',$where).' LIMIT 1';
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types,...$values);
                $stmt->execute();
                $msg = 'Deleted, affected rows: '. $stmt->affected_rows;
            }
        } elseif ($action === 'update'){
            // build set clause from posted fields
            $pairs = [];
            $types=''; $values=[];
            foreach ($cols as $c){
                $name = $c['Field'];
                if (array_key_exists($name,$_POST) && !in_array($name,$pk,true)){
                    $pairs[] = backtick($name).' = ?';
                    $types .= 's';
                    $values[] = $_POST[$name];
                }
            }
            if (empty($pairs)) { $msg = 'No updatable fields provided.'; }
            else {
                // where clause
                $where = [];
                foreach ($pk as $kcol){ $where[] = backtick($kcol).' = ?'; $types .= 's'; $values[] = $_POST["pk_$kcol"]; }
                $sql = "UPDATE ".backtick($table).' SET '.implode(', ',$pairs).' WHERE '.implode(' AND ',$where).' LIMIT 1';
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types,...$values);
                $stmt->execute();
                $msg = 'Updated, affected rows: '. $stmt->affected_rows;
            }
        } elseif ($action === 'insert'){
            $fields = [];$place = []; $types=''; $values=[];
            foreach ($cols as $c){ $name=$c['Field']; if (array_key_exists($name,$_POST)) { $fields[] = backtick($name); $place[]='?'; $types.='s'; $values[] = $_POST[$name]; } }
            if (empty($fields)) { $msg='No fields provided for insert.'; }
            else {
                $sql = 'INSERT INTO '.backtick($table).' ('.implode(',',$fields).') VALUES ('.implode(',',$place).')';
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($types,...$values);
                $stmt->execute();
                $msg = 'Inserted, new id: '. $stmt->insert_id;
            }
        }
    }
}

// Render UI
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>DB Tool</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>table td,table th{padding:6px;border-bottom:1px solid rgba(255,255,255,0.04)}</style>
</head>
<body>
<?php include_once __DIR__ . '/includes/nav.php'; ?>
<main class="container">
  <section class="card" style="padding:18px;">
    <div class="page-top">
      <div><h2 class="page-title">DB Tool</h2><div class="muted">Database browser + SQL runner (temporary)</div></div>
      <div class="muted">Use token in URL</div>
    </div>

    <?php if($msg): ?><div class="alert alert-success"><?php echo h($msg); ?></div><?php endif; ?>

    <div style="display:flex;gap:18px;align-items:flex-start;flex-wrap:wrap">
      <div style="min-width:220px;flex:0 0 260px">
        <div class="label">Tables</div>
        <nav class="form-row">
          <?php foreach(get_tables($conn) as $t): ?>
            <a class="dashboard-link" href="?token=<?php echo urlencode($TOOL_TOKEN); ?>&table=<?php echo urlencode($t); ?>"><?php echo h($t); ?></a>
          <?php endforeach; ?>
        </nav>
      </div>

      <div style="flex:1;min-width:420px">
        <?php if(!empty($_GET['table'])):
            $table = $_GET['table'];
            $cols = get_columns($conn,$table);
            $pk = get_primary_key($conn,$table);
            // pagination
            $page = max(1,(int)($_GET['p']??1)); $limit=25; $offset = ($page-1)*$limit;
            $res = fetch_rows($conn,$table,$limit,$offset);
        ?>
          <div style="display:flex;justify-content:space-between;align-items:center">
            <h3><?php echo h($table); ?></h3>
            <div>
              <a class="btn btn-ghost" href="?token=<?php echo urlencode($TOOL_TOKEN); ?>&table=<?php echo urlencode($table); ?>&action=insert_form">New row</a>
              <a class="btn" href="?token=<?php echo urlencode($TOOL_TOKEN); ?>">Refresh</a>
            </div>
          </div>

          <div style="overflow:auto;border-radius:8px;background:var(--card-bg);padding:8px;margin-top:8px">
            <table style="width:100%">
              <thead><tr>
                <?php foreach($cols as $c): ?><th><?php echo h($c['Field']); ?></th><?php endforeach; ?><th>actions</th>
              </tr></thead>
              <tbody>
                <?php while($row = $res->fetch_assoc()): ?>
                  <tr>
                    <?php foreach($cols as $c): $fn=$c['Field']; ?><td><?php echo h($row[$fn]); ?></td><?php endforeach; ?>
                    <td style="white-space:nowrap">
                      <?php if(!empty($pk)): ?>
                        <form method="post" style="display:inline">
                          <?php foreach($pk as $kcol): ?><input type="hidden" name="pk_<?php echo h($kcol); ?>" value="<?php echo h($row[$kcol]); ?>"><?php endforeach; ?>
                          <input type="hidden" name="table" value="<?php echo h($table); ?>">
                          <input type="hidden" name="token" value="<?php echo h($TOOL_TOKEN); ?>">
                          <button name="action" value="edit" formaction="?token=<?php echo urlencode($TOOL_TOKEN); ?>&table=<?php echo urlencode($table); ?>&action=edit_form" class="btn-ghost">Edit</button>
                        </form>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete row?');">
                          <?php foreach($pk as $kcol): ?><input type="hidden" name="pk_<?php echo h($kcol); ?>" value="<?php echo h($row[$kcol]); ?>"><?php endforeach; ?>
                          <input type="hidden" name="table" value="<?php echo h($table); ?>">
                          <input type="hidden" name="token" value="<?php echo h($TOOL_TOKEN); ?>">
                          <button name="action" value="delete" class="btn">Delete</button>
                        </form>
                      <?php else: ?>
                        <span class="muted">No PK</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>

          <div style="margin-top:10px">
            <a href="?token=<?php echo urlencode($TOOL_TOKEN); ?>&table=<?php echo urlencode($table); ?>&p=<?php echo max(1,$page-1); ?>" class="btn btn-ghost">Prev</a>
            <a href="?token=<?php echo urlencode($TOOL_TOKEN); ?>&table=<?php echo urlencode($table); ?>&p=<?php echo $page+1; ?>" class="btn btn-ghost">Next</a>
          </div>

        <?php elseif(isset($_GET['action']) && $_GET['action']==='insert_form' && !empty($_GET['table'])):
            $table = $_GET['table']; $cols = get_columns($conn,$table);
        ?>
          <h3>Insert into <?php echo h($table); ?></h3>
          <form method="post">
            <input type="hidden" name="action" value="insert">
            <input type="hidden" name="table" value="<?php echo h($table); ?>">
            <input type="hidden" name="token" value="<?php echo h($TOOL_TOKEN); ?>">
            <?php foreach($cols as $c): $name=$c['Field']; ?>
              <label class="form-row"><span class="form-label"><?php echo h($name); ?></span><input name="<?php echo h($name); ?>" class="form-control"></label>
            <?php endforeach; ?>
            <div class="form-row"><button class="btn btn-hero" type="submit">Insert</button></div>
          </form>

        <?php elseif(isset($_GET['action']) && $_GET['action']==='edit_form' && !empty($_GET['table'])):
            $table = $_GET['table']; $cols = get_columns($conn,$table); $pk = get_primary_key($conn,$table);
            // load row by pk from POST (we sent pk values earlier)
            $where=[];$types='';$vals=[];
            foreach($pk as $kcol){ $val = $_POST["pk_$kcol"] ?? ''; $where[] = backtick($kcol).' = ?'; $types.='s'; $vals[] = $val; }
            $sql = 'SELECT * FROM '.backtick($table).' WHERE '.implode(' AND ',$where).' LIMIT 1';
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types,...$vals);
            $stmt->execute(); $row = $stmt->get_result()->fetch_assoc();
        ?>
          <h3>Edit <?php echo h($table); ?></h3>
          <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="table" value="<?php echo h($table); ?>">
            <input type="hidden" name="token" value="<?php echo h($TOOL_TOKEN); ?>">
            <?php foreach($pk as $kcol): ?><input type="hidden" name="pk_<?php echo h($kcol); ?>" value="<?php echo h($row[$kcol]); ?>"><?php endforeach; ?>
            <?php foreach($cols as $c): $name=$c['Field']; ?>
              <label class="form-row"><span class="form-label"><?php echo h($name); ?></span>
                <input name="<?php echo h($name); ?>" class="form-control" value="<?php echo h($row[$name]); ?>">
              </label>
            <?php endforeach; ?>
            <div class="form-row"><button class="btn btn-hero" type="submit">Update</button></div>
          </form>

        <?php else: ?>
          <h3>SQL Runner</h3>
          <form method="post">
            <input type="hidden" name="action" value="sql">
            <input type="hidden" name="token" value="<?php echo h($TOOL_TOKEN); ?>">
            <label class="form-row"><textarea name="sql" rows="8" class="form-control"></textarea></label>
            <div class="form-row"><button class="btn btn-hero" type="submit">Run</button></div>
          </form>

          <?php
            // Render any SQL results captured earlier (we execute SQL once and store results in $sql_results)
            if (!empty($sql_results)){
              echo '<div style="margin-top:12px">';
              foreach($sql_results as $r){
                if ($r['type'] === 'rows'){
                  echo '<table style="width:100%;margin-bottom:10px">';
                  echo '<tr>'; foreach($r['fields'] as $f) echo '<th>'.h($f).'</th>'; echo '</tr>';
                  foreach($r['rows'] as $row){ echo '<tr>'; foreach($r['fields'] as $f) echo '<td>'.h($row[$f]).'</td>'; echo '</tr>'; }
                  echo '</table>';
                } elseif ($r['type'] === 'ok'){
                  echo '<div class="muted">OK - affected: '.h($r['affected']).'</div>';
                } elseif ($r['type'] === 'error'){
                  echo '<div class="alert alert-error">SQL error: '.h($r['error']).'</div>';
                }
              }
              echo '</div>';
            }
          ?>

        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
<?php include_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
