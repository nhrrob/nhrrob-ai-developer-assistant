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
        <div className="nhrada-app">
            <header className="nhrada-app-header">
                <div className="nhrada-brand">
                    <span className="nhrada-brand-icon"><CodeIcon /></span>
                    <span className="nhrada-brand-name">AI Developer Assistant</span>
                    <span className="nhrada-brand-badge">Beta</span>
                </div>

                <nav className="nhrada-tab-nav" role="tablist">
                    {TABS.map(tab => (
                        <button
                            key={tab.id}
                            role="tab"
                            aria-selected={activeTab === tab.id}
                            className={`nhrada-tab-btn${activeTab === tab.id ? ' nhrada-tab-btn--active' : ''}`}
                            onClick={() => setActiveTab(tab.id)}
                        >
                            <span className="nhrada-tab-icon">{tab.icon}</span>
                            {tab.label}
                        </button>
                    ))}
                </nav>

            </header>

            <main className="nhrada-app-main" role="tabpanel">
                {activeTab === 'chat' && <Chat />}
                {activeTab === 'history' && <History />}
                {activeTab === 'settings' && <Settings />}
            </main>
        </div>
    );
};

export default App;
