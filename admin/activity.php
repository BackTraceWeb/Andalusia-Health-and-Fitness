<?php
/**
 * Admin: Payment & Signup Activity
 * View all QuickPay payments and new signups in separate tabs
 */
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: /admin/index.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - AHF Admin</title>
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
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3b82f6;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 2px solid #334155;
        }
        .tab {
            padding: 12px 24px;
            background: transparent;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
        }
        .tab:hover {
            color: #e2e8f0;
            background: #1e293b;
        }
        .tab.active {
            color: #3b82f6;
            border-bottom-color: #3b82f6;
        }

        /* Content */
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }

        .section {
            background: #1e293b;
            border-radius: 8px;
            padding: 20px;
        }

        .stats {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            flex: 1;
            background: #0f172a;
            padding: 16px;
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        .stat-label {
            font-size: 0.875rem;
            color: #94a3b8;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #f8fafc;
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

        .type-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .type-quickpay {
            background: #10b981;
            color: #fff;
        }
        .type-signup {
            background: #3b82f6;
            color: #fff;
        }

        .amount {
            font-weight: 600;
            color: #10b981;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #64748b;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #94a3b8;
        }

        .limit-selector {
            margin-bottom: 16px;
        }
        .limit-selector label {
            margin-right: 8px;
            color: #cbd5e1;
        }
        .limit-selector select {
            background: #0f172a;
            color: #e2e8f0;
            border: 1px solid #334155;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="/admin/dashboard.php" class="back-link">← Back to Dashboard</a>

        <h1>Activity Log</h1>
        <p class="subtitle">All QuickPay payments and new member signups</p>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('quickpays')">QuickPay Payments</button>
            <button class="tab" onclick="switchTab('signups')">New Signups</button>
        </div>

        <!-- QuickPay Tab -->
        <div id="quickpays-content" class="tab-content active">
            <div class="section">
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-label">Total QuickPays</div>
                        <div class="stat-value" id="quickpay-total">-</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Last 24 Hours</div>
                        <div class="stat-value" id="quickpay-24h">-</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Last 7 Days</div>
                        <div class="stat-value" id="quickpay-7d">-</div>
                    </div>
                </div>

                <div class="limit-selector">
                    <label for="quickpay-limit">Show:</label>
                    <select id="quickpay-limit" onchange="loadQuickPays()">
                        <option value="50">Last 50</option>
                        <option value="100">Last 100</option>
                        <option value="200">Last 200</option>
                    </select>
                </div>

                <div id="quickpays-table">
                    <div class="loading">Loading QuickPay payments...</div>
                </div>
            </div>
        </div>

        <!-- Signups Tab -->
        <div id="signups-content" class="tab-content">
            <div class="section">
                <div class="stats">
                    <div class="stat-card">
                        <div class="stat-label">Total Signups</div>
                        <div class="stat-value" id="signup-total">-</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Last 24 Hours</div>
                        <div class="stat-value" id="signup-24h">-</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Last 7 Days</div>
                        <div class="stat-value" id="signup-7d">-</div>
                    </div>
                </div>

                <div class="limit-selector">
                    <label for="signup-limit">Show:</label>
                    <select id="signup-limit" onchange="loadSignups()">
                        <option value="50">Last 50</option>
                        <option value="100">Last 100</option>
                        <option value="200">Last 200</option>
                    </select>
                </div>

                <div id="signups-table">
                    <div class="loading">Loading signups...</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            // Update tab buttons
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');

            // Update content
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.getElementById(tab + '-content').classList.add('active');

            // Load data if not already loaded
            if (tab === 'quickpays' && !window.quickpaysLoaded) {
                loadQuickPays();
            } else if (tab === 'signups' && !window.signupsLoaded) {
                loadSignups();
            }
        }

        async function loadQuickPays() {
            const limit = document.getElementById('quickpay-limit').value;
            const container = document.getElementById('quickpays-table');

            try {
                const response = await fetch(`/api/admin/get-quickpays.php?limit=${limit}`);
                const data = await response.json();

                if (!data.ok) {
                    throw new Error(data.error || 'Failed to load data');
                }

                renderQuickPays(data.quickpays, data.stats);
                window.quickpaysLoaded = true;
            } catch (error) {
                console.error('Error loading QuickPays:', error);
                container.innerHTML = `<div class="no-data">Error: ${error.message}</div>`;
            }
        }

        async function loadSignups() {
            const limit = document.getElementById('signup-limit').value;
            const container = document.getElementById('signups-table');

            try {
                const response = await fetch(`/api/admin/get-signups.php?limit=${limit}`);
                const data = await response.json();

                if (!data.ok) {
                    throw new Error(data.error || 'Failed to load data');
                }

                renderSignups(data.signups, data.stats);
                window.signupsLoaded = true;
            } catch (error) {
                console.error('Error loading signups:', error);
                container.innerHTML = `<div class="no-data">Error: ${error.message}</div>`;
            }
        }

        function renderQuickPays(quickpays, stats) {
            // Update stats
            document.getElementById('quickpay-total').textContent = stats.total;
            document.getElementById('quickpay-24h').textContent = stats.last_24h;
            document.getElementById('quickpay-7d').textContent = stats.last_7d;

            const container = document.getElementById('quickpays-table');

            if (!quickpays || quickpays.length === 0) {
                container.innerHTML = '<div class="no-data">No QuickPay payments found</div>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Member ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Transaction ID</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            quickpays.forEach(qp => {
                const statusBadge = qp.reactivated
                    ? `<span style="color: #10b981; font-weight: 600;">✓ FOB Reactivated</span><br><small style="color: #94a3b8;">Valid until ${qp.valid_until_formatted}</small>`
                    : '<span style="color: #64748b;">Processed</span>';

                html += `
                    <tr>
                        <td>${new Date(qp.processed_at).toLocaleString()}</td>
                        <td>${qp.member_id}</td>
                        <td>${qp.member_name || '-'}</td>
                        <td>${qp.email || '-'}</td>
                        <td class="amount">$${(qp.amount_cents / 100).toFixed(2)}</td>
                        <td>${statusBadge}</td>
                        <td style="font-family: monospace; font-size: 0.75rem;">${qp.transaction_id || '-'}</td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            container.innerHTML = html;
        }

        function renderSignups(signups, stats) {
            // Update stats
            document.getElementById('signup-total').textContent = stats.total;
            document.getElementById('signup-24h').textContent = stats.last_24h;
            document.getElementById('signup-7d').textContent = stats.last_7d;

            const container = document.getElementById('signups-table');

            if (!signups || signups.length === 0) {
                container.innerHTML = '<div class="no-data">No signups found</div>';
                return;
            }

            let html = `
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Plan</th>
                            <th>Monthly Fee</th>
                        </tr>
                    </thead>
                    <tbody>
            `;

            signups.forEach(signup => {
                html += `
                    <tr>
                        <td>${new Date(signup.completed_at).toLocaleString()}</td>
                        <td>${signup.member_name || '-'}</td>
                        <td>${signup.member_email || '-'}</td>
                        <td>${signup.member_phone || '-'}</td>
                        <td>${signup.membership_plan || '-'}</td>
                        <td class="amount">$${parseFloat(signup.monthly_fee || 0).toFixed(2)}</td>
                    </tr>
                `;
            });

            html += `
                    </tbody>
                </table>
            `;

            container.innerHTML = html;
        }

        // Load QuickPays on page load
        loadQuickPays();
    </script>
</body>
</html>
