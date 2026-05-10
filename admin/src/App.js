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
        <div className="wpad-react-wrap">
            <div className="wpad-topbar">
                <div className="wpad-logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                        <path d="M11.5 2.5L13.8 8.2L19.5 10.5L13.8 12.8L11.5 18.5L9.2 12.8L3.5 10.5L9.2 8.2L11.5 2.5Z"/>
                        <path d="M18.5 16.5L19.5 19L22 20L19.5 21L18.5 23.5L17.5 21L15 20L17.5 19L18.5 16.5Z"/>
                    </svg>
                    <h2>AI Developer</h2>
                </div>
                <ul className="wpad-nav">
                    <li>
                        <button 
                            className={activeTab === 'settings' ? 'active' : ''} 
                            onClick={() => handleTabChange('settings')}
                        >
                            <span className="dashicons dashicons-admin-settings"></span> Settings
                        </button>
                    </li>
                    <li>
                        <button 
                            className={activeTab === 'history' ? 'active' : ''} 
                            onClick={() => handleTabChange('history')}
                        >
                            <span className="dashicons dashicons-backup"></span> History
                        </button>
                    </li>
                </ul>
            </div>
            <div className="wpad-content">
                {activeTab === 'settings' && <Settings />}
                {activeTab === 'history' && <History />}
            </div>
        </div>
    );
};

export default App;
