import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const Field = ({ label, description, children }) => (
    <div className="nhraa-field">
        <label className="nhraa-field-label">{label}</label>
        {description && <p className="nhraa-field-desc">{description}</p>}
        <div className="nhraa-field-control">{children}</div>
    </div>
);

const Settings = () => {
    const [settings, setSettings] = useState({
        nhraa_licence_key: '',
        nhraa_ai_provider: 'claude',
        nhraa_claude_api_key: '',
        nhraa_openai_api_key: '',
        nhraa_gemini_api_key: '',
        nhraa_debug_mode: false,
    });
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [notice, setNotice] = useState(null);
    const [clearing, setClearing] = useState(false);
    const [showKey, setShowKey] = useState({ claude: false, openai: false, gemini: false });
    const toggleKey = (p) => setShowKey(prev => ({ ...prev, [p]: !prev[p] }));

    useEffect(() => {
        apiFetch({ path: '/nhraa/v1/settings' })
            .then(res => {
                setSettings({
                    nhraa_licence_key: res.nhraa_licence_key || '',
                    nhraa_ai_provider: res.nhraa_ai_provider || 'claude',
                    nhraa_claude_api_key: res.nhraa_claude_api_key || '',
                    nhraa_openai_api_key: res.nhraa_openai_api_key || '',
                    nhraa_gemini_api_key: res.nhraa_gemini_api_key || '',
                    nhraa_debug_mode: !!res.nhraa_debug_mode,
                });
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
            path: '/nhraa/v1/settings',
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
            path: '/nhraa/v1/clear-history',
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
            <div className="nhraa-panel">
                <div className="nhraa-loading-rows">
                    {[1, 2, 3].map(i => <div key={i} className="nhraa-skeleton-row" />)}
                </div>
            </div>
        );
    }

    return (
        <div className="nhraa-panel">
            <div className="nhraa-panel-header">
                <div>
                    <h1 className="nhraa-panel-title">Settings</h1>
                    <p className="nhraa-panel-desc">Configure your AI Developer Assistant.</p>
                </div>
            </div>

            {notice && (
                <div className={`nhraa-notice nhraa-notice--${notice.type}`}>
                    {notice.text}
                </div>
            )}

            <form onSubmit={handleSave}>
                <div className="nhraa-settings-section">
                    <h2 className="nhraa-section-title">Licence & API</h2>

                    <Field
                        label="Pro Licence Key"
                        description="Enter your NHR AI Developer Pro licence key to unlock unlimited requests and all change types."
                    >
                        <input
                            type="text"
                            className="nhraa-input-text"
                            value={settings.nhraa_licence_key}
                            onChange={e => set('nhraa_licence_key', e.target.value)}
                            placeholder="XXXX-XXXX-XXXX-XXXX"
                            autoComplete="off"
                        />
                    </Field>

                    <Field
                        label="AI Provider"
                        description="Choose which AI service powers the assistant. Select a provider then enter its API key below."
                    >
                        <select
                            className="nhraa-input-text"
                            value={settings.nhraa_ai_provider}
                            onChange={e => set('nhraa_ai_provider', e.target.value)}
                        >
                            <option value="claude">Claude (Anthropic) — paid</option>
                            <option value="openai">ChatGPT (OpenAI) — paid</option>
                            <option value="gemini">Gemini (Google) — free tier available</option>
                        </select>
                    </Field>

                    {settings.nhraa_ai_provider === 'claude' && (
                        <Field
                            label="Anthropic API Key"
                            description="Get your key at console.anthropic.com → API Keys."
                        >
                            <div className="nhraa-password-wrap">
                                <input
                                    type={showKey.claude ? 'text' : 'password'}
                                    className="nhraa-input-text"
                                    value={settings.nhraa_claude_api_key}
                                    onChange={e => set('nhraa_claude_api_key', e.target.value)}
                                    placeholder="sk-ant-api03-…"
                                    autoComplete="new-password"
                                />
                                <button type="button" className="nhraa-toggle-key" onClick={() => toggleKey('claude')}>
                                    {showKey.claude ? 'Hide' : 'Show'}
                                </button>
                            </div>
                        </Field>
                    )}

                    {settings.nhraa_ai_provider === 'openai' && (
                        <Field
                            label="OpenAI API Key"
                            description="Get your key at platform.openai.com → API Keys. Uses gpt-4o-mini (low cost)."
                        >
                            <div className="nhraa-password-wrap">
                                <input
                                    type={showKey.openai ? 'text' : 'password'}
                                    className="nhraa-input-text"
                                    value={settings.nhraa_openai_api_key}
                                    onChange={e => set('nhraa_openai_api_key', e.target.value)}
                                    placeholder="sk-…"
                                    autoComplete="new-password"
                                />
                                <button type="button" className="nhraa-toggle-key" onClick={() => toggleKey('openai')}>
                                    {showKey.openai ? 'Hide' : 'Show'}
                                </button>
                            </div>
                        </Field>
                    )}

                    {settings.nhraa_ai_provider === 'gemini' && (
                        <Field
                            label="Google Gemini API Key"
                            description="Get your free key at aistudio.google.com → Get API Key. Uses gemini-2.0-flash (free tier)."
                        >
                            <div className="nhraa-password-wrap">
                                <input
                                    type={showKey.gemini ? 'text' : 'password'}
                                    className="nhraa-input-text"
                                    value={settings.nhraa_gemini_api_key}
                                    onChange={e => set('nhraa_gemini_api_key', e.target.value)}
                                    placeholder="AIza…"
                                    autoComplete="new-password"
                                />
                                <button type="button" className="nhraa-toggle-key" onClick={() => toggleKey('gemini')}>
                                    {showKey.gemini ? 'Hide' : 'Show'}
                                </button>
                            </div>
                        </Field>
                    )}
                </div>

                <div className="nhraa-settings-section">
                    <h2 className="nhraa-section-title">Developer Options</h2>

                    <Field label="Debug Mode" description="Enable additional logging in the PHP error log for troubleshooting.">
                        <label className="nhraa-toggle">
                            <input
                                type="checkbox"
                                checked={settings.nhraa_debug_mode}
                                onChange={e => set('nhraa_debug_mode', e.target.checked)}
                            />
                            <span className="nhraa-toggle-track" />
                            <span className="nhraa-toggle-label">{settings.nhraa_debug_mode ? 'Enabled' : 'Disabled'}</span>
                        </label>
                    </Field>
                </div>

                <div className="nhraa-settings-actions">
                    <button type="submit" className="nhraa-btn-primary" disabled={saving}>
                        {saving ? 'Saving…' : 'Save Settings'}
                    </button>
                </div>
            </form>

            <div className="nhraa-settings-section nhraa-settings-section--danger">
                <h2 className="nhraa-section-title nhraa-section-title--danger">Danger Zone</h2>
                <div className="nhraa-danger-row">
                    <div>
                        <strong>Clear Chat History</strong>
                        <p>Permanently delete all chat messages. Applied changes are not affected.</p>
                    </div>
                    <button
                        type="button"
                        className="nhraa-btn-danger"
                        onClick={handleClearHistory}
                        disabled={clearing}
                    >
                        {clearing ? 'Clearing…' : 'Clear History'}
                    </button>
                </div>
            </div>

            <div className="nhraa-settings-section">
                <h2 className="nhraa-section-title">About</h2>
                <div className="nhraa-about-grid">
                    <div className="nhraa-about-card">
                        <strong>Free Plan</strong>
                        <ul>
                            <li>10 AI requests / month</li>
                            <li>CSS &amp; JS changes</li>
                            <li>Undo any change</li>
                        </ul>
                    </div>
                    <div className="nhraa-about-card nhraa-about-card--pro">
                        <strong>Pro Plan — $9/mo</strong>
                        <ul>
                            <li>Unlimited requests</li>
                            <li>CSS, JS, PHP &amp; option changes</li>
                            <li>Full change history</li>
                            <li>Bring your own API key</li>
                            <li>Priority support</li>
                        </ul>
                        <a href="#" className="nhraa-btn-primary nhraa-btn-sm">Upgrade Now</a>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Settings;
