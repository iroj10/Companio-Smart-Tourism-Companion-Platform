<?php
session_start();
if (!isset($_SESSION['user_id'])) { http_response_code(401); exit; }
require_once 'includes/db.php';
$currentUser = (int)$_SESSION['user_id'];
if (!isset($_GET['uid'])) { die("No chat partner specified."); }
$partnerId = (int)$_GET['uid'];

$pres = $db->query("SELECT first_name, last_name FROM users WHERE user_id=$partnerId");
if (!$pres || $pres->num_rows === 0) { die("User not found."); }
$partnerData = $pres->fetch_assoc();
$partnerName = $partnerData['first_name']." ".$partnerData['last_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $msgText = $db->real_escape_string($_POST['message']);
    $db->query("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES ($currentUser, $partnerId, '$msgText')");
    header('Content-Type: application/json');
    echo json_encode(["status"=>"ok"]);
    exit;
}

if (isset($_GET['last_id'])) {
    $lastId = (int)$_GET['last_id'];
    $qry = "SELECT message_id, sender_id, message_text, sent_at 
            FROM messages
            WHERE ((sender_id=$currentUser AND receiver_id=$partnerId) 
                OR (sender_id=$partnerId AND receiver_id=$currentUser))
              AND message_id > $lastId
            ORDER BY message_id ASC";
    $res = $db->query($qry);
    $newMessages = [];
    while($row = $res->fetch_assoc()) { $newMessages[] = $row; }
    header('Content-Type: application/json');
    echo json_encode($newMessages);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chat with <?= htmlspecialchars($partnerName); ?></title>
  <link rel="stylesheet" href="assets/companio.css">
  <style>
    .chat-window { max-width: 700px; margin: 2rem auto; }
    .messages { border: 1px solid #ccc; background:#fff; padding: 1rem; height: 380px; overflow-y: auto; margin-bottom: 1rem; border-radius:10px; }
    .message { margin: 0.5rem 0; }
    .message.you { text-align: right; }
    .message .text { display: inline-block; padding: 0.5rem 1rem; border-radius: 10px; }
    .message.you .text { background: #667eea; color: #fff; }
    .message.them .text { background: #f1f1f1; color: #333; }
    .message .time { font-size: 0.8rem; color: #666; margin: 0 0.5rem; }
  </style>
</head>
<body>
  <header>
    <nav>
      <div class="logo">🌍 Companio</div>
      <div class="nav-links">
        <a href="index.php">Home</a>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Sign Out</a>
      </div>
    </nav>
  </header>
  <main>
    <div class="chat-window container">
      <h2>Chat with <?= htmlspecialchars($partnerName); ?></h2>
      <div id="messages" class="messages">
        <?php
          $qry = "SELECT sender_id, message_text, DATE_FORMAT(sent_at, '%H:%i') as time, message_id
                  FROM messages
                  WHERE (sender_id=$currentUser AND receiver_id=$partnerId) 
                     OR (sender_id=$partnerId AND receiver_id=$currentUser)
                  ORDER BY message_id ASC";
          $res = $db->query($qry);
          $lastId = 0;
          while($msg = $res->fetch_assoc()):
            $isMe = ($msg['sender_id'] == $currentUser);
            $lastId = (int)$msg['message_id'];
        ?>
          <div class="message <?= $isMe ? 'you' : 'them'; ?>">
            <span class="text"><?= htmlspecialchars($msg['message_text']); ?></span>
            <span class="time"><?= htmlspecialchars($msg['time']); ?></span>
          </div>
        <?php endwhile; ?>
      </div>
      <form id="chatForm">
        <input type="text" id="chatInput" placeholder="Type a message..." style="width:80%;" required>
        <button type="submit" class="btn btn-primary" style="width:18%;">Send</button>
      </form>
    </div>
    <script>
      let lastMessageId = <?= $lastId ?? 0 ?>;
      const messagesDiv = document.getElementById('messages');
      const chatForm = document.getElementById('chatForm');
      const chatInput = document.getElementById('chatInput');

      messagesDiv.scrollTop = messagesDiv.scrollHeight;

      chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const text = chatInput.value.trim();
        if (text === "") return;
        fetch('chat.php?uid=<?= $partnerId; ?>', {
          method: 'POST',
          headers: {'Content-Type': 'application/x-www-form-urlencoded'},
          body: 'message=' + encodeURIComponent(text)
        }).then(r=>r.json()).then(data=>{
          if (data.status === 'ok') {
            const msgElem = document.createElement('div');
            msgElem.className = 'message you';
            msgElem.innerHTML = '<span class="text">'+ text.replace(/</g,'&lt;') +'</span><span class="time">now</span>';
            messagesDiv.appendChild(msgElem);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
            chatInput.value = '';
          }
        });
      });

      setInterval(function() {
        fetch('chat.php?uid=<?= $partnerId; ?>&last_id=' + lastMessageId)
          .then(r=>r.json())
          .then(arr=>{
            if (arr.length > 0) {
              arr.forEach(msg => {
                const isMe = (msg.sender_id == <?= $currentUser; ?>);
                const div = document.createElement('div');
                div.className = 'message ' + (isMe ? 'you' : 'them');
                const time = new Date(msg.sent_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                div.innerHTML = '<span class="text">'+ msg.message_text.replace(/</g,'&lt;') +'</span><span class="time">'+time+'</span>';
                messagesDiv.appendChild(div);
                lastMessageId = msg.message_id;
              });
              messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
          });
      }, 5000);
    </script>
  </main>
</body>
</html>
