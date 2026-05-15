import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const Field = ({ label, description, children }) => (
    <div className="nhrada-field">
        <label className="nhrada-field-label">{label}</label>
        {description && <p className="nhrada-field-desc">{description}</p>}
        <div className="nhrada-field-control">{children}</div>
    </div>
);

const Settings = () => {
    const [settings, setSettings] = useState({
        nhrada_ai_provider: 'claude',
        nhrada_claude_api_key: '',
        nhrada_openai_api_key: '',
        nhrada_gemini_api_key: '',
        nhrada_debug_mode: false,
    });
    const [wpAiAvailable, setWpAiAvailable] = useState(false);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState(null);
    const [clearing, setClearing] = useState(false);
    const [showKey, setShowKey] = useState({ claude: false, openai: false, gemini: false });
    const toggleKey = (p) => setShowKey(prev => ({ ...prev, [p]: !prev[p] }));

    useEffect(() => {
        apiFetch({ path: '/nhrada/v1/settings' })
            .then(res => {
                setSettings({
                    nhrada_ai_provider: res.nhrada_ai_provider || 'claude',
                    nhrada_claude_api_key: res.nhrada_claude_api_key || '',
                    nhrada_openai_api_key: res.nhrada_openai_api_key || '',
                    nhrada_gemini_api_key: res.nhrada_gemini_api_key || '',
                    nhrada_debug_mode: !!res.nhrada_debug_mode,
                });
                setWpAiAvailable(!!res.wp_ai_client_available);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, []);

    const showNotice = (type, text) => {
        setNotice({ type, text });
        setTimeout(() => setNotice(null), 5000);
    };

    const handleSave = (e) => {
        e.preventDefault();
        setSaving(true);
        apiFetch({
            path: '/nhrada/v1/settings',
            method: 'POST',
            data: settings,
        }).then(() => {
            setSaving(false);
            showNotice('success', 'Settings saved successfully.');
        }).catch(err => {
            setSaving(false);
            showNotice('error', err.message || 'Failed to save settings.');
        });
    };

    const handleClearHistory = () => {
        if (!window.confirm('Clear all chat messages? This cannot be undone.')) return;
        setClearing(true);
        apiFetch({
            path: '/nhrada/v1/clear-history',
            method: 'POST',
        }).then(() => {
            setClearing(false);
            showNotice('success', 'Chat history cleared.');
        }).catch(err => {
            setClearing(false);
            showNotice('error', err.message || 'Failed to clear history.');
        });
    };

    const set = (key, val) => setSettings(prev => ({ ...prev, [key]: val }));

    if (loading) {
        return (
            <div className="nhrada-panel">
                <div className="nhrada-loading-rows">
                    {[1, 2, 3].map(i => <div key={i} className="nhrada-skeleton-row" />)}
                </div>
            </div>
        );
    }

    return (
        <div className="nhrada-panel">
            <div className="nhrada-panel-header">
                <div>
                    <h1 className="nhrada-panel-title">Settings</h1>
                    <p className="nhrada-panel-desc">Configure your AI Developer Assistant.</p>
                </div>
            </div>

            {notice && (
                <div className={`nhrada-notice nhrada-notice--${notice.type}`}>
                    {notice.text}
                </div>
            )}

            <form onSubmit={handleSave}>
                <div className="nhrada-settings-section">
                    <h2 className="nhrada-section-title">AI Provider</h2>

                    {wpAiAvailable && (
                        <div className="nhrada-notice nhrada-notice--success">
                            WordPress AI is active — no API key required. The fields below are used as a fallback if WordPress AI becomes unavailable.
                        </div>
                    )}

                    <Field
                        label="AI Provider"
                        description="Choose which AI service powers the assistant. Select a provider then enter its API key below."
                    >
                        <select
                            className="nhrada-input-text"
                            value={settings.nhrada_ai_provider}
                            onChange={e => set('nhrada_ai_provider', e.target.value)}
                        >
                            <option value="claude">Claude (Anthropic) — paid</option>
                            <option value="openai">ChatGPT (OpenAI) — paid</option>
                            <option value="gemini">Gemini (Google) — free tier available</option>
                        </select>
                    </Field>

                    {settings.nhrada_ai_provider === 'claude' && (
                        <Field
                            label="Anthropic API Key"
                            description="Get your key at console.anthropic.com → API Keys."
                        >
                            <div className="nhrada-password-wrap">
                                <input
                                    type={showKey.claude ? 'text' : 'password'}
                                    className="nhrada-input-text"
                                    value={settings.nhrada_claude_api_key}
                                    onChange={e => set('nhrada_claude_api_key', e.target.value)}
                                    placeholder="sk-ant-api03-…"
                                    autoComplete="new-password"
                                />
                                <button type="button" className="nhrada-toggle-key" onClick={() => toggleKey('claude')}>
                                    {showKey.claude ? 'Hide' : 'Show'}
                                </button>
                            </div>
                        </Field>
                    )}

                    {settings.nhrada_ai_provider === 'openai' && (
                        <Field
                            label="OpenAI API Key"
                            description="Get your key at platform.openai.com → API Keys. Uses gpt-4o-mini (low cost)."
                        >
                            <div className="nhrada-password-wrap">
                                <input
                                    type={showKey.openai ? 'text' : 'password'}
                                    className="nhrada-input-text"
                                    value={settings.nhrada_openai_api_key}
                                    onChange={e => set('nhrada_openai_api_key', e.target.value)}
                                    placeholder="sk-…"
                                    autoComplete="new-password"
                                />
                                <button type="button" className="nhrada-toggle-key" onClick={() => toggleKey('openai')}>
                                    {showKey.openai ? 'Hide' : 'Show'}
                                </button>
                            </div>
                        </Field>
                    )}

                    {settings.nhrada_ai_provider === 'gemini' && (
                        <Field
                            label="Google Gemini API Key"
                            description="Get your free key at aistudio.google.com → Get API Key. Uses gemini-2.0-flash (free tier)."
                        >
                            <div className="nhrada-password-wrap">
                                <input
                                    type={showKey.gemini ? 'text' : 'password'}
                                    className="nhrada-input-text"
                                    value={settings.nhrada_gemini_api_key}
                                    onChange={e => set('nhrada_gemini_api_key', e.target.value)}
                                    placeholder="AIza…"
                                    autoComplete="new-password"
                                />
                                <button type="button" className="nhrada-toggle-key" onClick={() => toggleKey('gemini')}>
                                    {showKey.gemini ? 'Hide' : 'Show'}
                                </button>
                            </div>
                        </Field>
                    )}
                </div>

                <div className="nhrada-settings-section">
                    <h2 className="nhrada-section-title">Developer Options</h2>

                    <Field label="Debug Mode" description="Enable additional logging in the PHP error log for troubleshooting.">
                        <label className="nhrada-toggle">
                            <input
                                type="checkbox"
                                checked={settings.nhrada_debug_mode}
                                onChange={e => set('nhrada_debug_mode', e.target.checked)}
                            />
                            <span className="nhrada-toggle-track" />
                            <span className="nhrada-toggle-label">{settings.nhrada_debug_mode ? 'Enabled' : 'Disabled'}</span>
                        </label>
                    </Field>
                </div>

                <div className="nhrada-settings-actions">
                    <button type="submit" className="nhrada-btn-primary" disabled={saving}>
                        {saving ? 'Saving…' : 'Save Settings'}
                    </button>
                </div>
            </form>

            <div className="nhrada-settings-section nhrada-settings-section--danger">
                <h2 className="nhrada-section-title nhrada-section-title--danger">Danger Zone</h2>
                <div className="nhrada-danger-row">
                    <div>
                        <strong>Clear Chat History</strong>
                        <p>Permanently delete all chat messages. Applied changes are not affected.</p>
                    </div>
                    <button
                        type="button"
                        className="nhrada-btn-danger"
                        onClick={handleClearHistory}
                        disabled={clearing}
                    >
                        {clearing ? 'Clearing…' : 'Clear History'}
                    </button>
                </div>
            </div>

        </div>
    );
};

export default Settings;
