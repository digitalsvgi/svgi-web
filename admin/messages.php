<?php
// admin/messages.php
require_once __DIR__ . '/../includes/auth_check.php';
check_auth(['admin']);

$page_title = 'Communications Hub';
require_once __DIR__ . '/../includes/header.php';

// Fetch active submissions that have discussion threads or are in queue
$submissions = $pdo->query("
    SELECT s.id, s.title, s.status, s.created_at, c.name as college_name, d.name as dept_name,
           (SELECT COUNT(*) FROM messages WHERE submission_id = s.id) as message_count
    FROM submissions s
    JOIN colleges c ON s.college_id = c.id
    JOIN departments d ON s.department_id = d.id
    ORDER BY s.id DESC
")->fetchAll();
?>

<div class="mb-4">
    <h3 class="fw-bold text-dark">Communications Hub</h3>
    <p class="text-muted">Communicate directly with colleges regarding specific work submissions.</p>
</div>

<div class="row g-4">
    <div class="col-12 col-md-5">
        <div class="card glass-card border-0 shadow-sm">
            <div class="card-header bg-transparent border-0 pt-4 px-4">
                <h5 class="fw-bold text-dark mb-0">Active Channels</h5>
            </div>
            <div class="card-body px-4 pb-4">
                <div class="list-group list-group-flush" style="max-height: 500px; overflow-y: auto;">
                    <?php if (empty($submissions)): ?>
                        <div class="text-center text-muted py-4">No active submissions found.</div>
                    <?php else: ?>
                        <?php foreach ($submissions as $sub): 
                            $taskId = get_task_id($sub['id'], $sub['created_at']);
                        ?>
                            <a href="#" class="list-group-item list-group-item-action border-0 mb-2 rounded p-3 bg-light select-channel-btn" 
                               data-id="<?php echo $sub['id']; ?>"
                               data-task-id="<?php echo $taskId; ?>"
                               data-title="<?php echo htmlspecialchars($sub['title']); ?>"
                               data-college="<?php echo htmlspecialchars($sub['college_name']); ?>">
                                <div class="mb-2"><span class="ref-code-pill"><?php echo $taskId; ?></span></div>
                                <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                    <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($sub['title']); ?></h6>
                                    <span class="badge rounded-pill" style="background-color: #0C4DA2;"><?php echo $sub['message_count']; ?></span>
                                </div>
                                <div class="small text-muted mb-1"><i class="bi bi-bank me-1"></i><?php echo htmlspecialchars($sub['college_name']); ?></div>
                                <span class="badge badge-<?php echo $sub['status']; ?> text-capitalize" style="font-size: 0.75rem;">
                                    <?php echo $sub['status']; ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-md-7">
        <div class="card glass-card border-0 shadow-sm h-100" id="chat_card" style="display: none;">
            <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex justify-content-between align-items-center border-bottom pb-3">
                <div>
                    <div class="mb-2"><span id="channel_task_id" class="ref-code-pill"></span></div>
                    <h5 class="fw-bold text-dark mb-0" id="channel_title">Submission Title</h5>
                    <span class="text-muted small" id="channel_college">Stanford College</span>
                </div>
            </div>
            <div class="card-body px-4 py-3">
                <div id="workspace_chat_history" class="p-3 border rounded mb-3 bg-light" style="height: 350px; overflow-y: auto;">
                    <!-- Message history goes here -->
                </div>
                <form action="submissions.php" method="POST" id="chat_form">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="submission_id" id="workspace_sub_id">
                    <div class="input-group">
                        <textarea class="form-control" name="message_text" placeholder="Type a message..." rows="2" required></textarea>
                        <button type="submit" class="btn btn-primary rounded-pill fw-medium px-4 shadow-sm d-flex align-items-center px-4"><i class="bi bi-send me-1"></i> Send</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card glass-card border-0 shadow-sm h-100 d-flex align-items-center justify-content-center py-5 text-center" id="no_chat_selected">
            <div class="py-5">
                <i class="bi bi-chat-left-dots text-muted display-4 mb-3"></i>
                <h5 class="text-muted">Select a submission channel to view discussion</h5>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const channelButtons = document.querySelectorAll('.select-channel-btn');
    const chatCard = document.getElementById('chat_card');
    const noChatSelected = document.getElementById('no_chat_selected');
    const workspaceChatHistory = document.getElementById('workspace_chat_history');
    
    channelButtons.forEach(btn => {
        btn.addEventListener('click', async (e) => {
            e.preventDefault();
            
            // Mark active
            channelButtons.forEach(b => b.classList.remove('active', 'text-white'));
            btn.classList.add('active');
            
            const id = btn.getAttribute('data-id');
            const taskId = btn.getAttribute('data-task-id');
            const title = btn.getAttribute('data-title');
            const college = btn.getAttribute('data-college');
            
            document.getElementById('workspace_sub_id').value = id;
            document.getElementById('channel_task_id').innerText = taskId;
            document.getElementById('channel_title').innerText = title;
            document.getElementById('channel_college').innerText = "College: " + college;
            
            noChatSelected.style.display = 'none';
            chatCard.style.display = 'block';
            
            workspaceChatHistory.innerHTML = '<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>Loading messages...</div>';
            
            try {
                const response = await fetch(`../ajax/messages.php?submission_id=${id}`);
                const messages = await response.json();
                
                if (messages.length === 0) {
                    workspaceChatHistory.innerHTML = '<div class="text-center text-muted py-5">No messages yet. Send a message to start communicating.</div>';
                } else {
                    workspaceChatHistory.innerHTML = '';
                    messages.forEach(msg => {
                        const isMe = (parseInt(msg.sender_id) === <?php echo $_SESSION['user_id']; ?>);
                        const cardBg = isMe ? 'bg-primary text-white' : 'bg-white border';
                        const wrapperClass = isMe ? 'd-flex flex-column align-items-end mb-3' : 'd-flex flex-column align-items-start mb-3';
                        
                        workspaceChatHistory.innerHTML += `
                            <div class="${wrapperClass}">
                                <div class="small text-muted mb-1">${escapeHtml(msg.sender_name)} (${escapeHtml(msg.sender_role)})</div>
                                <div class="p-2.5 rounded shadow-sm px-3 ${cardBg}" style="max-width: 75%; text-align: left; display: inline-block;">
                                    ${escapeHtml(msg.message_text)}
                                </div>
                                <div class="small text-muted mt-1" style="font-size: 0.75rem;">${escapeHtml(msg.created_at)}</div>
                            </div>
                        `;
                    });
                    workspaceChatHistory.scrollTop = workspaceChatHistory.scrollHeight;
                }
            } catch (err) {
                workspaceChatHistory.innerHTML = '<div class="text-center text-danger py-5">Failed to fetch messages.</div>';
            }
        });
    });
});

function escapeHtml(text) {
    if (!text) return '';
    return text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
