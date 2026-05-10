import { useState } from '@wordpress/element';
import Settings from './Settings';
import History from './History';
import './style.css'; 

const App = () => {
    const [activeTab, setActiveTab] = useState(window.location.hash === '#history' ? 'history' : 'settings');

    const handleTabChange = (tab) => {
        setActiveTab(tab);
        window.location.hash = tab;
    };

    return (
        <div className="nhraa-react-wrap">
            <header className="nhraa-topbar">
                <div className="nhraa-topbar-left">
                    <div className="nhraa-logo">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11.5 2.5L13.8 8.2L19.5 10.5L13.8 12.8L11.5 18.5L9.2 12.8L3.5 10.5L9.2 8.2L11.5 2.5Z"/>
                            <path d="M18.5 16.5L19.5 19L22 20L19.5 21L18.5 23.5L17.5 21L15 20L17.5 19L18.5 16.5Z"/>
                        </svg>
                        <h2>AI Developer <span>Assistant</span></h2>
                    </div>
                    <nav className="nhraa-nav">
                        <button 
                            className={activeTab === 'settings' ? 'active' : ''} 
                            onClick={() => handleTabChange('settings')}
                        >
                            <span className="dashicons dashicons-admin-settings"></span> Settings
                        </button>
                        <button 
                            className={activeTab === 'history' ? 'active' : ''} 
                            onClick={() => handleTabChange('history')}
                        >
                            <span className="dashicons dashicons-backup"></span> History
                        </button>
                    </nav>
                </div>
                <div className="nhraa-topbar-right">
                    <a href="https://github.com/nhrrob/nhrrob-ai-assistant" target="_blank" className="nhraa-btn-outline">
                        <span className="dashicons dashicons-sos"></span> Help & Support
                    </a>
                    <button className="nhraa-btn-primary">
                        Upgrade to Pro
                    </button>
                </div>
            </header>

            <main className="nhraa-main-layout">
                <div className="nhraa-content-area">
                    {activeTab === 'settings' && <Settings />}
                    {activeTab === 'history' && <History />}
                </div>

                <aside className="nhraa-sidebar-right">
                    <div className="nhraa-widget nhraa-widget-pro">
                        <h3>Unlock Pro Power</h3>
                        <p>Get access to advanced code analysis, unlimited site changes, and premium support.</p>
                        <button className="button button-primary">View Pricing</button>
                    </div>

                    <div className="nhraa-widget">
                        <h3>Quick Links</h3>
                        <ul>
                            <li><a href="#"><span className="dashicons dashicons-external"></span> Documentation</a></li>
                            <li><a href="#"><span className="dashicons dashicons-groups"></span> Community Forum</a></li>
                            <li><a href="#"><span className="dashicons dashicons-star-filled"></span> Leave a Review</a></li>
                        </ul>
                    </div>

                    <div className="nhraa-widget">
                        <h3>Other Plugins</h3>
                        <div className="nhraa-promo-item">
                            <strong>Options Table Manager</strong>
                            <p>Optimize your DB and manage options like a pro.</p>
                            <a href="#">Learn More &rarr;</a>
                        </div>
                    </div>
                </aside>
            </main>

            <footer className="nhraa-footer">
                <p>&copy; {new Date().getFullYear()} NHR AI Developer Assistant. Handcrafted for WordPress developers.</p>
                <div className="nhraa-footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
            </footer>
        </div>
    );
};

export default App;
