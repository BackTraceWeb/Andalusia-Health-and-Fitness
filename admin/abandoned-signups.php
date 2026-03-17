<?php
/**
 * Admin: Abandoned Signups Recovery Tool
 * View and recover signup attempts where payment may have succeeded but completion failed
 */
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: /admin/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abandoned Signups - AHF Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 {
            font-size: 2rem;
            margin-bottom: 8px;
            color: #f8fafc;
        }
        .subtitle {
            color: #94a3b8;
            margin-bottom: 24px;
        }
        .section {
            background: #1e293b;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .section h2 {
            font-size: 1.25rem;
            margin-bottom: 16px;
            color: #f1f5f9;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }
        th {
            text-align: left;
            padding: 12px 8px;
            border-bottom: 2px solid #334155;
            color: #cbd5e1;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        td {
            padding: 12px 8px;
            border-bottom: 1px solid #334155;
        }
        tr:hover {
            background: #293548;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pending {
            background: #f59e0b;
            color: #000;
        }
        .status-completed {
            background: #10b981;
            color: #fff;
        }
        .age {
            color: #94a3b8;
            font-size: 0.75rem;
        }
        .age.critical {
            color: #ef4444;
            font-weight: 600;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
        .btn {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
        }
        .btn:hover {
            background: #2563eb;
        }
        .btn-small {
            padding: 4px 12px;
            font-size: 0.75rem;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3b82f6;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/admin/dashboard.php" class="back-link">← Back to Dashboard</a>

        <h1>Abandoned Signups Recovery</h1>
        <p class="subtitle">Signups that started but didn't complete (may need manual follow-up)</p>

        <div class="section">
            <h2>Pending Signups (Not Completed)</h2>
            <div id="pending-container">
                <div class="loading">Loading...</div>
            </div>
        </div>

        <div class="section">
            <h2>Recently Completed (Last 10)</h2>
            <div id="completed-container">
                <div class="loading">Loading...</div>
            </div>
        </div>
    </div>

    <script>
        async function loadData() {
            try {
                const response = await fetch('/api/admin/get-abandoned-signups.php');
                const data = await response.json();

                if (!data.ok) {
                    throw new Error(data.error || 'Failed to load data');
                }

                renderPending(data.pending);
                renderCompleted(data.completed);
            } catch (error) {
                console.error('Error loading data:', error);
                document.getElementById('pending-container').innerHTML =
                    `<div class="no-data">Error: ${error.message}</div>`;
                document.getElementById('completed-container').innerHTML =
                    `<div class="no-data">Error: ${error.message}</div>`;
            }
        }

        function renderPending(pending) {
            const container = document.getElementById('pending-container');

            if (!pending || pending.length === 0) {
                container.innerHTML = '<div class="no-data">No pending signups found</div>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Created</th>
                            <th>Age</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Plan</th>
                            <th>Monthly Fee</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            pending.forEach(signup => {
                const ageClass = signup.age_minutes > 120 ? 'critical' : '';
                const ageText = signup.age_minutes < 60
                    ? `${signup.age_minutes}m`
                    : `${Math.floor(signup.age_minutes / 60)}h ${signup.age_minutes % 60}m`;

                html += `
                    <tr>
                        <td>${new Date(signup.created_at).toLocaleString()}</td>
                        <td class="age ${ageClass}">${ageText} ago</td>
                        <td>${signup.member_name || '-'}</td>
                        <td>${signup.member_email || '-'}</td>
                        <td>${signup.member_phone || '-'}</td>
                        <td>${signup.membership_plan || '-'}</td>
                        <td>$${parseFloat(signup.monthly_fee || 0).toFixed(2)}</td>
                        <td><span class="status-badge status-pending">${signup.status}</span></td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            container.innerHTML = html;
        }

        function renderCompleted(completed) {
            const container = document.getElementById('completed-container');

            if (!completed || completed.length === 0) {
                container.innerHTML = '<div class="no-data">No completed signups</div>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Created</th>
                            <th>Completed</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            completed.forEach(signup => {
                html += `
                    <tr>
                        <td>${new Date(signup.created_at).toLocaleString()}</td>
                        <td>${new Date(signup.completed_at).toLocaleString()}</td>
                        <td>${signup.member_name || '-'}</td>
                        <td>${signup.member_email || '-'}</td>
                        <td><span class="status-badge status-completed">${signup.status}</span></td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            container.innerHTML = html;
        }

        // Load data on page load
        loadData();

        // Auto-refresh every 30 seconds
        setInterval(loadData, 30000);
    </script>
</body>
</html>
