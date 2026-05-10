import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const Settings = () => {
    const [settings, setSettings] = useState({ nhraa_licence_key: '', nhraa_claude_api_key: '' });
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [message, setMessage] = useState(null);

    useEffect(() => {
        apiFetch({ path: '/nhraa/v1/settings' }).then((res) => {
            setSettings({
                nhraa_licence_key: res.nhraa_licence_key || '',
                nhraa_claude_api_key: res.nhraa_claude_api_key || '',
            });
            setLoading(false);
        }).catch((err) => {
            console.error(err);
            setLoading(false);
        });
    }, []);

    const handleSave = (e) => {
        e.preventDefault();
        setSaving(true);
        setMessage(null);

        apiFetch({
            path: '/nhraa/v1/settings',
            method: 'POST',
            data: settings,
        }).then((res) => {
            setSaving(false);
            setMessage({ type: 'success', text: 'Settings saved successfully.' });
        }).catch((err) => {
            setSaving(false);
            setMessage({ type: 'error', text: err.message || 'Failed to save settings.' });
        });
    };

    if (loading) return <p>Loading settings...</p>;

    return (
        <div className="nhraa-settings-view">
            <h1>Settings</h1>
            {message && (
                <div className={`nhraa-notice nhraa-${message.type}`}>
                    <p>{message.text}</p>
                </div>
            )}
            <form onSubmit={handleSave} className="nhraa-form">
                <div className="nhraa-form-group">
                    <label>Pro Licence Key</label>
                    <input 
                        type="text" 
                        value={settings.nhraa_licence_key} 
                        onChange={(e) => setSettings({...settings, nhraa_licence_key: e.target.value})} 
                        className="regular-text"
                    />
                    <p className="description">Enter your NHR AI Developer Pro licence key here.</p>
                </div>

                <div className="nhraa-form-group">
                    <label>Claude API Key (Bring Your Own)</label>
                    <input 
                        type="password" 
                        value={settings.nhraa_claude_api_key} 
                        onChange={(e) => setSettings({...settings, nhraa_claude_api_key: e.target.value})} 
                        className="regular-text"
                    />
                    <p className="description">Enter your Anthropic API key to bypass the proxy and use the AI directly.</p>
                </div>

                <button type="submit" className="button button-primary" disabled={saving}>
                    {saving ? 'Saving...' : 'Save Settings'}
                </button>
            </form>
        </div>
    );
};

export default Settings;
