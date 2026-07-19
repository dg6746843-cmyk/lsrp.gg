<?php
session_start();
$file_path = "admins.txt";
$log_file = "admin_logs.txt";
$notice_file = "admin_notice.txt";
$chat_file = "admin_chat_messages.txt";

$roles = [
    7 => "Founder",
    6 => "Project Management",
    5 => "Special Admin",
    4 => "Project Associate",
    3 => "Chief Admin",
    2 => "Senior Administrator",
    1 => "Server Administrator"
];

$role_colors = [
    7 => "#ff1744", // Founder - Neon Red
    6 => "#00e676", // Project Management - Bright Green
    5 => "#00b0ff", // Special Admin - Electric Blue
    4 => "#d500f9", // Project Associate - Purple
    3 => "#ff9100", // Chief Admin - Orange
    2 => "#ffea00", // Senior Administrator - Yellow
    1 => "#b0bec5"  // Server Administrator - Grey
];

if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    setcookie("admin_user", "", time() - 3600, "/");
    setcookie("admin_rank", "", time() - 3600, "/");
    header("Location: admin_portal.php");
    exit;
}

if (!isset($_SESSION['admin_logged']) && isset($_COOKIE['admin_user'])) {
    $_SESSION['admin_logged'] = htmlspecialchars($_COOKIE['admin_user']);
    $_SESSION['admin_rank'] = isset($_COOKIE['admin_rank']) ? (int)$_COOKIE['admin_rank'] : 1;
}

// AJAX Chat Endpoint internally handled to avoid 403 errors
if (isset($_GET['chat_action'])) {
    if (!isset($_SESSION['admin_logged'])) {
        echo json_encode([]);
        exit;
    }
    
    if ($_GET['chat_action'] === 'fetch') {
        header('Content-Type: application/json');
        $output = [];
        if (file_exists($chat_file)) {
            $lines = file($chat_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_slice($lines, -40);
            foreach ($lines as $line) {
                $json = json_decode($line, true);
                if ($json) $output[] = $json;
            }
        }
        echo json_encode($output);
        exit;
    }
    
    if ($_GET['chat_action'] === 'send' && $_SERVER["REQUEST_METHOD"] == "POST") {
        $msg = trim($_POST['message']);
        if (!empty($msg)) {
            $payload = json_encode([
                "username" => $_SESSION['admin_logged'],
                "rank" => (int)$_SESSION['admin_rank'],
                "message" => htmlspecialchars($msg),
                "time" => date("H:i")
            ]);
            file_put_contents($chat_file, $payload . "\n", FILE_APPEND);
        }
        exit;
    }
}

$msg = "";
$msg_type = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login_submit'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (!empty($username) && !empty($password)) {
        $user_exists = false;
        $matched_line = [];
        
        if (file_exists($file_path)) {
            $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $data = explode('|', $line);
                if (count($data) >= 4 && $data[0] === $username) {
                    $user_exists = true;
                    $matched_line = $data;
                    break;
                }
            }
        }

        if ($user_exists) {
            if (password_verify($password, $matched_line[1])) {
                $user_rank = (int)$matched_line[2];
                $is_approved = (int)$matched_line[3];
                
                if ($is_approved == 1) {
                    $_SESSION['admin_logged'] = $username;
                    $_SESSION['admin_rank'] = $user_rank;
                    setcookie("admin_user", $username, time() + (86400 * 30), "/");
                    setcookie("admin_rank", $user_rank, time() + (86400 * 30), "/");
                    header("Location: admin_portal.php");
                    exit;
                } else {
                    $msg = "Wait for approval from Vlad Sam!";
                    $msg_type = "error";
                }
            } else {
                $msg = "Invalid password for this account!";
                $msg_type = "error";
            }
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            if ($username === "Vlad Sam") {
                $new_user_line = "Vlad Sam|" . $hashed_password . "|7|1\n";
                file_put_contents($file_path, $new_user_line, FILE_APPEND);
                $_SESSION['admin_logged'] = "Vlad Sam";
                $_SESSION['admin_rank'] = 7;
                setcookie("admin_user", "Vlad Sam", time() + (86400 * 30), "/");
                setcookie("admin_rank", 7, time() + (86400 * 30), "/");
                header("Location: admin_portal.php");
                exit;
            } else {
                $new_user_line = $username . "|" . $hashed_password . "|1|0\n";
                file_put_contents($file_path, $new_user_line, FILE_APPEND);
                $msg = "Registration successful! Wait for Vlad Sam to approve.";
                $msg_type = "success";
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_role']) && isset($_SESSION['admin_logged'])) {
    $my_rank = (int)$_SESSION['admin_rank'];
    $target_user = trim($_POST['target_user']);
    $new_rank = (int)$_POST['new_rank'];
    
    if ($my_rank >= 6 && file_exists($file_path)) {
        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $new_file_content = "";
        foreach ($lines as $line) {
            $data = explode('|', $line);
            if (count($data) >= 4 && $data[0] === $target_user) {
                if ($new_rank <= $my_rank) {
                    $line = "{$data[0]}|\x20{$data[1]}|{$new_rank}|1";
                    $line = str_replace("\x20", "", $line);
                }
            }
            $new_file_content .= $line . "\n";
        }
        file_put_contents($file_path, $new_file_content);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['post_notice']) && isset($_SESSION['admin_logged']) && $_SESSION['admin_rank'] >= 6) {
    $notice_text = htmlspecialchars(trim($_POST['notice_text']));
    if (!empty($notice_text)) {
        file_put_contents($notice_file, $_SESSION['admin_logged'] . "|" . date("M d, Y H:i") . "|" . $notice_text);
    }
}

$total_admins = 0;
$pending_admins = 0;
if (file_exists($file_path)) {
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $data = explode('|', $line);
        if (count($data) >= 4) {
            if ($data[3] == 1) $total_admins++;
            else $pending_admins++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LIVE STATE - Admin Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;700;800&display=swap" rel="stylesheet">
<style>
*{ margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Montserrat', sans-serif; background: #06122b; color: white; min-height: 100vh; }
.login-page { display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
.login-container { background: rgba(6, 18, 43, 0.85); border: 1px solid rgba(255, 255, 255, 0.1); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); width: 100%; max-width: 400px; padding: 40px 30px; border-radius: 15px; text-align: center; }
.login-container h2 { font-weight: 800; font-size: 24px; margin-bottom: 10px; letter-spacing: 1px; text-transform: uppercase; }
.login-container p { font-size: 14px; opacity: 0.7; margin-bottom: 30px; }
.input-group { margin-bottom: 20px; text-align: left; }
.input-group label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 8px; opacity: 0.8; }
.input-group input, .control-box input, .control-box select, .control-box textarea { width: 100%; padding: 14px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 10px; color: white; font-family: 'Montserrat', sans-serif; font-size: 14px; outline: none; }
.input-group input:focus { border-color: #e67e22; background: rgba(255, 255, 255, 0.08); }
.btn { width: 100%; padding: 14px; background: #e67e22; color: white; border: none; border-radius: 10px; font-weight: 700; font-size: 16px; cursor: pointer; box-shadow: 0 4px 15px rgba(230, 126, 34, 0.4); transition: 0.2s; }
.btn:hover { transform: scale(1.02); background: #d35400; }
.msg-box { border: 1px solid; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 700; margin-bottom: 20px; }
.msg-box.error { background: rgba(231, 76, 60, 0.2); border-color: #e74c3c; color: #e74c3c; }
.msg-box.success { background: rgba(46, 204, 113, 0.2); border-color: #2ecc71; color: #2ecc71; }
.dashboard-page { padding: 40px 20px; max-width: 1200px; margin: 0 auto; }
.dash-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; }
.dash-header h1 { font-weight: 800; font-size: 28px; }
.logout-btn { color: #e74c3c; text-decoration: none; font-weight: 700; font-size: 14px; border: 1px solid #e74c3c; padding: 8px 16px; border-radius: 8px; transition: 0.2s; }
.logout-btn:hover { background: #e74c3c; color: white; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
.stat-card { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.05); padding: 20px; border-radius: 12px; text-align: center; }
.stat-card h4 { font-size: 12px; opacity: 0.6; margin-bottom: 5px; text-transform: uppercase; }
.stat-card p { font-size: 28px; font-weight: 800; color: #ffc400; }
.grid-container { display: grid; grid-template-columns: 1fr; gap: 25px; }
@media(min-width: 768px) { .grid-container { grid-template-columns: 2fr 1fr; } }
.card { background: rgba(6, 18, 43, 0.85); border: 1px solid rgba(255, 255, 255, 0.1); padding: 25px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); margin-bottom: 25px; }
.card h3 { font-weight: 700; font-size: 18px; margin-bottom: 15px; letter-spacing: 0.5px; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05); text-align: left; font-size: 14px; }
th { font-weight: 700; opacity: 0.6; }
.status-approved { color: #2ecc71; font-weight: 700; }
.status-pending { color: #f1c40f; font-weight: 700; }
.control-box { display: flex; flex-direction: column; gap: 15px; }
.control-box input, .control-box select, .control-box textarea { padding: 12px; }
.notice-banner { background: rgba(230, 126, 34, 0.1); border-left: 4px solid #e67e22; padding: 15px; border-radius: 4px; margin-bottom: 25px; font-size: 14px; line-height: 1.6; }

/* IN-LINE REALTIME GROUP CHAT UI STYLING */
.chat-box { height: 320px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding: 10px; background: rgba(0,0,0,0.2); border-radius: 10px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 12px; }
.chat-box::-webkit-scrollbar { width: 4px; }
.chat-box::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 10px; }
.bubble { max-width: 85%; padding: 10px 14px; border-radius: 14px; line-height: 1.4; word-wrap: break-word; font-size: 13px; display: flex; flex-direction: column; }
.bubble.incoming { background: #16264f; align-self: flex-start; border-bottom-left-radius: 4px; }
.bubble.outgoing { background: #e67e22; color: white; align-self: flex-end; border-bottom-right-radius: 4px; }
.meta-tag { font-size: 11px; font-weight: 700; margin-bottom: 4px; display: flex; gap: 6px; align-items: center; }
.meta-time { font-size: 9px; opacity: 0.5; text-align: right; margin-top: 4px; font-weight: 500; }
.chat-form { display: flex; gap: 8px; }
.chat-form input { flex: 1; padding: 12px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: white; font-family: 'Montserrat', sans-serif; outline: none; }
.chat-form button { padding: 0 20px; background: #e67e22; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 700; transition: 0.2s; }
.chat-form button:hover { background: #d35400; }
</style>
</head>
<body>

<?php if (!isset($_SESSION['admin_logged'])): ?>
    <div class="login-page">
        <div class="login-container">
            <h2>ADMIN PORTAL</h2>
            <p>Sign in to access control panel</p>
            <?php if(!empty($msg)): ?>
                <div class="msg-box <?php echo $msg_type; ?>"><?php echo $msg; ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="input-group">
                    <label>USERNAME</label>
                    <input type="text" name="username" required autocomplete="off">
                </div>
                <div class="input-group">
                    <label>PASSWORD</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" name="login_submit" class="btn">LOGIN / SIGN UP</button>
            </form>
        </div>
    </div>
<?php else: ?>
    <div class="dashboard-page">
        <div class="dash-header">
            <div>
                <h1>CONTROL PANEL</h1>
                <p style="font-size:14px; opacity:0.7; margin-top:5px;">Welcome back, <strong style="color:#ffc400;"><?php echo $_SESSION['admin_logged']; ?></strong> [Role: <?php echo $roles[$_SESSION['admin_rank']]; ?>]</p>
            </div>
            <a href="admin_portal.php?action=logout" class="logout-btn">LOGOUT</a>
        </div>

        <?php 
        if (file_exists($notice_file)) {
            $notice_data = explode('|', file_get_contents($notice_file));
            if (count($notice_data) >= 3) {
                echo '<div class="notice-banner">
                        <strong>Global Announcement from ' . htmlspecialchars($notice_data[0]) . ' (' . htmlspecialchars($notice_data[1]) . '):</strong><br>' . htmlspecialchars($notice_data[2]) . '
                      </div>';
            }
        }
        ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h4>Verified Managers</h4>
                <p><?php echo $total_admins; ?></p>
            </div>
            <div class="stat-card">
                <h4>Pending Approvals</h4>
                <p style="color:#f1c40f;"><?php echo $pending_admins; ?></p>
            </div>
            <div class="stat-card">
                <h4>System Authorization</h4>
                <p style="color:#2ecc71;">ONLINE</p>
            </div>
        </div>

        <div class="grid-container">
            <div class="card">
                <h3>Staff Directory & Rosters</h3>
                <table>
                    <thead>
                        <tr>
                            <th>USERNAME</th>
                            <th>RANK ROLE</th>
                            <th>ACCESS STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (file_exists($file_path)) {
                            $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            foreach ($lines as $line) {
                                $data = explode('|', $line);
                                if (count($data) >= 4) {
                                    $status_html = ($data[3] == 1) ? '<span class="status-approved">Approved</span>' : '<span class="status-pending">Pending</span>';
                                    $role_title = isset($roles[(int)$data[2]]) ? $roles[(int)$data[2]] : "Unknown";
                                    echo "<tr>
                                            <td>" . htmlspecialchars($data[0]) . "</td>
                                            <td style='color:".($role_colors[(int)$data[2]] ?? '#fff')."; font-weight:700;'> " . $role_title . " (Lvl {$data[2]})</td>
                                            <td>{$status_html}</td>
                                          </tr>";
                                }
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div style="display: flex; flex-direction: column; gap: 5px;">
                <?php if ($_SESSION['admin_rank'] >= 6): ?>
                    <div class="card" style="border-color: #e67e22;">
                        <h3 style="color: #e67e22;">Promote & Authorize Status</h3>
                        <form method="POST" action="" class="control-box">
                            <input type="text" name="target_user" placeholder="Target Staff Username" required autocomplete="off">
                            <select name="new_rank">
                                <?php 
                                foreach ($roles as $lvl => $title) {
                                    if ($lvl <= $_SESSION['admin_rank']) {
                                        echo "<option value='{$lvl}'>Level {$lvl} - {$title}</option>";
                                    }
                                }
                                ?>
                            </select>
                            <button type="submit" name="update_role" class="btn">Apply Role & Approve</button>
                        </form>
                    </div>

                    <div class="card" style="border-color: #ffc400;">
                        <h3 style="color: #ffc400;">Broadcast System Message</h3>
                        <form method="POST" action="" class="control-box">
                            <textarea name="notice_text" rows="3" placeholder="Type message parameters here..." required></textarea>
                            <button type="submit" name="post_notice" class="btn" style="background:#ffc400; color:#000; box-shadow: 0 4px 15px rgba(255, 196, 0, 0.3);">Post Notice</button>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="card" style="border-color: #00b0ff;">
                    <h3 style="color: #00b0ff;">Secure Staff Room (Group Chat)</h3>
                    <div class="chat-box" id="chatBoxContainer"></div>
                    <form class="chat-form" id="portalChatForm" onsubmit="transmitMessage(event)">
                        <input type="text" id="portalMsgInput" placeholder="Type a message to team..." required autocomplete="off">
                        <button type="submit">Send</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    const current_user = <?php echo json_encode($_SESSION['admin_logged']); ?>;
    const role_colors = <?php echo json_encode($role_colors); ?>;
    const role_titles = <?php echo json_encode($roles); ?>;

    function fetchGroupMessages() {
        fetch('admin_portal.php?chat_action=fetch')
            .then(res => res.json())
            .then(data => {
                let box = document.getElementById('chatBoxContainer');
                let shouldScroll = box.scrollTop + box.clientHeight >= box.scrollHeight - 60;
                let html = '';
                
                data.forEach(msg => {
                    let isMe = (msg.username === current_user);
                    let bubbleType = isMe ? 'outgoing' : 'incoming';
                    let color = role_colors[msg.rank] || '#b0bec5';
                    let title = role_titles[msg.rank] || 'Staff';

                    html += `<div class="bubble ${bubbleType}">`;
                    if(!isMe) {
                        html += `<div class="meta-tag" style="color:${color}">${msg.username} <span style="opacity:0.6; font-size:9px; background:rgba(255,255,255,0.1); padding:1px 4px; border-radius:3px;">${title}</span></div>`;
                    }
                    html += `<div>${msg.message}</div>`;
                    html += `<div class="meta-time">${msg.time}</div>`;
                    html += `</div>`;
                });
                
                box.innerHTML = html;
                if(shouldScroll || box.innerHTML === '') {
                    box.scrollTop = box.scrollHeight;
                }
            });
    }

    function transmitMessage(e) {
        e.preventDefault();
        let input = document.getElementById('portalMsgInput');
        let text = input.value.trim();
        if(!text) return;

        let data = new FormData();
        data.append('message', text);
        input.value = '';

        fetch('admin_portal.php?chat_action=send', { method: 'POST', body: data })
            .then(() => fetchGroupMessages());
    }

    setInterval(fetchGroupMessages, 2000);
    window.onload = fetchGroupMessages;
    </script>
<?php endif; ?>

</body>
</html>

