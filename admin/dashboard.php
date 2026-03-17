<?php
require __DIR__ . '/_auth.php';
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Dashboard — Andalusia Health & Fitness</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
  --primary: #d81b60;
  --primary-dark: #a80c47;
  --primary-light: #ff4081;
  --success: #10b981;
  --warning: #f59e0b;
  --danger: #ef4444;
  --info: #3b82f6;

  --bg-primary: #0f0f11;
  --bg-secondary: #18181b;
  --bg-tertiary: #27272a;

  --text-primary: #fafafa;
  --text-secondary: #a1a1aa;
  --text-tertiary: #71717a;

  --border: #27272a;
  --hover: #3f3f46;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  background: var(--bg-primary);
  color: var(--text-primary);
  line-height: 1.6;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.dashboard {
  max-width: 1920px;
  margin: 0 auto;
  padding: 2rem;
}

/* Header */
.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2.5rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid var(--border);
}

.header-title {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.header-title h1 {
  font-size: 1.75rem;
  font-weight: 700;
  background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.header-badge {
  padding: 0.375rem 0.875rem;
  background: rgba(216, 27, 96, 0.1);
  border: 1px solid rgba(216, 27, 96, 0.2);
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--primary-light);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  position: relative;
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.75rem;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--primary), var(--primary-light));
  opacity: 0;
  transition: opacity 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  border-color: var(--primary);
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4), 0 0 0 1px rgba(216, 27, 96, 0.1);
}

.stat-card:hover::before {
  opacity: 1;
}

.stat-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.stat-label {
  font-size: 0.875rem;
  font-weight: 500;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.stat-icon {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 10px;
  font-size: 1.5rem;
  background: rgba(216, 27, 96, 0.1);
}

.stat-value {
  font-size: 2.5rem;
  font-weight: 800;
  line-height: 1;
  margin-bottom: 0.5rem;
  background: linear-gradient(135deg, var(--text-primary) 0%, var(--text-secondary) 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.stat-trend {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 500;
}

.stat-trend.up {
  color: var(--success);
}

.stat-trend.down {
  color: var(--danger);
}

/* Controls */
.controls {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-bottom: 2rem;
  padding: 1.5rem;
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: 12px;
}

.filter-group {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  flex: 1;
  min-width: 300px;
}

.filter-btn {
  padding: 0.625rem 1.125rem;
  background: var(--bg-tertiary);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text-secondary);
  font-size: 0.875rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.filter-btn:hover {
  background: var(--hover);
  color: var(--text-primary);
  border-color: var(--primary);
}

.filter-btn.active {
  background: var(--primary);
  color: white;
  border-color: var(--primary);
  box-shadow: 0 4px 12px rgba(216, 27, 96, 0.3);
}

.search-wrapper {
  position: relative;
  flex: 1;
  min-width: 300px;
}

.search-input {
  width: 100%;
  padding: 0.75rem 1rem 0.75rem 2.75rem;
  background: var(--bg-tertiary);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text-primary);
  font-size: 0.875rem;
  font-family: inherit;
  transition: all 0.2s ease;
}

.search-input:focus {
  outline: none;
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(216, 27, 96, 0.1);
}

.search-icon {
  position: absolute;
  left: 1rem;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-tertiary);
  pointer-events: none;
}

/* Table */
.table-wrapper {
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: 12px;
  overflow: hidden;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table thead {
  background: var(--bg-tertiary);
  border-bottom: 1px solid var(--border);
}

.table th {
  padding: 1rem 1.5rem;
  text-align: left;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  cursor: pointer;
  user-select: none;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.table th:hover {
  color: var(--text-primary);
  background: var(--hover);
}

.table th.sortable::after {
  content: '⇅';
  margin-left: 0.5rem;
  opacity: 0.3;
  font-size: 0.875rem;
}

.table th.sorted-asc::after {
  content: '↑';
  opacity: 1;
  color: var(--primary);
}

.table th.sorted-desc::after {
  content: '↓';
  opacity: 1;
  color: var(--primary);
}

.table tbody tr {
  border-bottom: 1px solid var(--border);
  transition: all 0.2s ease;
}

.table tbody tr:hover {
  background: var(--bg-tertiary);
  cursor: pointer;
}

.table tbody tr:last-child {
  border-bottom: none;
}

.table td {
  padding: 1.25rem 1.5rem;
  font-size: 0.875rem;
  color: var(--text-primary);
}

.table td.id {
  font-weight: 600;
  color: var(--text-secondary);
  font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
}

.table td.name {
  font-weight: 500;
}

.table td.fee {
  font-weight: 600;
  font-family: 'SF Mono', 'Monaco', 'Consolas', monospace;
}

/* Badges */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.375rem;
  padding: 0.375rem 0.75rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.025em;
  white-space: nowrap;
}

.badge-current {
  background: rgba(16, 185, 129, 0.1);
  color: var(--success);
  border: 1px solid rgba(16, 185, 129, 0.2);
}

.badge-due {
  background: rgba(239, 68, 68, 0.1);
  color: var(--danger);
  border: 1px solid rgba(239, 68, 68, 0.2);
}

.badge-draft {
  background: rgba(59, 130, 246, 0.1);
  color: var(--info);
  border: 1px solid rgba(59, 130, 246, 0.2);
}

.badge-manual {
  background: rgba(245, 158, 11, 0.1);
  color: var(--warning);
  border: 1px solid rgba(245, 158, 11, 0.2);
}

.badge-inactive {
  background: rgba(113, 113, 122, 0.1);
  color: var(--text-tertiary);
  border: 1px solid rgba(113, 113, 122, 0.2);
}

.badge-icon {
  width: 6px;
  height: 6px;
  border-radius: 50%;
}

.badge-current .badge-icon {
  background: var(--success);
  box-shadow: 0 0 8px var(--success);
}

.badge-due .badge-icon {
  background: var(--danger);
  box-shadow: 0 0 8px var(--danger);
}

/* Detail Panel */
.detail-panel {
  position: fixed;
  top: 0;
  right: -600px;
  width: 600px;
  height: 100vh;
  background: var(--bg-secondary);
  border-left: 1px solid var(--border);
  box-shadow: -20px 0 60px rgba(0, 0, 0, 0.5);
  transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  z-index: 1000;
  overflow-y: auto;
}

.detail-panel.active {
  right: 0;
}

.detail-header {
  position: sticky;
  top: 0;
  background: var(--bg-secondary);
  border-bottom: 1px solid var(--border);
  padding: 2rem;
  z-index: 10;
}

.detail-close {
  position: absolute;
  top: 1.5rem;
  right: 1.5rem;
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg-tertiary);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text-secondary);
  cursor: pointer;
  transition: all 0.2s ease;
  font-size: 1.25rem;
}

.detail-close:hover {
  background: var(--danger);
  border-color: var(--danger);
  color: white;
  transform: rotate(90deg);
}

.detail-name {
  font-size: 1.5rem;
  font-weight: 700;
  margin-bottom: 1rem;
}

.detail-content {
  padding: 2rem;
}

.detail-section {
  margin-bottom: 2rem;
}

.detail-section-title {
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-bottom: 1rem;
}

.detail-grid {
  display: grid;
  gap: 1rem;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  background: var(--bg-tertiary);
  border: 1px solid var(--border);
  border-radius: 8px;
}

.detail-label {
  font-size: 0.875rem;
  color: var(--text-secondary);
  font-weight: 500;
}

.detail-value {
  font-size: 0.875rem;
  color: var(--text-primary);
  font-weight: 600;
}

.detail-actions {
  display: flex;
  gap: 1rem;
}

.btn {
  padding: 0.875rem 1.5rem;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  text-decoration: none;
}

.btn-primary {
  background: var(--primary);
  color: white;
  box-shadow: 0 4px 12px rgba(216, 27, 96, 0.3);
}

.btn-primary:hover {
  background: var(--primary-dark);
  transform: translateY(-2px);
  box-shadow: 0 8px 20px rgba(216, 27, 96, 0.4);
}

/* Responsive */
@media (max-width: 1024px) {
  .dashboard {
    padding: 1.5rem;
  }

  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .detail-panel {
    width: 100%;
    right: -100%;
  }
}

@media (max-width: 640px) {
  .dashboard {
    padding: 1rem;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .controls {
    flex-direction: column;
  }

  .filter-group,
  .search-wrapper {
    width: 100%;
    min-width: 100%;
  }

  .table-wrapper {
    overflow-x: auto;
  }

  .table th,
  .table td {
    padding: 0.875rem;
    font-size: 0.8125rem;
  }

  /* Hide some columns on mobile */
  .table th:nth-child(3),
  .table td:nth-child(3),
  .table th:nth-child(4),
  .table td:nth-child(4) {
    display: none;
  }
}

/* Loading State */
.loading {
  text-align: center;
  padding: 4rem 2rem;
  color: var(--text-secondary);
}

.loading-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid var(--border);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 1rem;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Toggle Switch */
.toggle-switch {
  position: relative;
  display: inline-block;
  width: 48px;
  height: 26px;
}

.toggle-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.toggle-slider {
  position: absolute;
  cursor: pointer;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: var(--bg-tertiary);
  border: 1px solid var(--border);
  transition: 0.3s;
  border-radius: 26px;
}

.toggle-slider:before {
  position: absolute;
  content: "";
  height: 18px;
  width: 18px;
  left: 3px;
  bottom: 3px;
  background-color: var(--text-secondary);
  transition: 0.3s;
  border-radius: 50%;
}

input:checked + .toggle-slider {
  background-color: var(--primary);
  border-color: var(--primary);
}

input:checked + .toggle-slider:before {
  transform: translateX(22px);
  background-color: white;
}

input:disabled + .toggle-slider {
  opacity: 0.5;
  cursor: not-allowed;
}

/* View Tabs */
.view-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 2rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid var(--border);
}

.view-tab {
  padding: 0.75rem 1.5rem;
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text-secondary);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.view-tab:hover {
  background: var(--bg-tertiary);
  color: var(--text-primary);
}

.view-tab.active {
  background: var(--primary);
  border-color: var(--primary);
  color: white;
}

/* View Content */
.view-content {
  display: none;
}

.view-content.active {
  display: block;
}

/* Doors Grid */
.doors-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.door-card {
  background: var(--bg-secondary);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 1.5rem;
  transition: all 0.2s ease;
}

.door-card:hover {
  border-color: var(--primary);
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}

.door-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
}

.door-name {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--text-primary);
}

.door-status-badge {
  padding: 0.375rem 0.875rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}

.door-status-badge.online {
  background: rgba(16, 185, 129, 0.1);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: var(--success);
}

.door-status-badge.offline {
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  color: var(--danger);
}

.door-info {
  display: grid;
  gap: 0.75rem;
}

.door-info-row {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--border);
}

.door-info-label {
  color: var(--text-secondary);
  font-size: 0.875rem;
}

.door-info-value {
  color: var(--text-primary);
  font-weight: 600;
  font-size: 0.875rem;
}

.door-alert {
  margin-top: 1rem;
  padding: 0.75rem;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.3);
  border-radius: 6px;
  color: var(--danger);
  font-size: 0.875rem;
}

/* AxTrax Sub-Tabs */
.axtrax-sub-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  padding: 0.5rem;
  background: var(--bg-secondary);
  border-radius: 8px;
  border: 1px solid var(--border);
}

.axtrax-sub-tab {
  padding: 0.625rem 1.25rem;
  background: transparent;
  border: none;
  border-radius: 6px;
  color: var(--text-secondary);
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.axtrax-sub-tab:hover {
  background: var(--bg-tertiary);
  color: var(--text-primary);
}

.axtrax-sub-tab.active {
  background: var(--primary);
  color: white;
}

.axtrax-sub-content {
  display: none;
}

.axtrax-sub-content.active {
  display: block;
}

/* ============================================
   MOBILE RESPONSIVE STYLES
   ============================================ */

/* Tablet and below (1024px) */
@media (max-width: 1024px) {
  .dashboard {
    padding: 1.5rem 1rem;
  }

  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
  }

  .doors-grid {
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  }
}

/* Mobile (768px and below) */
@media (max-width: 768px) {
  /* Dashboard container */
  .dashboard {
    padding: 1rem 0.75rem;
  }

  /* Header */
  .header {
    flex-direction: column;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
  }

  .header-title {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.5rem;
    width: 100%;
  }

  .header-title h1 {
    font-size: 1.5rem;
  }

  .header-actions {
    width: 100%;
  }

  .logout-btn {
    width: 100%;
    justify-content: center;
  }

  /* Stats grid - stack on mobile */
  .stats-grid {
    grid-template-columns: 1fr;
    gap: 0.75rem;
  }

  .stat-card {
    padding: 1rem;
  }

  .stat-value {
    font-size: 1.75rem;
  }

  /* View tabs - horizontal scroll on mobile */
  .view-tabs {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
    margin-bottom: 1.5rem;
  }

  .view-tab {
    white-space: nowrap;
    flex-shrink: 0;
    padding: 0.75rem 1.25rem;
    font-size: 0.875rem;
    min-height: 44px; /* iOS touch target */
  }

  /* AxTrax sub-tabs - horizontal scroll */
  .axtrax-sub-tabs {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
    margin-bottom: 1rem;
  }

  .axtrax-sub-tab {
    white-space: nowrap;
    flex-shrink: 0;
    padding: 0.75rem 1.25rem;
    min-height: 44px; /* iOS touch target */
  }

  /* Doors grid - stack vertically on mobile */
  .doors-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  .door-card {
    padding: 1.25rem;
  }

  .door-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }

  /* Door unlock button - full width on mobile */
  .door-card .filter-btn {
    width: 100%;
    padding: 0.875rem 1.5rem;
    font-size: 1rem;
    min-height: 48px; /* Touch-friendly */
  }

  /* Table wrapper - horizontal scroll */
  .table-wrapper {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    margin: 0 -0.75rem; /* Extend to edges */
    padding: 0 0.75rem;
  }

  .table {
    min-width: 600px; /* Prevent table from being too narrow */
    font-size: 0.875rem;
  }

  .table th,
  .table td {
    padding: 0.75rem 0.5rem;
    white-space: nowrap;
  }

  /* Buttons - touch-friendly */
  .filter-btn,
  .action-btn {
    min-height: 44px;
    padding: 0.75rem 1.25rem;
    font-size: 0.9375rem;
  }

  /* Search input - larger on mobile */
  input[type="text"],
  input[type="email"],
  input[type="date"],
  textarea,
  select {
    font-size: 16px; /* Prevents iOS zoom */
    min-height: 44px;
    padding: 0.75rem;
  }

  /* Member list items - touch-friendly */
  .member-item {
    padding: 1rem;
  }

  /* Detail panel - full screen on mobile */
  .detail-panel {
    width: 100%;
    max-width: 100%;
    border-radius: 0;
  }

  .detail-panel.active {
    right: 0;
  }

  .detail-header {
    padding: 1.25rem 1rem;
  }

  .detail-content {
    padding: 1.5rem 1rem;
  }

  /* Door info rows - better spacing on mobile */
  .door-info-row {
    padding: 0.75rem 0;
  }

  .door-info-label {
    font-size: 0.875rem;
  }

  .door-info-value {
    font-size: 0.9375rem;
  }

  /* Loading spinner - slightly smaller on mobile */
  .loading-spinner {
    width: 32px;
    height: 32px;
    border-width: 3px;
  }

  /* AxTrax Update Member form */
  .form-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  /* Filters - stack on mobile */
  .filters {
    flex-direction: column;
    gap: 0.75rem;
  }

  .filters > * {
    width: 100%;
  }

  /* Hide scrollbars but keep functionality */
  .view-tabs::-webkit-scrollbar,
  .axtrax-sub-tabs::-webkit-scrollbar {
    height: 4px;
  }

  .view-tabs::-webkit-scrollbar-thumb,
  .axtrax-sub-tabs::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 2px;
  }
}

/* Small mobile (480px and below) */
@media (max-width: 480px) {
  .dashboard {
    padding: 0.75rem 0.5rem;
  }

  .header-title h1 {
    font-size: 1.25rem;
  }

  .stat-card {
    padding: 0.875rem;
  }

  .stat-value {
    font-size: 1.5rem;
  }

  .stat-label {
    font-size: 0.75rem;
  }

  .door-card {
    padding: 1rem;
  }

  .door-name {
    font-size: 1rem;
  }

  .view-tab,
  .axtrax-sub-tab {
    padding: 0.625rem 1rem;
    font-size: 0.8125rem;
  }

  .table {
    font-size: 0.8125rem;
  }

  .table th,
  .table td {
    padding: 0.625rem 0.375rem;
  }
}

/* Landscape mobile */
@media (max-width: 896px) and (orientation: landscape) {
  .dashboard {
    padding: 1rem;
  }

  .header {
    margin-bottom: 1rem;
  }

  .doors-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
  }

  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

/* Touch device optimizations */
@media (hover: none) and (pointer: coarse) {
  /* Larger tap targets for touch devices */
  button,
  .member-item,
  .door-card,
  .view-tab,
  .axtrax-sub-tab {
    -webkit-tap-highlight-color: rgba(216, 27, 96, 0.1);
  }

  /* Remove hover effects on touch devices */
  .member-item:hover,
  .door-card:hover {
    transform: none;
    background: var(--bg-secondary);
  }

  /* Better touch feedback */
  button:active,
  .view-tab:active,
  .axtrax-sub-tab:active {
    transform: scale(0.98);
  }
}
</style>
</head>
<body>

<div class="dashboard">
  <div class="header">
    <div class="header-title">
      <h1>Admin Dashboard</h1>
      <span class="header-badge">Andalusia Health & Fitness</span>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-header">
        <div>
          <div class="stat-label">Current Members</div>
          <div class="stat-value" id="stat-current">0</div>
        </div>
        <div class="stat-icon">✓</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-header">
        <div>
          <div class="stat-label">Payments Due</div>
          <div class="stat-value" id="stat-due">0</div>
        </div>
        <div class="stat-icon">⚠</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-header">
        <div>
          <div class="stat-label">Past Due (30 Days)</div>
          <div class="stat-value" id="stat-past-due-30">0</div>
        </div>
        <div class="stat-icon">📅</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-header">
        <div>
          <div class="stat-label">Draft Members</div>
          <div class="stat-value" id="stat-draft">0</div>
        </div>
        <div class="stat-icon">💳</div>
      </div>
    </div>

    <div class="stat-card">
      <div class="stat-header">
        <div>
          <div class="stat-label">Total Members</div>
          <div class="stat-value" id="stat-total">0</div>
        </div>
        <div class="stat-icon">👥</div>
      </div>
    </div>
  </div>

  <!-- View Tabs -->
  <div class="view-tabs">
    <button class="view-tab active" data-view="members">👥 Members</button>
    <button class="view-tab" data-view="signups">📋 Signups</button>
    <button class="view-tab" data-view="quickpays">💳 QuickPay</button>
    <button class="view-tab" data-view="axtrax">🔐 AxTraxPro</button>
    <button class="view-tab" onclick="location='departments.php'">🏢 Departments</button>
  </div>

  <!-- Members View -->
  <div id="view-members" class="view-content active">
    <div class="controls">
      <div class="filter-group">
        <button class="filter-btn active" data-filter="all">All Members</button>
        <button class="filter-btn" data-filter="current">Current</button>
        <button class="filter-btn" data-filter="due">All Due</button>
        <button class="filter-btn" data-filter="past-due-30">Past Due (30d)</button>
        <button class="filter-btn" data-filter="draft">Draft</button>
        <button class="filter-btn" data-filter="inactive">Inactive</button>
      </div>

      <div class="search-wrapper">
        <span class="search-icon">🔍</span>
        <input type="text" class="search-input" id="search" placeholder="Search members...">
      </div>
    </div>

  <div class="table-wrapper">
    <table class="table">
      <thead>
        <tr>
          <th class="sortable" data-column="id">ID</th>
          <th class="sortable" data-column="name">Member</th>
          <th class="sortable" data-column="department">Department</th>
          <th class="sortable" data-column="payment">Payment</th>
          <th class="sortable" data-column="fee">Monthly Fee</th>
          <th class="sortable" data-column="valid_until">Valid Until</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody id="table-body">
        <tr>
          <td colspan="7">
            <div class="loading">
              <div class="loading-spinner"></div>
              Loading members...
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  </div>

  <!-- Signups View -->
  <div id="view-signups" class="view-content">
    <div style="display:flex;gap:1.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
      <div style="background:var(--bg-tertiary);padding:1rem 1.5rem;border-radius:8px;min-width:140px;text-align:center;">
        <div style="font-size:1.75rem;font-weight:700;color:var(--primary-light);" id="signups-total">—</div>
        <div style="font-size:0.75rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">Total</div>
      </div>
      <div style="background:var(--bg-tertiary);padding:1rem 1.5rem;border-radius:8px;min-width:140px;text-align:center;">
        <div style="font-size:1.75rem;font-weight:700;color:var(--success);" id="signups-24h">—</div>
        <div style="font-size:0.75rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">Last 24h</div>
      </div>
      <div style="background:var(--bg-tertiary);padding:1rem 1.5rem;border-radius:8px;min-width:140px;text-align:center;">
        <div style="font-size:1.75rem;font-weight:700;color:var(--info);" id="signups-7d">—</div>
        <div style="font-size:0.75rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">Last 7 Days</div>
      </div>
      <div style="flex:1;"></div>
      <button class="filter-btn" onclick="loadSignups()">🔄 Refresh</button>
    </div>

    <h3 style="margin-bottom:0.75rem;font-size:1rem;color:var(--text-primary);">Completed Signups</h3>
    <div class="table-wrapper">
      <table class="table">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Plan</th><th>Monthly</th><th>Completed</th></tr></thead>
        <tbody id="signups-tbody"><tr><td colspan="7" style="text-align:center;padding:2rem;">Loading...</td></tr></tbody>
      </table>
    </div>

    <h3 style="margin:2rem 0 0.75rem;font-size:1rem;color:var(--warning);">⚠️ Abandoned Signups</h3>
    <p style="color:var(--text-secondary);font-size:0.85rem;margin-bottom:1rem;">Signup attempts older than 15 minutes that never completed. Payment may have been charged.</p>
    <div class="table-wrapper">
      <table class="table">
        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Plan</th><th>Monthly</th><th>Age</th><th>Started</th></tr></thead>
        <tbody id="abandoned-tbody"><tr><td colspan="8" style="text-align:center;padding:2rem;">Loading...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- QuickPay View -->
  <div id="view-quickpays" class="view-content">
    <div style="display:flex;gap:1.5rem;margin-bottom:1.5rem;flex-wrap:wrap;">
      <div style="background:var(--bg-tertiary);padding:1rem 1.5rem;border-radius:8px;min-width:140px;text-align:center;">
        <div style="font-size:1.75rem;font-weight:700;color:var(--primary-light);" id="qp-total">—</div>
        <div style="font-size:0.75rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">Total Payments</div>
      </div>
      <div style="background:var(--bg-tertiary);padding:1rem 1.5rem;border-radius:8px;min-width:140px;text-align:center;">
        <div style="font-size:1.75rem;font-weight:700;color:var(--success);" id="qp-24h">—</div>
        <div style="font-size:0.75rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">Last 24h</div>
      </div>
      <div style="background:var(--bg-tertiary);padding:1rem 1.5rem;border-radius:8px;min-width:140px;text-align:center;">
        <div style="font-size:1.75rem;font-weight:700;color:var(--info);" id="qp-7d">—</div>
        <div style="font-size:0.75rem;color:var(--text-secondary);text-transform:uppercase;letter-spacing:0.05em;">Last 7 Days</div>
      </div>
      <div style="flex:1;"></div>
      <button class="filter-btn" onclick="loadQuickPays()">🔄 Refresh</button>
    </div>
    <div class="table-wrapper">
      <table class="table">
        <thead><tr><th>ID</th><th>Member</th><th>Amount</th><th>Transaction ID</th><th>Status</th><th>Processed</th></tr></thead>
        <tbody id="qp-tbody"><tr><td colspan="6" style="text-align:center;padding:2rem;">Loading...</td></tr></tbody>
      </table>
    </div>
  </div>

  <!-- AxTraxPro View -->
  <div id="view-axtrax" class="view-content">
    <!-- AxTrax Sub-Tabs -->
    <div class="axtrax-sub-tabs">
      <button class="axtrax-sub-tab active" data-axtrax-view="doors">🚪 Door Monitoring</button>
      <button class="axtrax-sub-tab" data-axtrax-view="events">📋 Access Events</button>
    </div>

    <!-- Doors Sub-View -->
    <div id="axtrax-doors" class="axtrax-sub-content active">
      <div style="margin-bottom: 1.5rem;">
        <button class="filter-btn" onclick="loadDoors()">🔄 Refresh Status</button>
      </div>
      <div id="doors-grid" class="doors-grid">
        <div class="loading">
          <div class="loading-spinner"></div>
          Loading door status...
        </div>
      </div>
    </div>

    <!-- Events Sub-View -->
    <div id="axtrax-events" class="axtrax-sub-content">
      <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <button class="filter-btn" onclick="loadEvents()">🔄 Refresh Events</button>
        <div style="color: var(--text-secondary); font-size: 0.875rem;" id="events-count"></div>
      </div>
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Time</th>
              <th>Member</th>
              <th>Door</th>
              <th>Event Type</th>
              <th>Card</th>
            </tr>
          </thead>
          <tbody id="events-body">
            <tr>
              <td colspan="5">
                <div class="loading">
                  <div class="loading-spinner"></div>
                  Loading events...
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="detail-panel" id="detail-panel">
  <div class="detail-header">
    <div class="detail-close" onclick="closeDetail()">×</div>
    <h2 class="detail-name" id="detail-name">Member Details</h2>
    <div id="detail-status"></div>
  </div>
  <div class="detail-content" id="detail-content"></div>
</div>

<script>
// View Switching
function switchView(viewName) {
  // Update tabs
  document.querySelectorAll('.view-tab').forEach(tab => {
    if (tab.dataset.view === viewName) {
      tab.classList.add('active');
    } else {
      tab.classList.remove('active');
    }
  });

  // Update content
  document.querySelectorAll('.view-content').forEach(content => {
    content.classList.remove('active');
  });
  document.getElementById('view-' + viewName).classList.add('active');

  // Load data for the view
  if (viewName === 'signups') loadSignups();
  if (viewName === 'quickpays') loadQuickPays();
  if (viewName === 'axtrax') {
    // Load doors by default when opening AxTrax view
    loadDoors();
  }
}

// AxTrax Sub-Tab Switching
function switchAxTraxView(axtraxView) {
  // Update sub-tabs
  document.querySelectorAll('.axtrax-sub-tab').forEach(tab => {
    if (tab.dataset.axtraxView === axtraxView) {
      tab.classList.add('active');
    } else {
      tab.classList.remove('active');
    }
  });

  // Update sub-content
  document.querySelectorAll('.axtrax-sub-content').forEach(content => {
    content.classList.remove('active');
  });
  document.getElementById('axtrax-' + axtraxView).classList.add('active');

  // Load data for the sub-view
  if (axtraxView === 'doors') {
    loadDoors();
  } else if (axtraxView === 'events') {
    loadEvents();
  }
}

// Add click handlers to view tabs
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.view-tab[data-view]').forEach(tab => {
    tab.addEventListener('click', () => {
      switchView(tab.dataset.view);
    });
  });

  // Add click handlers to AxTrax sub-tabs
  document.querySelectorAll('.axtrax-sub-tab[data-axtrax-view]').forEach(tab => {
    tab.addEventListener('click', () => {
      switchAxTraxView(tab.dataset.axtraxView);
    });
  });
});

// Doors Monitoring
async function loadDoors() {
  const grid = document.getElementById('doors-grid');
  grid.innerHTML = '<div class="loading"><div class="loading-spinner"></div>Loading door status...</div>';

  try {
    const res = await fetch('/api/axtrax-doors.php', {
      credentials: 'same-origin'
    });
    const data = await res.json();

    if (!data.ok || !data.doors) {
      grid.innerHTML = '<div class="loading">Failed to load door status</div>';
      return;
    }

    renderDoors(data.doors);
  } catch (err) {
    console.error(err);
    grid.innerHTML = '<div class="loading">Error loading door status</div>';
  }
}

function renderDoors(doors) {
  const grid = document.getElementById('doors-grid');

  if (doors.length === 0) {
    grid.innerHTML = '<div class="loading">No doors found</div>';
    return;
  }

  grid.innerHTML = doors.map(door => {
    const isOnline = door.online;
    const statusClass = isOnline ? 'online' : 'offline';
    const statusText = isOnline ? 'Online' : 'Offline';

    // Extract clean door name from tDesc (format: "1\\Panel 1\\Door 1 Front Door")
    const doorName = door.name.split('\\').pop().trim();

    const alerts = [];
    if (door.forced) alerts.push('Door Forced');
    if (door.held_alert) alerts.push('Door Held Open');

    return `
      <div class="door-card">
        <div class="door-header">
          <div class="door-name">${doorName}</div>
          <div class="door-status-badge ${statusClass}">${statusText}</div>
        </div>
        <div class="door-info">
          <div class="door-info-row">
            <span class="door-info-label">Door ID</span>
            <span class="door-info-value">#${door.id}</span>
          </div>
          <div class="door-info-row">
            <span class="door-info-label">Panel ID</span>
            <span class="door-info-value">${door.panel_id}</span>
          </div>
          <div class="door-info-row">
            <span class="door-info-label">Door Number</span>
            <span class="door-info-value">${door.door_number}</span>
          </div>
          <div class="door-info-row">
            <span class="door-info-label">REX Active</span>
            <span class="door-info-value">${door.rex ? 'Yes' : 'No'}</span>
          </div>
        </div>
        ${alerts.length > 0 ? `<div class="door-alert">⚠️ ${alerts.join(', ')}</div>` : ''}
        <button class="filter-btn" style="width: 100%; margin-top: 1rem;" onclick="openDoor(${door.id})">🔓 Open Door</button>
      </div>
    `;
  }).join('');
}

// Open Door by ID
async function openDoor(doorId) {
  if (!confirm(`Are you sure you want to open door #${doorId}?`)) {
    return;
  }

  try {
    const res = await fetch(`/api/axtrax-open-door.php?door_id=${doorId}`, {
      credentials: 'same-origin'
    });
    const data = await res.json();

    if (data.ok) {
      alert(`✓ Door #${doorId} opened successfully`);
      loadDoors(); // Refresh status
    } else {
      alert(`✗ Failed to open door: ${data.error}`);
    }
  } catch (err) {
    console.error(err);
    alert('Error opening door');
  }
}

// Access Events
async function loadEvents() {
  const tbody = document.getElementById('events-body');
  tbody.innerHTML = '<tr><td colspan="5"><div class="loading"><div class="loading-spinner"></div>Loading events...</div></td></tr>';

  try {
    const res = await fetch('/api/axtrax-events.php', {
      credentials: 'same-origin'
    });
    const data = await res.json();

    if (!data.ok || !data.events) {
      tbody.innerHTML = '<tr><td colspan="5" class="loading">Failed to load events</td></tr>';
      return;
    }

    document.getElementById('events-count').textContent = `${data.count} events`;
    renderEvents(data.events);
  } catch (err) {
    console.error(err);
    tbody.innerHTML = '<tr><td colspan="5" class="loading">Error loading events</td></tr>';
  }
}

function renderEvents(events) {
  const tbody = document.getElementById('events-body');

  if (events.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" class="loading">No events found</td></tr>';
    return;
  }

  // Show only last 100 events for performance
  const recentEvents = events.slice(0, 100);

  tbody.innerHTML = recentEvents.map(event => {
    const timestamp = new Date(event.timestamp);
    const timeStr = timestamp.toLocaleString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });

    const eventTypeText = getEventTypeText(event.event_type);
    const cardInfo = event.card_code ? `${event.site_code}-${event.card_code}` : 'N/A';

    return `
      <tr>
        <td>${timeStr}</td>
        <td><strong>${event.member_name}</strong></td>
        <td>Door #${event.door_id}</td>
        <td>${eventTypeText}</td>
        <td><code style="font-size:0.75rem;">${cardInfo}</code></td>
      </tr>
    `;
  }).join('');
}

function getEventTypeText(type) {
  const types = {
    0: 'Access Granted',
    1: 'Access Denied',
    2: 'Door Forced',
    3: 'Door Held',
    4: 'REX',
    5: 'Unlock'
  };
  return types[type] || `Type ${type}`;
}

// Create User in AxTrax
async function handleCreateUser(event) {
  event.preventDefault();

  const form = event.target;
  const formData = new FormData(form);
  const resultDiv = document.getElementById('create-user-result');

  const userData = {
    first_name: formData.get('first_name'),
    last_name: formData.get('last_name'),
    email: formData.get('email'),
    phone: formData.get('phone'),
    address: formData.get('address'),
    notes: formData.get('notes')
  };

  resultDiv.style.display = 'block';
  resultDiv.style.background = 'rgba(59, 130, 246, 0.1)';
  resultDiv.style.border = '1px solid rgba(59, 130, 246, 0.3)';
  resultDiv.style.color = 'var(--info)';
  resultDiv.textContent = 'Creating user in AxTrax...';

  try {
    const res = await fetch('/api/axtrax-add-user.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(userData)
    });

    const data = await res.json();

    if (data.ok) {
      resultDiv.style.background = 'rgba(16, 185, 129, 0.1)';
      resultDiv.style.border = '1px solid rgba(16, 185, 129, 0.3)';
      resultDiv.style.color = 'var(--success)';
      resultDiv.textContent = '✓ User created successfully in AxTrax!';
      form.reset();

      // Hide success message after 5 seconds
      setTimeout(() => {
        resultDiv.style.display = 'none';
      }, 5000);
    } else {
      throw new Error(data.error || 'Failed to create user');
    }
  } catch (err) {
    resultDiv.style.background = 'rgba(239, 68, 68, 0.1)';
    resultDiv.style.border = '1px solid rgba(239, 68, 68, 0.3)';
    resultDiv.style.color = 'var(--danger)';
    resultDiv.textContent = '✗ Error: ' + err.message;
  }
}

// Show AxTrax Update Form
function showAxTraxUpdateForm(memberId, firstName, lastName) {
  const m = allMembers.find(member => member.id === memberId);
  if (!m) return;

  const content = document.getElementById('detail-content');
  const nameEl = document.getElementById('detail-name');

  nameEl.textContent = `Update ${firstName} ${lastName} in AxTrax`;

  content.innerHTML = `
    <div style="max-width: 600px;">
      <div style="margin-bottom: 2rem;">
        <p style="color: var(--text-secondary); font-size: 0.875rem;">
          Update member information in the AxTrax access control system.
          Changes will NOT affect the local member database (they stay separate).
        </p>
      </div>

      <form id="axtrax-update-form" onsubmit="handleAxTraxUpdate(event, '${firstName}', '${lastName}')" style="display: grid; gap: 1.5rem;">
        <div>
          <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">First Name</label>
          <input type="text" name="first_name" value="${m.first_name || ''}" style="width: 100%; padding: 0.75rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Last Name</label>
          <input type="text" name="last_name" value="${m.last_name || ''}" style="width: 100%; padding: 0.75rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Email</label>
          <input type="email" name="email" value="${m.email || ''}" style="width: 100%; padding: 0.75rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Valid Until (Expiration Date)</label>
          <input type="date" name="stop_date" value="${m.valid_until || ''}" style="width: 100%; padding: 0.75rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 0.875rem;">
        </div>

        <div>
          <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; font-size: 0.875rem;">Notes</label>
          <textarea name="notes" rows="3" style="width: 100%; padding: 0.75rem; background: var(--bg-secondary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-size: 0.875rem; resize: vertical;">${m.notes || ''}</textarea>
        </div>

        <div style="display: flex; gap: 1rem;">
          <button type="button" onclick="showDetail(${memberId})" class="btn" style="flex: 1; padding: 0.875rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 8px; color: var(--text-primary); font-weight: 600; cursor: pointer;">
            Cancel
          </button>
          <button type="submit" class="btn btn-primary" style="flex: 1; padding: 0.875rem; font-size: 1rem; font-weight: 600;">
            🔐 Push to AxTrax
          </button>
        </div>

        <div id="axtrax-update-result" style="display: none; padding: 1rem; border-radius: 8px; font-size: 0.875rem;"></div>
      </form>
    </div>
  `;
}

async function handleAxTraxUpdate(event, searchFirstName, searchLastName) {
  event.preventDefault();

  const form = event.target;
  const formData = new FormData(form);
  const resultDiv = document.getElementById('axtrax-update-result');

  const updates = {
    first_name: formData.get('first_name'),
    last_name: formData.get('last_name'),
    email: formData.get('email'),
    stop_date: formData.get('stop_date'),
    notes: formData.get('notes')
  };

  resultDiv.style.display = 'block';
  resultDiv.style.background = 'rgba(59, 130, 246, 0.1)';
  resultDiv.style.border = '1px solid rgba(59, 130, 246, 0.3)';
  resultDiv.style.color = 'var(--info)';
  resultDiv.textContent = 'Searching for user in AxTrax and updating...';

  try {
    const res = await fetch('/api/axtrax-update-user.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({
        first_name: searchFirstName,
        last_name: searchLastName,
        updates
      })
    });

    const data = await res.json();

    if (data.ok) {
      resultDiv.style.background = 'rgba(16, 185, 129, 0.1)';
      resultDiv.style.border = '1px solid rgba(16, 185, 129, 0.3)';
      resultDiv.style.color = 'var(--success)';
      resultDiv.textContent = '✓ Member updated successfully in AxTrax! Changes will sync back to the database within 15 minutes.';
    } else {
      throw new Error(data.error || 'Failed to update member');
    }
  } catch (err) {
    resultDiv.style.background = 'rgba(239, 68, 68, 0.1)';
    resultDiv.style.border = '1px solid rgba(239, 68, 68, 0.3)';
    resultDiv.style.color = 'var(--danger)';
    resultDiv.textContent = '✗ Error: ' + err.message;
  }
}

let allMembers = [];
let currentSort = { column: 'id', direction: 'asc' };

async function loadMembers() {
  try {
    const res = await fetch('/api/members-list.php?status=all');
    const data = await res.json();

    if (!data.ok || !data.members) {
      document.getElementById('table-body').innerHTML =
        '<tr><td colspan="7" class="loading">Failed to load members</td></tr>';
      return;
    }

    allMembers = data.members;
    updateStats();
    renderTable(allMembers);
  } catch (err) {
    console.error(err);
    document.getElementById('table-body').innerHTML =
      '<tr><td colspan="7" class="loading">Error loading members</td></tr>';
  }
}

function updateStats() {
  // Exclude inactive members from all payment-related stats
  const activeMembers = allMembers.filter(m => m.status !== 'inactive');
  const total = activeMembers.length;
  const current = activeMembers.filter(m => m.status === 'current').length;
  const due = activeMembers.filter(m => m.status === 'due').length;
  const draft = activeMembers.filter(m => m.is_draft === 1).length;

  // Calculate members past due within last 30 days (excluding inactive)
  const today = new Date();
  const thirtyDaysAgo = new Date();
  thirtyDaysAgo.setDate(today.getDate() - 30);

  const pastDue30 = activeMembers.filter(m => {
    if (m.status !== 'due' || !m.valid_until) return false;
    const validUntil = new Date(m.valid_until);
    return validUntil >= thirtyDaysAgo && validUntil <= today;
  }).length;

  document.getElementById('stat-total').textContent = total;
  document.getElementById('stat-current').textContent = current;
  document.getElementById('stat-due').textContent = due;
  document.getElementById('stat-past-due-30').textContent = pastDue30;
  document.getElementById('stat-draft').textContent = draft;
}

function getStatusBadge(status) {
  if (status === 'current') {
    return '<span class="badge badge-current"><span class="badge-icon"></span>Current</span>';
  }
  if (status === 'due') {
    return '<span class="badge badge-due"><span class="badge-icon"></span>Due</span>';
  }
  if (status === 'inactive') {
    return '<span class="badge badge-inactive">Inactive</span>';
  }
  return '<span class="badge">' + status + '</span>';
}

function getPaymentBadge(type) {
  if (type === 'draft') {
    return '<span class="badge badge-draft">Draft</span>';
  }
  return '<span class="badge badge-manual">Manual</span>';
}

function sortTable(column) {
  if (currentSort.column === column) {
    currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
  } else {
    currentSort.column = column;
    currentSort.direction = 'asc';
  }

  document.querySelectorAll('th').forEach(th => {
    th.classList.remove('sorted-asc', 'sorted-desc');
  });

  const th = document.querySelector(`th[data-column="${column}"]`);
  if (th) {
    th.classList.add(currentSort.direction === 'asc' ? 'sorted-asc' : 'sorted-desc');
  }

  const sorted = [...allMembers].sort((a, b) => {
    let aVal, bVal;

    if (column === 'id') {
      aVal = parseInt(a.id);
      bVal = parseInt(b.id);
    } else if (column === 'name') {
      aVal = (a.first_name || '') + ' ' + (a.last_name || '');
      bVal = (b.first_name || '') + ' ' + (b.last_name || '');
    } else if (column === 'department') {
      aVal = a.department_name || '';
      bVal = b.department_name || '';
    } else if (column === 'payment') {
      aVal = a.payment_type || '';
      bVal = b.payment_type || '';
    } else if (column === 'fee') {
      aVal = parseFloat(a.monthly_fee || 0);
      bVal = parseFloat(b.monthly_fee || 0);
    } else if (column === 'valid_until') {
      aVal = a.valid_until || '';
      bVal = b.valid_until || '';
    }

    if (aVal < bVal) return currentSort.direction === 'asc' ? -1 : 1;
    if (aVal > bVal) return currentSort.direction === 'asc' ? 1 : -1;
    return 0;
  });

  renderTable(sorted);
}

function renderTable(list) {
  const tbody = document.getElementById('table-body');

  if (!list.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="loading">No members found</td></tr>';
    return;
  }

  tbody.innerHTML = list.map(m => `
    <tr onclick="showDetail(${m.id})">
      <td class="id">#${m.id}</td>
      <td class="name">
        ${m.first_name} ${m.last_name || ''}
        ${m.is_draft === 1 ? '<span class="badge badge-draft" style="margin-left:8px;font-size:0.7rem;">DRAFT</span>' : ''}
      </td>
      <td>${m.department_name || '<span style="color:var(--text-tertiary)">—</span>'}</td>
      <td>${getPaymentBadge(m.payment_type)}</td>
      <td class="fee">$${parseFloat(m.monthly_fee).toFixed(2)}</td>
      <td>${m.valid_until || '<span style="color:var(--text-tertiary)">—</span>'}</td>
      <td>${getStatusBadge(m.status)}</td>
    </tr>
  `).join('');
}

function filterMembers(type) {
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  event.target.classList.add('active');

  let filtered = allMembers;
  if (type === 'current') {
    filtered = allMembers.filter(m => m.status === 'current');
  } else if (type === 'due') {
    filtered = allMembers.filter(m => m.status === 'due');
  } else if (type === 'past-due-30') {
    // Members who became due within the last 30 days
    const today = new Date();
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(today.getDate() - 30);

    filtered = allMembers.filter(m => {
      if (m.status !== 'due' || !m.valid_until) return false;
      const validUntil = new Date(m.valid_until);
      return validUntil >= thirtyDaysAgo && validUntil <= today;
    });
  } else if (type === 'draft') {
    filtered = allMembers.filter(m => m.is_draft === 1);
  } else if (type === 'inactive') {
    filtered = allMembers.filter(m => m.status === 'inactive');
  }

  renderTable(filtered);
}

document.querySelectorAll('th.sortable').forEach(th => {
  th.addEventListener('click', () => sortTable(th.dataset.column));
});

document.querySelectorAll('.filter-btn[data-filter]').forEach(btn => {
  btn.addEventListener('click', () => filterMembers(btn.dataset.filter));
});

document.getElementById('search').addEventListener('input', e => {
  const q = e.target.value.toLowerCase();
  const filtered = allMembers.filter(m =>
    (m.first_name && m.first_name.toLowerCase().includes(q)) ||
    (m.last_name && m.last_name.toLowerCase().includes(q)) ||
    (m.department_name && m.department_name.toLowerCase().includes(q))
  );
  renderTable(filtered);
});

async function showDetail(id) {
  const res = await fetch(`/api/member-detail.php?id=${id}`);
  const data = await res.json();

  const panel = document.getElementById('detail-panel');
  const content = document.getElementById('detail-content');
  const nameEl = document.getElementById('detail-name');
  const statusEl = document.getElementById('detail-status');

  panel.classList.add('active');

  if (!data.ok || !data.member) {
    content.innerHTML = '<p>Failed to load member details</p>';
    return;
  }

  const m = data.member;
  nameEl.textContent = `${m.first_name} ${m.last_name || ''}`;
  statusEl.innerHTML = getStatusBadge(m.status);

  const isDraft = m.is_draft === 1 || m.is_draft === '1';
  const quickPayUrl = `https://andalusiahealthandfitness.com/quickpay/?m=${m.id}`;

  content.innerHTML = `
    <div class="detail-section">
      <div class="detail-section-title">Member Information</div>
      <div class="detail-grid">
        <div class="detail-item">
          <span class="detail-label">Member ID</span>
          <span class="detail-value">#${m.id}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Department</span>
          <span class="detail-value">${m.department_name || '—'}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Company</span>
          <span class="detail-value">${m.company_name || '—'}</span>
        </div>
      </div>
    </div>

    <div class="detail-section">
      <div class="detail-section-title">Payment Details</div>
      <div class="detail-grid">
        <div class="detail-item">
          <span class="detail-label">Payment Type</span>
          <span class="detail-value">
            ${getPaymentBadge(m.payment_type)}
            ${isDraft ? '<span class="badge badge-draft" style="margin-left:8px;">AUTO DRAFT</span>' : ''}
          </span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Monthly Fee</span>
          <span class="detail-value" style="color:var(--primary)">$${parseFloat(m.monthly_fee).toFixed(2)}</span>
        </div>
      </div>
      ${isDraft ? `
        <div style="margin-top:1rem;padding:12px;background:rgba(216,27,96,0.1);border:1px solid rgba(216,27,96,0.2);border-radius:8px;">
          <div style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:12px;">
            💳 <strong>Automatic Bank Draft Member</strong>
          </div>

          <div style="margin-bottom:12px;padding:10px;background:var(--bg-secondary);border-radius:6px;display:flex;justify-content:space-between;align-items:center;">
            <div>
              <div style="font-size:0.875rem;font-weight:600;color:var(--text-primary);">Enable QuickPay</div>
              <div style="font-size:0.75rem;color:var(--text-secondary);">Allow manual payment if draft fails</div>
            </div>
            <label class="toggle-switch">
              <input type="checkbox" id="quickpay-toggle-${m.id}" ${m.allow_quickpay ? 'checked' : ''} onchange="toggleQuickPay(${m.id}, this.checked)">
              <span class="toggle-slider"></span>
            </label>
          </div>

          <div id="quickpay-buttons-${m.id}" style="display:${m.allow_quickpay ? 'block' : 'none'};">
            <div style="font-size:0.75rem;color:var(--text-secondary);margin-bottom:8px;">
              ⚠️ QuickPay enabled. Member can now pay manually:
            </div>
            <button onclick="copyQuickPayLink('${quickPayUrl}')" class="btn btn-primary" style="width:100%;margin-bottom:8px;">
              📋 Copy QuickPay Link
            </button>
            <a href="${quickPayUrl}" target="_blank" class="btn btn-primary" style="width:100%;display:inline-block;text-align:center;background:var(--primary-dark);">
              🔗 Open QuickPay Page
            </a>
          </div>
        </div>
      ` : ''}
      ${m.notes ? `
        <div style="margin-top:1rem;padding:12px;background:var(--bg-tertiary);border-radius:8px;">
          <div style="font-size:0.75rem;font-weight:600;color:var(--text-secondary);margin-bottom:4px;">NOTES:</div>
          <div style="font-size:0.875rem;color:var(--text-primary);">${m.notes}</div>
        </div>
      ` : ''}
    </div>

    <div class="detail-section">
      <div class="detail-section-title">Membership Status</div>
      <div class="detail-grid">
        <div class="detail-item">
          <span class="detail-label">Valid From</span>
          <span class="detail-value">${m.valid_from || '—'}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Valid Until</span>
          <span class="detail-value">${m.valid_until || '—'}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Last Updated</span>
          <span class="detail-value">${m.updated_at || '—'}</span>
        </div>
      </div>
    </div>

    <div class="detail-section">
      <div class="detail-section-title">AxTrax Actions</div>
      <button onclick="showAxTraxUpdateForm(${m.id}, '${m.first_name}', '${m.last_name}')" class="btn btn-primary" style="width: 100%; padding: 1rem;">
        🔐 Update Member in AxTrax
      </button>
    </div>
  `;
}

function closeDetail() {
  document.getElementById('detail-panel').classList.remove('active');
}

function copyQuickPayLink(url) {
  navigator.clipboard.writeText(url).then(() => {
    // Show success message
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '✓ Copied!';
    btn.style.background = 'var(--success)';
    setTimeout(() => {
      btn.innerHTML = originalText;
      btn.style.background = '';
    }, 2000);
  }).catch(err => {
    alert('Failed to copy link. Please copy manually: ' + url);
  });
}

async function toggleQuickPay(memberId, enabled) {
  const toggle = document.getElementById(`quickpay-toggle-${memberId}`);
  const buttons = document.getElementById(`quickpay-buttons-${memberId}`);

  // Disable toggle during request
  toggle.disabled = true;

  try {
    const res = await fetch('/api/toggle-quickpay.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ member_id: memberId, enabled: enabled })
    });

    const data = await res.json();

    if (!data.ok) {
      alert('Failed to toggle QuickPay: ' + (data.error || 'Unknown error'));
      // Revert toggle
      toggle.checked = !enabled;
      return;
    }

    // Show/hide buttons based on toggle state
    if (buttons) {
      buttons.style.display = enabled ? 'block' : 'none';
    }

    // Show success message
    const message = enabled ? 'QuickPay enabled for this member' : 'QuickPay disabled';
    console.log(message);

  } catch (err) {
    console.error(err);
    alert('Network error. Please try again.');
    // Revert toggle
    toggle.checked = !enabled;
  } finally {
    toggle.disabled = false;
  }
}

async function loadSignups() {
  const tbody = document.getElementById("signups-tbody");
  try {
    const res = await fetch("/api/admin/get-signups.php?limit=100");
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    document.getElementById("signups-total").textContent = data.stats.total;
    document.getElementById("signups-24h").textContent = data.stats.last_24h;
    document.getElementById("signups-7d").textContent = data.stats.last_7d;
    if (!data.signups.length) {
      tbody.innerHTML = "<tr><td colspan=7 style=text-align:center;padding:2rem;>No signups yet.</td></tr>";
      return;
    }
    tbody.innerHTML = data.signups.map(s => {
      const dt = s.completed_at ? new Date(s.completed_at).toLocaleString() : "—";
      return `<tr><td>${s.id}</td><td>${s.member_name||"—"}</td><td>${s.member_email||"—"}</td><td>${s.member_phone||"—"}</td><td>${s.membership_plan||"—"}</td><td>$${s.monthly_fee||"0.00"}</td><td>${dt}</td></tr>`;
    }).join("");
  } catch(e) {
    tbody.innerHTML = "<tr><td colspan=7 style=text-align:center;color:var(--danger);>Error: "+e.message+"</td></tr>";
  }
  // Also load abandoned signups
  const atbody = document.getElementById("abandoned-tbody");
  try {
    const res2 = await fetch("/api/admin/get-abandoned-signups.php");
    const data2 = await res2.json();
    if (!data2.ok) throw new Error(data2.error);
    if (!data2.pending || !data2.pending.length) {
      atbody.innerHTML = "<tr><td colspan=8 style=\"text-align:center;padding:2rem;color:var(--success);\">No abandoned signups.</td></tr>";
    } else {
      atbody.innerHTML = data2.pending.map(s => {
        const age = s.age_minutes >= 60 ? Math.round(s.age_minutes/60)+"h" : s.age_minutes+"m";
        const dt = s.created_at ? new Date(s.created_at).toLocaleString() : "\u2014";
        return `<tr style="color:var(--warning);"><td>${s.id}</td><td>${s.member_name||"\u2014"}</td><td>${s.member_email||"\u2014"}</td><td>${s.member_phone||"\u2014"}</td><td>${s.membership_plan||"\u2014"}</td><td>$${s.monthly_fee||"0.00"}</td><td>${age} ago</td><td>${dt}</td></tr>`;
      }).join("");
    }
  } catch(e2) {
    atbody.innerHTML = "<tr><td colspan=8 style=text-align:center;color:var(--danger);>Error: "+e2.message+"</td></tr>";
  }
}

async function loadQuickPays() {
  const tbody = document.getElementById("qp-tbody");
  try {
    const res = await fetch("/api/admin/get-quickpays.php?limit=100");
    const data = await res.json();
    if (!data.ok) throw new Error(data.error);
    document.getElementById("qp-total").textContent = data.stats.total;
    document.getElementById("qp-24h").textContent = data.stats.last_24h;
    document.getElementById("qp-7d").textContent = data.stats.last_7d;
    if (!data.quickpays.length) {
      tbody.innerHTML = "<tr><td colspan=6 style=text-align:center;padding:2rem;>No payments yet.</td></tr>";
      return;
    }
    tbody.innerHTML = data.quickpays.map(q => {
      const amt = q.amount_cents ? "$" + (q.amount_cents/100).toFixed(2) : "—";
      const dt = q.processed_at ? new Date(q.processed_at).toLocaleString() : "—";
      const status = q.reactivated ? "<span style=color:var(--success);font-weight:600;>✓ Active</span>" : "<span style=color:var(--text-secondary);>Processed</span>";
      return `<tr><td>${q.id}</td><td>${q.member_name||"—"}</td><td>${amt}</td><td style=font-family:monospace;font-size:0.8rem;>${q.transaction_id||"—"}</td><td>${status}</td><td>${dt}</td></tr>`;
    }).join("");
  } catch(e) {
    tbody.innerHTML = "<tr><td colspan=6 style=text-align:center;color:var(--danger);>Error: "+e.message+"</td></tr>";
  }
}

loadMembers();
</script>
</body>
</html>
