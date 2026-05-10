import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const UndoIcon = () => (
    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
        <polyline points="1 4 1 10 7 10"/>
        <path d="M3.51 15a9 9 0 1 0 .49-4.5"/>
    </svg>
);

const TYPE_COLORS = {
    css: { bg: '#dbeafe', color: '#1d4ed8', label: 'CSS' },
    js: { bg: '#fef9c3', color: '#854d0e', label: 'JS' },
    php: { bg: '#f3e8ff', color: '#7c3aed', label: 'PHP' },
    option: { bg: '#dcfce7', color: '#166534', label: 'Option' },
    none: { bg: '#f1f5f9', color: '#475569', label: 'None' },
};

const TypeBadge = ({ type }) => {
    const cfg = TYPE_COLORS[type] || TYPE_COLORS.none;
    return (
        <span className="nhraa-type-badge" style={{ background: cfg.bg, color: cfg.color }}>
            {cfg.label}
        </span>
    );
};

const StatusBadge = ({ status }) => (
    <span className={`nhraa-status-badge nhraa-status-badge--${status}`}>
        {status === 'applied' ? 'Applied' : 'Undone'}
    </span>
);

const TARGET_LABELS = {
    'custom-css':  'Appearance → Custom CSS',
    'custom-js':   'Footer JS snippet',
    'functions-snippet': 'PHP snippets file',
};

const formatTarget = (target) => TARGET_LABELS[target] || (target ? `Option: ${target}` : '—');

const formatDate = (dateStr) => {
    if (!dateStr) return '—';
    try {
        return new Date(dateStr).toLocaleString(undefined, {
            month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit',
        });
    } catch {
        return dateStr;
    }
};

const CodeBlock = ({ code }) => {
    const [open, setOpen] = useState(false);
    if (!code) return <span className="nhraa-col-done">—</span>;
    return (
        <div>
            <button type="button" className="nhraa-code-toggle" onClick={() => setOpen(v => !v)}>
                {open ? 'Hide code' : 'View code'}
            </button>
            {open && <pre className="nhraa-code-preview">{code}</pre>}
        </div>
    );
};

const History = () => {
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [undoingId, setUndoingId] = useState(null);
    const [notice, setNotice] = useState(null);

    const loadHistory = useCallback(() => {
        setLoading(true);
        apiFetch({ path: '/nhraa/v1/history' })
            .then(res => {
                setItems(res || []);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, []);

    useEffect(() => { loadHistory(); }, [loadHistory]);

    const handleUndo = useCallback((changeId) => {
        if (!window.confirm('Undo this change? Your site will be restored to its state before this change.')) return;

        setUndoingId(changeId);
        apiFetch({
            path: '/nhraa/v1/undo',
            method: 'POST',
            data: { change_id: changeId },
        }).then(res => {
            setUndoingId(null);
            if (res.success) {
                setItems(prev => prev.map(item =>
                    item.id == changeId ? { ...item, status: 'undone' } : item
                ));
                setNotice({ type: 'success', text: res.message });
            } else {
                setNotice({ type: 'error', text: res.error || 'Undo failed.' });
            }
            setTimeout(() => setNotice(null), 5000);
        }).catch(() => {
            setUndoingId(null);
            setNotice({ type: 'error', text: 'Network error. Please try again.' });
            setTimeout(() => setNotice(null), 5000);
        });
    }, []);

    return (
        <div className="nhraa-panel">
            <div className="nhraa-panel-header">
                <div>
                    <h1 className="nhraa-panel-title">Change History</h1>
                    <p className="nhraa-panel-desc">Every change your AI developer has made to your site.</p>
                </div>
                <button className="nhraa-btn-ghost" onClick={loadHistory} disabled={loading}>
                    {loading ? 'Refreshing…' : 'Refresh'}
                </button>
            </div>

            {notice && (
                <div className={`nhraa-notice nhraa-notice--${notice.type}`}>
                    {notice.text}
                </div>
            )}

            {loading ? (
                <div className="nhraa-loading-rows">
                    {[1, 2, 3].map(i => <div key={i} className="nhraa-skeleton-row" />)}
                </div>
            ) : items.length === 0 ? (
                <div className="nhraa-empty-panel">
                    <p>No changes have been made yet. Go to the Chat tab and ask your AI developer to make a change.</p>
                </div>
            ) : (
                <div className="nhraa-history-table-wrap">
                    <table className="nhraa-history-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Request</th>
                                <th>Description</th>
                                <th>Type</th>
                                <th>Applied To</th>
                                <th>Code</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            {items.map(item => (
                                <tr key={item.id} className={item.status === 'undone' ? 'nhraa-row--undone' : ''}>
                                    <td className="nhraa-col-date">{formatDate(item.created_at)}</td>
                                    <td className="nhraa-col-request">
                                        <span className="nhraa-truncate" title={item.request}>{item.request}</span>
                                    </td>
                                    <td className="nhraa-col-desc">
                                        <span className="nhraa-truncate" title={item.description}>{item.description}</span>
                                    </td>
                                    <td><TypeBadge type={item.change_type} /></td>
                                    <td className="nhraa-col-target">{formatTarget(item.file_target)}</td>
                                    <td><CodeBlock code={item.code} /></td>
                                    <td><StatusBadge status={item.status} /></td>
                                    <td>
                                        {item.status === 'applied' ? (
                                            <button
                                                className="nhraa-undo-btn-sm"
                                                onClick={() => handleUndo(item.id)}
                                                disabled={undoingId === item.id}
                                            >
                                                {undoingId === item.id ? 'Undoing…' : (
                                                    <><UndoIcon /> Undo</>
                                                )}
                                            </button>
                                        ) : (
                                            <span className="nhraa-col-done">—</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
};

export default History;
