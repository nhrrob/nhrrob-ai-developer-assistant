import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const MAX_CHARS = 1000;

const SUGGESTIONS = [
    'Make my site header sticky when scrolling',
    'Add a floating WhatsApp button in the bottom right',
    'Change the primary button color to #e63946',
];

const CodeIcon = () => (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
        <path d="M14.6 16.6l4.6-4.6-4.6-4.6 1.4-1.4 6 6-6 6-1.4-1.4zm-5.2 0L4.8 12l4.6-4.6L8 6 2 12l6 6 1.4-1.4z"/>
        <path d="M9.5 4.5l1.9-.5 3.6 15-1.9.5z"/>
    </svg>
);

const SendIcon = () => (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
        <line x1="22" y1="2" x2="11" y2="13"/>
        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
    </svg>
);

const UndoIcon = () => (
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
        <polyline points="1 4 1 10 7 10"/>
        <path d="M3.51 15a9 9 0 1 0 .49-4.5"/>
    </svg>
);

const Chat = () => {
    const [messages, setMessages] = useState([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [initialLoading, setInitialLoading] = useState(true);
    const [usageInfo, setUsageInfo] = useState(null);
    const bottomRef = useRef(null);
    const inputRef = useRef(null);
    const messagesAreaRef = useRef(null);

    useEffect(() => {
        apiFetch({ path: '/nhraa/v1/messages' })
            .then(res => {
                setMessages(res.messages || []);
                setUsageInfo(res.usage || null);
                setInitialLoading(false);
            })
            .catch(() => setInitialLoading(false));
    }, []);

    useEffect(() => {
        if (bottomRef.current) {
            bottomRef.current.scrollIntoView({ behavior: 'smooth' });
        }
    }, [messages, loading]);

    const sendMessage = useCallback(() => {
        const text = input.trim();
        if (!text || text.length > MAX_CHARS || loading) return;

        const userMsg = { role: 'user', content: text, id: `user-${Date.now()}` };
        setInput('');
        setMessages(prev => [...prev, userMsg]);
        setLoading(true);

        apiFetch({
            path: '/nhraa/v1/chat',
            method: 'POST',
            data: { message: text },
        }).then(res => {
            setLoading(false);

            if (res.upgrade_required) {
                setMessages(prev => [...prev, {
                    role: 'assistant',
                    content: res.message,
                    id: `assistant-${Date.now()}`,
                    upgrade_required: true,
                }]);
                return;
            }

            const displayMsg = res.confirmation_message || res.message || 'Done.';
            setMessages(prev => [...prev, {
                role: 'assistant',
                content: displayMsg,
                id: `assistant-${Date.now()}`,
                change_id: res.change_id || null,
                warnings: res.warnings || null,
                is_error: !res.confirmation_message && !!res.message,
            }]);

            if (res.usage) setUsageInfo(res.usage);
            setTimeout(() => inputRef.current?.focus(), 50);
        }).catch(() => {
            setLoading(false);
            setMessages(prev => [...prev, {
                role: 'assistant',
                content: 'Network error. Please check your connection and try again.',
                id: `error-${Date.now()}`,
                is_error: true,
            }]);
        });
    }, [input, loading]);

    const handleUndo = useCallback((changeId, msgId) => {
        apiFetch({
            path: '/nhraa/v1/undo',
            method: 'POST',
            data: { change_id: changeId },
        }).then(res => {
            if (res.success) {
                setMessages(prev => prev.map(m =>
                    m.id === msgId ? { ...m, undone: true } : m
                ));
                setMessages(prev => [...prev, {
                    role: 'assistant',
                    content: res.message,
                    id: `undo-${Date.now()}`,
                }]);
            } else {
                alert('Undo failed: ' + (res.error || 'Unknown error'));
            }
        }).catch(() => {
            alert('Network error during undo. Please try again.');
        });
    }, []);

    const handleKeyDown = (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    };

    const handleSuggestion = (text) => {
        setInput(text);
        setTimeout(() => inputRef.current?.focus(), 50);
    };

    const charCount = input.length;
    const overLimit = charCount > MAX_CHARS;
    const canSend = input.trim().length > 0 && !overLimit && !loading;

    if (initialLoading) {
        return (
            <div className="nhraa-chat-init">
                <div className="nhraa-spinner-ring" />
                <p>Loading conversation…</p>
            </div>
        );
    }

    return (
        <div className="nhraa-chat-page">
            <div className="nhraa-messages-scroll" ref={messagesAreaRef}>
                <div className="nhraa-messages-inner">

                    {messages.length === 0 && (
                        <div className="nhraa-empty-state">
                            <div className="nhraa-empty-icon">
                                <CodeIcon />
                            </div>
                            <h2>Your AI Developer is ready</h2>
                            <p>Describe a change in plain English and I'll implement it on your site — no coding needed.</p>
                            <div className="nhraa-suggestion-chips">
                                {SUGGESTIONS.map(s => (
                                    <button key={s} className="nhraa-chip" onClick={() => handleSuggestion(s)}>
                                        {s}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    {messages.map((msg, index) => (
                        <div
                            key={msg.id || index}
                            className={`nhraa-msg nhraa-msg--${msg.role}${msg.is_error ? ' nhraa-msg--error' : ''}`}
                        >
                            {msg.role === 'assistant' && (
                                <div className="nhraa-msg-avatar" aria-hidden>
                                    <CodeIcon />
                                </div>
                            )}
                            <div className="nhraa-msg-body">
                                <div className="nhraa-msg-bubble">
                                    <p>{msg.content}</p>
                                    {msg.warnings && (
                                        <p className="nhraa-msg-warning">
                                            <span>⚠</span> {msg.warnings}
                                        </p>
                                    )}
                                </div>
                                {msg.upgrade_required && (
                                    <a href="#" className="nhraa-upgrade-cta">
                                        Upgrade to Pro — unlimited requests for $9/mo →
                                    </a>
                                )}
                                {msg.change_id && !msg.undone && (
                                    <button
                                        className="nhraa-undo-btn"
                                        onClick={() => handleUndo(msg.change_id, msg.id)}
                                    >
                                        <UndoIcon /> Undo this change
                                    </button>
                                )}
                                {msg.undone && (
                                    <span className="nhraa-undone-tag">Undone</span>
                                )}
                            </div>
                        </div>
                    ))}

                    {loading && (
                        <div className="nhraa-msg nhraa-msg--assistant">
                            <div className="nhraa-msg-avatar" aria-hidden>
                                <CodeIcon />
                            </div>
                            <div className="nhraa-msg-body">
                                <div className="nhraa-msg-bubble nhraa-typing-bubble">
                                    <span className="nhraa-dot" />
                                    <span className="nhraa-dot" />
                                    <span className="nhraa-dot" />
                                </div>
                            </div>
                        </div>
                    )}

                    <div ref={bottomRef} />
                </div>
            </div>

            <div className="nhraa-input-zone">
                {usageInfo && usageInfo.plan === 'free' && (
                    <div className="nhraa-usage-strip">
                        <div
                            className="nhraa-usage-bar"
                            style={{ width: `${Math.min(100, (usageInfo.used / usageInfo.limit) * 100)}%` }}
                        />
                        <span>{usageInfo.used} of {usageInfo.limit} free requests used this month</span>
                    </div>
                )}
                <div className="nhraa-input-row">
                    <div className={`nhraa-input-wrap${overLimit ? ' nhraa-input-wrap--over' : ''}`}>
                        <textarea
                            ref={inputRef}
                            className="nhraa-textarea"
                            placeholder="E.g., Make my header sticky when scrolling…"
                            value={input}
                            onChange={e => setInput(e.target.value)}
                            onKeyDown={handleKeyDown}
                            rows="1"
                            disabled={loading}
                        />
                        {charCount > MAX_CHARS * 0.8 && (
                            <span className={`nhraa-counter${overLimit ? ' nhraa-counter--over' : ''}`}>
                                {charCount}/{MAX_CHARS}
                            </span>
                        )}
                    </div>
                    <button
                        className={`nhraa-send-btn${canSend ? ' nhraa-send-btn--active' : ''}`}
                        onClick={sendMessage}
                        disabled={!canSend}
                        aria-label="Send message"
                    >
                        {loading ? (
                            <span className="nhraa-send-spinner" />
                        ) : (
                            <SendIcon />
                        )}
                    </button>
                </div>
                <p className="nhraa-input-hint">Enter to send · Shift+Enter for new line</p>
            </div>
        </div>
    );
};

export default Chat;
