import { render } from '@wordpress/element';
import App from './App';

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('nhraa-admin-app');
    if (root) {
        render(<App />, root);
    }
});
