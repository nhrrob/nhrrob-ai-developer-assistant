import { useState } from '@wordpress/element';
import Chat from './Chat';
import History from './History';
import Settings from './Settings';
import './style.css';

const CodeIcon = () => (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
        <path d="M14.6 16.6l4.6-4.6-4.6-4.6 1.4-1.4 6 6-6 6-1.4-1.4zm-5.2 0L4.8 12l4.6-4.6L8 6 2 12l6 6 1.4-1.4z"/>
        <path d="M9.5 4.5l1.9-.5 3.6 15-1.9.5z"/>
    </svg>
);

const TABS = [
    {
        id: 'chat',
        label: 'Chat',
        icon: (
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
        ),
    },
    {
        id: 'history',
        label: 'History',
        icon: (
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="1 4 1 10 7 10"/>
                <path d="M3.51 15a9 9 0 1 0 .49-4.5"/>
            </svg>
        ),
    },
    {
        id: 'settings',
        label: 'Settings',
        icon: (
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
        ),
    },
];

const App = () => {
    const [activeTab, setActiveTab] = useState('chat');

    return (
        <div className="nhraa-app">
            <header className="nhraa-app-header">
                <div className="nhraa-brand">
                    <span className="nhraa-brand-icon"><CodeIcon /></span>
                    <span className="nhraa-brand-name">AI Developer Assistant</span>
                    <span className="nhraa-brand-badge">Beta</span>
                </div>

                <nav className="nhraa-tab-nav" role="tablist">
                    {TABS.map(tab => (
                        <button
                            key={tab.id}
                            role="tab"
                            aria-selected={activeTab === tab.id}
                            className={`nhraa-tab-btn${activeTab === tab.id ? ' nhraa-tab-btn--active' : ''}`}
                            onClick={() => setActiveTab(tab.id)}
                        >
                            <span className="nhraa-tab-icon">{tab.icon}</span>
                            {tab.label}
                        </button>
                    ))}
                </nav>

                <div className="nhraa-header-end">
                    <button className="nhraa-pro-pill">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        Upgrade to Pro
                    </button>
                </div>
            </header>

            <main className="nhraa-app-main" role="tabpanel">
                {activeTab === 'chat' && <Chat />}
                {activeTab === 'history' && <History />}
                {activeTab === 'settings' && <Settings />}
            </main>
        </div>
    );
};

export default App;
