import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const History = () => {
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        apiFetch({ path: '/nhraa/v1/history' }).then((res) => {
            setHistory(res);
            setLoading(false);
        }).catch((err) => {
            console.error(err);
            setLoading(false);
        });
    }, []);

    if (loading) return <p>Loading history...</p>;

    return (
        <div className="nhraa-history-view">
            <h1>Change History</h1>
            <table className="wp-list-table widefat fixed striped table-view-list">
                <thead>
                    <tr>
                        <th style={{ width: '150px' }}>Date</th>
                        <th>Request</th>
                        <th>Description</th>
                        <th style={{ width: '100px' }}>Status</th>
                    </tr>
                </thead>
                <tbody>
                    {history.length === 0 ? (
                        <tr><td colSpan="4">No changes found.</td></tr>
                    ) : (
                        history.map((item) => (
                            <tr key={item.id}>
                                <td>{item.created_at}</td>
                                <td>{item.request}</td>
                                <td>{item.description}</td>
                                <td>
                                    <span className={`nhraa-status nhraa-status-${item.status}`}>
                                        {item.status}
                                    </span>
                                </td>
                            </tr>
                        ))
                    )}
                </tbody>
            </table>
        </div>
    );
};

export default History;
