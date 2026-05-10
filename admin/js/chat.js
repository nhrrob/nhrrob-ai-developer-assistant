document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('nhraa-chat-toggle');
    const closeBtn = document.getElementById('nhraa-chat-close');
    const chatWindow = document.getElementById('nhraa-chat-window');

    const input = document.getElementById('nhraa-chat-input');
    const sendBtn = document.getElementById('nhraa-chat-send');
    const history = document.getElementById('nhraa-chat-history');
    const counter = document.getElementById('nhraa-char-count');
    const MAX_CHARS = 1000;

    if (!input || !sendBtn || !history || !counter) return;

    // Toggle logic
    if (toggleBtn && chatWindow) {
        toggleBtn.addEventListener('click', function() {
            chatWindow.classList.toggle('nhraa-hidden');
            if (!chatWindow.classList.contains('nhraa-hidden')) {
                input.focus();
                history.scrollTop = history.scrollHeight;
            }
        });
    }

    if (closeBtn && chatWindow) {
        closeBtn.addEventListener('click', function() {
            chatWindow.classList.add('nhraa-hidden');
        });
    }

    // Create loading indicator
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'nhraa-loading-indicator';
    loadingIndicator.textContent = 'Thinking...';
    history.appendChild(loadingIndicator);

    // Character counter
    input.addEventListener('input', function() {
        const len = input.value.length;
        counter.textContent = `${len} / ${MAX_CHARS}`;
        if (len > MAX_CHARS) {
            counter.style.color = '#d63638';
            sendBtn.disabled = true;
        } else {
            counter.style.color = '#646970';
            sendBtn.disabled = false;
        }
    });

    // Enter to send
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });

    sendBtn.addEventListener('click', function(e) {
        e.preventDefault();
        sendMessage();
    });

    function appendMessage(role, text, changeId = null) {
        const wrapper = document.createElement('div');
        wrapper.className = `nhraa-message nhraa-${role}`;
        
        const content = document.createElement('div');
        content.className = 'nhraa-message-content';
        
        const p = document.createElement('p');
        p.textContent = text;
        
        content.appendChild(p);

        if (changeId) {
            const undoBtn = document.createElement('button');
            undoBtn.className = 'button button-small nhraa-undo-btn';
            undoBtn.style.marginTop = '10px';
            undoBtn.textContent = 'Undo';
            undoBtn.dataset.changeId = changeId;
            
            undoBtn.addEventListener('click', function(e) {
                e.preventDefault();
                handleUndo(changeId, undoBtn, wrapper);
            });
            content.appendChild(undoBtn);
        }

        wrapper.appendChild(content);
        
        // Insert before loading indicator
        history.insertBefore(wrapper, loadingIndicator);
        history.scrollTop = history.scrollHeight;
    }

    function handleUndo(changeId, btn, wrapper) {
        btn.disabled = true;
        btn.textContent = 'Undoing...';

        fetch(nhraaChatData.undoUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nhraaChatData.nonce
            },
            body: JSON.stringify({ change_id: changeId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                btn.textContent = 'Undone';
                btn.classList.add('disabled');
                appendMessage('assistant', data.message);
            } else {
                btn.disabled = false;
                btn.textContent = 'Undo';
                alert('Undo failed: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            btn.disabled = false;
            btn.textContent = 'Undo';
            alert('Network error. Please try again.');
        });
    }

    function sendMessage() {
        const message = input.value.trim();
        if (!message || message.length > MAX_CHARS) return;

        // Disable input
        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;
        
        // Append user message
        appendMessage('user', message);
        
        // Show loading
        loadingIndicator.style.display = 'block';
        history.scrollTop = history.scrollHeight;

        // Send API request
        fetch(nhraaChatData.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': nhraaChatData.nonce
            },
            body: JSON.stringify({ message: message })
        })
        .then(response => response.json())
        .then(data => {
            loadingIndicator.style.display = 'none';
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
            
            if (data.confirmation_message) {
                appendMessage('assistant', data.confirmation_message, data.change_id);
            } else if (data.message) {
                appendMessage('assistant', 'Error: ' + data.message);
            } else {
                appendMessage('assistant', 'An unknown error occurred.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            loadingIndicator.style.display = 'none';
            input.disabled = false;
            sendBtn.disabled = false;
            appendMessage('assistant', 'Network error. Please try again.');
        });
    }
});
