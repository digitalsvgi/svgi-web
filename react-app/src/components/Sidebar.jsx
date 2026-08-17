import React from 'react';
import { useAuth } from '../context/AuthContext';

export default function Sidebar({ activeTab, setActiveTab }) {
  const { currentUser, logout } = useAuth();
  const role = currentUser?.role || 'college_user';

  // Define sidebar menu options based on role
  const menuOptions = {
    super_admin: [
      { id: 'dashboard', label: 'Dashboard', icon: 'bi-speedometer2' },
      { id: 'colleges', label: 'Institutional Colleges', icon: 'bi-bank' },
      { id: 'departments', label: 'Departments Management', icon: 'bi-diagram-3' },
      { id: 'submissions', label: 'Submissions Desk', icon: 'bi-folder-check' },
      { id: 'users', label: 'User Directory', icon: 'bi-people' },
      { id: 'logs', label: 'Activity Audits', icon: 'bi-journal-text' }
    ],
    admin: [
      { id: 'dashboard', label: 'Dashboard', icon: 'bi-speedometer2' },
      { id: 'submissions', label: 'Submissions Desk', icon: 'bi-folder2-open' }
    ],
    college_user: [
      { id: 'dashboard', label: 'Dashboard', icon: 'bi-speedometer2' },
      { id: 'submissions', label: 'Work Submissions', icon: 'bi-cloud-arrow-up' }
    ]
  };

  const currentMenu = menuOptions[role] || menuOptions['college_user'];

  return (
    <aside className="sidebar">
      {/* Brand Header */}
      <div className="sidebar-brand">
        <div className="brand-icon-box">
          <i className="bi bi-shield-check"></i>
        </div>
        <div>
          <div className="brand-text">SV Group</div>
          <div className="brand-subtext">CWM Portal</div>
        </div>
      </div>

      {/* Navigation List */}
      <nav className="sidebar-nav">
        <div className="nav-label">Core Modules</div>
        {currentMenu.map((item) => (
          <button
            key={item.id}
            onClick={() => setActiveTab(item.id)}
            className={`nav-link border-0 text-start bg-transparent w-100 ${activeTab === item.id ? 'active' : ''}`}
            style={{ cursor: 'pointer' }}
          >
            <i className={`bi ${item.icon}`}></i>
            <span>{item.label}</span>
          </button>
        ))}

        <div className="nav-label mt-4">Account</div>
        <button
          onClick={logout}
          className="nav-link border-0 text-start bg-transparent w-100 text-danger-hover"
          style={{ cursor: 'pointer' }}
        >
          <i className="bi bi-box-arrow-left text-danger"></i>
          <span className="text-danger">Sign Out</span>
        </button>
      </nav>
    </aside>
  );
}
